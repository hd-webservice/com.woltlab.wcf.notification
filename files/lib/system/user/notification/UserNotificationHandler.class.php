<?php
namespace wcf\system\user\notification;
use wcf\data\user\notification\event\UserNotificationEvent;
use wcf\data\user\notification\event\recipient\UserNotificationEventRecipientList;
use wcf\data\user\notification\UserNotificationAction;
use wcf\system\cache\CacheHandler;
use wcf\system\exception\SystemException;
use wcf\system\user\notification\object\IUserNotificationObject;
use wcf\system\SingletonFactory;

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
		
		// get event object
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
				'packageID' => PACKAGE_ID,
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
}
