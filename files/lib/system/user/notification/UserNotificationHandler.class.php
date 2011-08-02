<?php
namespace wcf\system\user\notification;
use wcf\data\user\notification\event\UserNotificationEvent;
use wcf\data\user\notification\event\recipient\UserNotificationEventRecipientList;
use wcf\data\user\notification\UserNotificationAction;
use wcf\data\user\notification\UserNotificationEditor;
use wcf\data\user\notification\UserNotificationList;
use wcf\system\cache\CacheHandler;
use wcf\system\database\util\PreparedStatementConditionBuilder;
use wcf\system\exception\SystemException;
use wcf\system\package\PackageDependencyHandler;
use wcf\system\storage\StorageHandler;
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
	 * number of outstanding notifications
	 * @var integer
	 */
	protected $notificationCount = null;
	
	/**
	 * @see wcf\system\SingletonFactory::init()
	 */
	protected function init() {
		// load cache
		CacheHandler::getInstance()->addResource('user-notification-object-type-'.PACKAGE_ID, WCF_DIR.'cache/cache.user-notification-object-type-'.PACKAGE_ID.'.php', 'wcf\system\cache\builder\CacheBuilderUserNotificationObjectType');
		$this->availableObjectTypes = CacheHandler::getInstance()->get('user-notification-object-type-'.PACKAGE_ID);
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
		if (!isset($this->availableObjectTypes[$objectType]['events'][$eventName])) {
			throw new SystemException("Unknown event '.$objectType.'-.$eventName.' given");
		}
		
		// get objects
		$objectTypeObject = $this->availableObjectTypes[$objectType]['object'];
		$event = $this->availableObjectTypes[$objectType]['events'][$eventName];
		// set object data
		$event->setObject($notificationObject, $additionalData);
		
		// get recipients
		$recipientList = new UserNotificationEventRecipientList();
		$recipientList->getConditionBuilder()->add('event_to_user.eventID = ?', array($event->eventID));
		$recipientList->getConditionBuilder()->add('user.userID IN (?)', array($recipientIDs));
		$recipientList->readObjects();
		if (count($recipientList->getObjectIDs())) {
			// save notification
			$action = new UserNotificationAction(array(), 'create', array('data' => array(
				'packageID' => $objectTypeObject->packageID,
				'eventID' => $event->eventID,
				'objectID' => $notificationObject->getObjectID(),
				'authorID' => $notificationObject->getAuthorID(),
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
	public function revokeEvent(array $eventName, $objectType, IUserNotificationObject $notificationObject) {
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
			throw new SystemException("Unknown object type '.$objectType.' given");
		}
		
		// get object type object
		$objectTypeObject = $this->availableObjectTypes[$objectType]['object'];
		
		// get event ids
		$eventIDs = array();
		foreach ($eventNames as $eventName) {
			if (!isset($this->availableObjectTypes[$objectType]['events'][$eventName])) {
				throw new SystemException("Unknown event '.$objectType.'-.$eventName.' given");
			}
			
			$eventIDs[] = $this->availableObjectTypes[$objectType]['events'][$eventName]->eventID;
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
		$notificationList->readObjectIDs();
		$notificationList->readObjectID();
		$notifications = $notificationList->getObjects();
		$notificationIDs = $notificationList->getObjectIDs();
		
		// get recipient ids
		$recipientIDs = $uniqueRecipientIDs = array();
		$conditionBuilder = new PreparedStatementConditionBuilder();
		$conditionBuilder->add('user_notification_to_user.notificationID IN (?)', array($notificationIDs));
		$sql = "SELECT	user_notification_to_user.*
			FROM	wcf".WCF_N."_user_notification_to_user user_notification_to_user";
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
			$event = $this->availableObjectTypes[$objectType]['events'][$notification->eventName];
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
			StorageHandler::getInstance()->reset($recipientID, 'userNotificationCount');
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
				StorageHandler::getInstance()->loadStorage(array(WCF::getUser()->userID));
					
				// get ids
				$data = StorageHandler::getInstance()->getStorage(array(WCF::getUser()->userID), 'userNotificationCount');
				
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
					StorageHandler::getInstance()->update(WCF::getUser()->userID, 'userNotificationCount', serialize($this->notificationCount), 1);
				}
				else {
					$this->notificationCount = unserialize($data[WCF::getUser()->userID]);
				}
			}
		}
		
		return $this->notificationCount;
	}
}
