<?php
namespace wcf\system\user\notification;
use wcf\data\object\type\ObjectTypeCache;
use wcf\data\user\notification\event\UserNotificationEvent;
use wcf\data\user\notification\event\UserNotificationEventList;
use wcf\data\user\notification\event\recipient\UserNotificationEventRecipientList;
use wcf\data\user\notification\recipient\UserNotificationRecipientList;
use wcf\data\user\notification\UserNotification;
use wcf\data\user\notification\UserNotificationAction;
use wcf\data\user\notification\UserNotificationEditor;
use wcf\data\user\notification\UserNotificationList;
use wcf\system\cache\CacheHandler;
use wcf\system\database\util\PreparedStatementConditionBuilder;
use wcf\system\exception\SystemException;
use wcf\system\package\PackageDependencyHandler;
use wcf\system\user\storage\UserStorageHandler;
use wcf\system\user\notification\object\IUserNotificationObject;
use wcf\system\SingletonFactory;
use wcf\system\WCF;

/**
 * Handles user notifications.
 *
 * @author	Marcel Werk, Oliver Kliebisch
 * @copyright	2001-2011 WoltLab GmbH, Oliver Kliebisch
 * @license	GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package	com.woltlab.wcf.notification
 * @subpackage	system.user.notification
 * @category 	Community Framework
 */
class UserNotificationHandler extends SingletonFactory {
	/**
	 * list of available object types
	 * @var array
	 */
	protected $availableObjectTypes = array();
	
	/**
	 * list of available events
	 * @var array
	 */
	protected $availableEvents = array();
	
	/**
	 * number of outstanding notifications
	 * @var integer
	 */
	protected $notificationCount = null;
	
	/**
	 * @see wcf\system\SingletonFactory::init()
	 */
	protected function init() {
		// get available object types
		$this->availableObjectTypes = ObjectTypeCache::getInstance()->getObjectTypes('com.woltlab.wcf.notification.objectType');
		foreach ($this->availableObjectTypes as $typeName => $object) {
			$this->availableObjectTypes[$typeName] = $object->getProcessor();
		}
		
		// get available events
		CacheHandler::getInstance()->addResource('user-notification-event-'.PACKAGE_ID, WCF_DIR.'cache/cache.user-notification-event-'.PACKAGE_ID.'.php', 'wcf\system\cache\builder\UserNotificationEventCacheBuilder');
		$this->availableEvents = CacheHandler::getInstance()->get('user-notification-event-'.PACKAGE_ID);
	}
	
	/**
	 * Triggers a notification event.
	 *
	 * @param	string								$eventName
	 * @param	string								$objectType
	 * @param	wcf\system\user\notification\object\IUserNotificationObject	$notificationObject
	 * @param	array<integer>							$recipientIDs
	 * @param	array<mixed>							$additionalData
	 */
	public function fireEvent($eventName, $objectType, IUserNotificationObject $notificationObject, array $recipientIDs, array $additionalData = array()) {
		// check given object type and event name
		if (!isset($this->availableEvents[$objectType][$eventName])) {
			throw new SystemException("Unknown event ".$objectType."-".$eventName." given".print_r($this->availableEvents, true));
		}
		
		// get objects
		$objectTypeObject = $this->availableObjectTypes[$objectType];
		$event = $this->availableEvents[$objectType][$eventName];
		// set object data
		$event->setObject(new UserNotification(null, array()), $notificationObject, $additionalData);
		
		// get recipients
		$recipientList = new UserNotificationEventRecipientList();
		$recipientList->getConditionBuilder()->add('event_to_user.eventID = ?', array($event->eventID));
		$recipientList->getConditionBuilder()->add('user_table.userID IN (?)', array($recipientIDs));
		$recipientList->readObjects();
		if (count($recipientList->getObjectIDs())) {
			// save notification
			$action = new UserNotificationAction(array(), 'create', array('data' => array(
				'packageID' => $objectTypeObject->packageID,
				'eventID' => $event->eventID,
				'objectID' => $notificationObject->getObjectID(),
				'authorID' => $event->getAuthorID(),
				'time' => TIME_NOW,
				'additionalData' => serialize($additionalData),
				'recipientIDs' => $recipientList->getObjectIDs()
			)));
			$result = $action->executeAction();
			$notification = $result['returnValues'];
			
			// sends notifications
			foreach ($recipientList->getObjects() as $recipient) {
				foreach ($recipient->getNotificationTypes($event->eventID) as $notificationType) {
					if ($event->supportsNotificationType($notificationType)) {
						$notificationType->send($notification, $recipient, $event);
					}
				}
			}
		}
	}
	
