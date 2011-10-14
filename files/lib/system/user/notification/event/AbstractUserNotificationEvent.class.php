<?php
namespace wcf\system\user\notification\event;
use wcf\data\user\notification\UserNotification;
use wcf\data\DatabaseObjectDecorator;
use wcf\system\user\notification\type\IUserNotificationType;
use wcf\system\user\notification\object\IUserNotificationObject;

/**
 * Provides default a implementation for user notification events.
 *
 * @author	Marcel Werk, Oliver Kliebisch
 * @copyright	2001-2011 WoltLab GmbH, Oliver Kliebisch
 * @license	GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package	com.woltlab.wcf.notification
 * @subpackage	system.user.notification.event
 * @category 	Community Framework
 */
abstract class AbstractUserNotificationEvent extends DatabaseObjectDecorator implements IUserNotificationEvent {
	/**
	 * @see wcf\data\DatabaseObjectDecorator::$baseClass
	 */
	protected static $baseClass = 'wcf\data\user\notification\event\UserNotificationEvent';
	
	/**
	 * user notification
	 * @var	wcf\data\user\notification\UserNotification
	 */	
	protected $notification = null;
	
	/**
	 * user notification object
	 * @var wcf\system\user\notification\object\IUserNotificationObject
	 */
	protected $userNotificationObject = null;
	
	/**
	 * additional data for this event
	 * @var array<mixed>
	 */
	protected $additionalData = array();
	
	/**
	 * list of actions for this event
	 * @var	array<array>
	 */
	protected $actions = array();
	
	/**
	 * @see	wcf\system\user\notification\event\IUserNotificationEvent::getActions()
	 */	
	public function getActions() {
		return $this->actions;
	}
	
	/**
	 * @see wcf\system\user\notification\event\IUserNotificationEvent::setObject()
	 */
	public function setObject(UserNotification $notification, IUserNotificationObject $object, array $additionalData = array()) {
		$this->notification = $notification;
		$this->userNotificationObject = $object;
		$this->additionalData = $additionalData;
		
		$this->addDefaultAction();
	}
	
	protected function addDefaultAction() {
		$this->actions[] = array(
			'actionName' => 'markAsConfirmed',
			'className' => 'wcf\\data\\user\\notification\\UserNotificationAction',
			'label' => 'OK',
			'objectID' => $this->notification->notificationID
		);
	}
	
	/**
	 * @see wcf\system\user\notification\event\IUserNotificationEvent::supportsNotificationType()
	 */
	public function supportsNotificationType(IUserNotificationType $notificationType) {
		return true;
	}
	
	/**
	 * @see	wcf\system\user\notification\event\IUserNotificationEvent::getAuthorID()
	 */
	public function getAuthorID() {
		return $this->userNotificationObject->getAuthorID();
	}
}