	/**
	 * Revokes an event and all its messages if possible.
	 *
	 * @param	string								$eventName
	 * @param	string								$objectType
	 * @param	wcf\system\user\notification\object\IUserNotificationObject	$notificationObject
	 */
	public function revokeEvent($eventName, $objectType, IUserNotificationObject $notificationObject) {
		$this->revokeEvents(array($eventName), $objectType, array($notificationObject));
	}
	
	/**
	 * Revokes events and all their messages if possible.
	 *
	 * @param	array<string>								$eventName
	 * @param	string									$objectType
	 * @param	array<wcf\system\user\notification\object\IUserNotificationObject>	$notificationObject
	 */
	public function revokeEvents(array $eventNames, $objectType, array $notificationObjects) {
		// check given object type
		if (!isset($this->availableObjectTypes[$objectType])) {
			throw new SystemException("Unknown object type ".$objectType." given");
		}
		
		// get object type object
		$objectTypeObject = $this->availableObjectTypes[$objectType];
		
		// get event ids
		$eventIDs = array();
		foreach ($eventNames as $eventName) {
			if (!isset($this->availableEvents[$objectType][$eventName])) {
				throw new SystemException("Unknown event ".$objectType."-".$eventName." given");
			}
			
			$eventIDs[] = $this->availableEvents[$objectType][$eventName]->eventID;
		}
		
		// get notification object ids
		$notificationObjectIDs = array();
		foreach ($notificationObjects as $notificationObject) {
			$notificationObjectIDs[] = $notificationObject->getObjectID();
		}
		
		// get notifications
		$notificationList = new UserNotificationList();
		$notificationList->getConditionBuilder()->add('user_notification.eventID IN (?)', array($eventIDs));
		$notificationList->getConditionBuilder()->add('user_notification.objectID IN (?)', array($notificationObjectIDs));
		$notificationList->getConditionBuilder()->add('user_notification.packageID = ?', array($objectTypeObject->packageID));
		$notificationList->sqlSelects = 'user_notification_event.eventName';
		$notificationList->sqlJoins = " LEFT JOIN wcf".WCF_N."_user_notification_event user_notification_event ON (user_notification_event.eventID = user_notification.eventID) ";
		$notificationList->readObjects();
		
		$notifications = $notificationList->getObjects();
		$notificationIDs = array_keys($notifications);
		
		// break if no notifications are available
		if (!count($notificationIDs)) {
			return;
		}
		
		// get recipient ids
		$recipientIDs = $uniqueRecipientIDs = array();
		$conditionBuilder = new PreparedStatementConditionBuilder();
		$conditionBuilder->add('user_notification_to_user.notificationID IN (?)', array($notificationIDs));
		$sql = "SELECT	user_notification_to_user.*
			FROM	wcf".WCF_N."_user_notification_to_user user_notification_to_user
			".$conditionBuilder;
		$statement = WCF::getDB()->prepareStatement($sql);
		$statement->execute($conditionBuilder->getParameters());
		while ($row = $statement->fetchArray()) {
			if (!isset($recipientIDs[$row['notificationID']])) $recipientIDs[$row['notificationID']] = array();
			$recipientIDs[$row['notificationID']][] = $row['userID'];
			$uniqueRecipientIDs[$row['userID']] = $row['userID'];
		}
		
		// get recipients
		$recipients = array();
		$recipientList = new UserNotificationRecipientList();
		$recipientList->getConditionBuilder()->add('user.userID IN (?)', array($uniqueRecipientIDs));
		$recipientList->readObjects();
		foreach ($recipientList->getObjects() as $recipient) {
			$recipients[$recipient->userID] = $recipient;
		}
		
		// revoke notifications
		foreach ($notifications as $notification) {
			$event = $this->availableEvents[$objectType][$notification->eventName];
			if (isset($recipientIDs[$notification->notificationID])) {
				foreach ($recipientIDs[$notification->notificationID] as $recipientID) {
					if (isset($recipients[$recipientID])) {
						foreach ($recipients[$recipientID]->getNotificationTypes($notification->eventID) as $notificationType) {
							if ($event->supportsNotificationType($notificationType)) {
								$notificationType->revoke($notification, $recipients[$recipientID], $event);
							}
						}
					}
				}
			}
		}
		
		// delete notifications
		UserNotificationEditor::deleteAll($notificationIDs);
		
		// reset recipient storage
		foreach ($uniqueRecipientIDs as $recipientID) {
			UserStorageHandler::getInstance()->reset($recipientID, 'userNotificationCount');
		}
	}
		
	/**
	 * Returns the number of outstanding notifications for the active user.
	 * 
	 * @return	integer
	 */
	public function getNotificationCount() {
		if ($this->notificationCount === null) {
			$this->notificationCount = 0;
		
			if (WCF::getUser()->userID) {
				// load storage data
				UserStorageHandler::getInstance()->loadStorage(array(WCF::getUser()->userID));
					
				// get ids
				$data = UserStorageHandler::getInstance()->getStorage(array(WCF::getUser()->userID), 'userNotificationCount');
				
				// cache does not exist or is outdated
				if ($data[WCF::getUser()->userID] === null) {
					$conditionBuilder = new PreparedStatementConditionBuilder();
					$conditionBuilder->add('notification.notificationID = notification_to_user.notificationID');
					$conditionBuilder->add('notification_to_user.userID = ?', array(WCF::getUser()->userID));
					$conditionBuilder->add('notification_to_user.confirmed = 0');
					$conditionBuilder->add('notification.packageID IN (?)', array(PackageDependencyHandler::getDependencies()));
					
					$sql = "SELECT	COUNT(*) AS count
						FROM	wcf".WCF_N."_user_notification_to_user notification_to_user,
							wcf".WCF_N."_user_notification notification
						".$conditionBuilder->__toString();
					$statement = WCF::getDB()->prepareStatement($sql);
					$statement->execute($conditionBuilder->getParameters());
					$row = $statement->fetchArray();
					$this->notificationCount = $row['count'];
					
					// update storage data
					UserStorageHandler::getInstance()->update(WCF::getUser()->userID, 'userNotificationCount', serialize($this->notificationCount), 1);
				}
				else {
					$this->notificationCount = unserialize($data[WCF::getUser()->userID]);
				}
			}
		}
		
		return $this->notificationCount;
	}
	
	/**
	 * Returns a limited list of outstanding notifications.
	 * 
	 * @param	integer		$limit
	 * @param	integer		$offset
	 * @return	array<array>
	 */	
	public function getNotifications($limit = 10, $offset = 0) {
		// build enormous query
		$conditions = new PreparedStatementConditionBuilder();
		$conditions->add("notification_to_user.userID = ?", array(WCF::getUser()->userID));
		$conditions->add("notification_to_user.confirmed = ?", array(0));
		$conditions->add("notification.notificationID = notification_to_user.notificationID");
		$conditions->add("notification.packageID IN (?)", array(PackageDependencyHandler::getDependencies()));
		
		$sql = "SELECT		notification_to_user.notificationID, notification_event.eventID,
					object_type.objectType, notification.objectID,
					notification.additionalData
			FROM		wcf".WCF_N."_user_notification_to_user notification_to_user,
					wcf".WCF_N."_user_notification notification
			LEFT JOIN	wcf".WCF_N."_user_notification_event notification_event
			ON		(notification_event.eventID = notification.eventID)
			LEFT JOIN	wcf".WCF_N."_object_type object_type
			ON		(object_type.objectTypeID = notification_event.objectTypeID)
			".$conditions;
		$statement = WCF::getDB()->prepareStatement($sql, $limit, $offset);
		$statement->execute($conditions->getParameters());
		
		$events = $objectTypes = $eventIDs = $notificationIDs = array();
		while ($row = $statement->fetchArray()) {
			$events[] = $row;
			
			// cache object types
			if (!isset($objectTypes[$row['objectType']])) {
				$objectTypes[$row['objectType']] = array(
					'objectType' => $this->availableObjectTypes[$row['objectType']],
					'objectIDs' => array(),
					'objects' => array()
				);
			}
			
			$objectTypes[$row['objectType']]['objectIDs'][] = $row['objectID'];
			$eventIDs[] = $row['eventID'];
			$notificationIDs[] = $row['notificationID'];
		}
		
		// return an empty set if no notifications exist
		if (!count($events)) {
			return array(
				'count' => 0,
				'notifications' => array()
			);
		}
		
		// load objects associated with each object type
		foreach ($objectTypes as $objectType => $objectData) {
			if (count($objectData['objectIDs']) > 1) {
				$objectTypes[$objectType]['objects'] = $objectData['objectType']->getObjectsByIDs($objectData['objectIDs']);
			}
			else {
				$objectTypes[$objectType]['objects'] = $objectData['objectType']->getObjectByID($objectData['objectIDs'][0]);
			}
		}
		
		// load required events
		$eventList = new UserNotificationEventList();
		$eventList->getConditionBuilder()->add("user_notification_event.eventID IN (?)", array($eventIDs));
		$eventList->sqlLimit = 0;
		$eventList->readObjects();
		$eventObjects = $eventList->getObjects();
		
		// load notification objects
		$notificationList = new UserNotificationList();
		$notificationList->getConditionBuilder()->add("user_notification.notificationID IN (?)", array($notificationIDs));
		$notificationList->sqlLimit = 0;
		$notificationList->readObjects();
		$notificationObjects = $notificationList->getObjects();
		
		// build notification data
		$notifications = array();
		foreach ($events as $event) {
			$className = $eventObjects[$event['eventID']]->className;
			$class = new $className($eventObjects[$event['eventID']]);
			$class->setObject(
				$notificationObjects[$event['notificationID']],
				$objectTypes[$event['objectType']]['objects'][$event['objectID']],
				unserialize($event['additionalData'])
			);
			
			$notifications[] = array(
				'notificationID' => $event['notificationID'],
				'label' => $class->getShortOutput(),
				'message' => $class->getRenderedOutput()
			);
		}
		
		return array(
			'count' => count($notifications),
			'notifications' => $notifications
		);
	}
	
	/**
	 * Marks a notification as confirmed.
	 * 
	 * @param	integer		$notificationID
	 * @param	integer		$confirmationTime
	 */
	public function markAsConfirmed($notificationID, $confirmationTime = TIME_NOW) {
		$sql = "UPDATE	wcf".WCF_N."_user_notification_to_user
			SET	confirmed = ?,
				confirmationTime = ?
			WHERE	notificationID = ?
				AND userID = ?";
		$statement = WCF::getDB()->prepareStatement($sql);
		$statement->execute(array(
			1,
			$confirmationTime,
			$notificationID,
			WCF::getUser()->userID
		));
	}
	
	/**
	 * Returns event id for given object type and event, returns NULL on failure.
	 * 
	 * @param	string		$objectType
	 * @param	string		$eventName
	 * @return	integer
	 */
	public function getEventID($objectType, $eventName) {
		$conditions = new PreparedStatementConditionBuilder();
		$conditions->add("object_type.objectTypeID = event.objectTypeID", array());
		$conditions->add("object_type.objectType = ?", array($objectType));
		$conditions->add("event.eventName = ?", array($eventName));
		$conditions->add("event.packageID IN (?)", array(PackageDependencyHandler::getDependencies()));
		
		$sql = "SELECT	event.eventID
			FROM	wcf".WCF_N."_user_notification_event event,
				wcf".WCF_N."_object_type object_type
			".$conditions;
		$statement = WCF::getDB()->prepareStatement($sql);
		$statement->execute($conditions->getParameters());
		$row = $statement->fetchArray();
		
		if (!$row) return null;
		
		return $row['eventID'];
	}
	
	/**
	 * Retrieves a notification id.
	 * 
	 * @param	integer		$eventID
	 * @param	integer		$objectID
	 * @param	integer		$authorID
	 * @param	integer		$time
	 * @return	integer
	 */
	public function getNotificationID($eventID, $objectID, $authorID = null, $time = null) {
		if ($authorID === null && $time === null) {
			throw new SystemException("authorID and time cannot be omitted at once.");
		}
		
		$conditions = new PreparedStatementConditionBuilder();
		$conditions->add("eventID = ?", array($eventID));
		$conditions->add("objectID = ?", array($objectID));
		$conditions->add("packageID IN (?)", array(PackageDependencyHandler::getDependencies()));
		if ($authorID !== null) $conditions->add("authorID = ?", array($authorID));
		if ($time !== null) $conditions->add("time = ?", array($time));
		
		$sql = "SELECT	notificationID
			FROM	wcf".WCF_N."_user_notification
			".$conditions;
		$statement = WCF::getDB()->prepareStatement($sql);
		$statement->execute($conditions->getParameters());
		
		$notifications = array();
		$row = $statement->fetchArray();
		
		return ($row === false) ? null : $row['notificationID'];
	}
}
