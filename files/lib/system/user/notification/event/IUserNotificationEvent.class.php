<?php
namespace wcf\system\user\notification\event;
use wcf\data\user\notification\UserNotification;
use wcf\data\IDatabaseObjectProcessor;
use wcf\system\user\notification\type\IUserNotificationType;
use wcf\system\user\notification\object\IUserNotificationObject;

/**
 * This interface should be implemented by every event which is fired by the notification system.
 *
 * @author	Marcel Werk, Oliver Kliebisch
 * @copyright	2001-2011 WoltLab GmbH, Oliver Kliebisch
 * @license	GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package	com.woltlab.wcf.notification
 * @subpackage	system.user.notification.event
 * @category 	Community Framework
 */
interface IUserNotificationEvent extends IDatabaseObjectProcessor {
	/**
	 * Returns the message for this notification event.
	 *
	 * @param	wcf\system\user\notification\type\IUserNotificationType	$notificationType
	 * @return	string
	 */
	public function getMessage(IUserNotificationType $notificationType);

	/**
	 * Returns the short output for this notification event.
	 *
	 * @return	string
	 */
	public function getShortOutput();

	/**
	 * Returns the medium output for this notification event.
	 *
	 * @return	string
	 */
	public function getMediumOutput();

	/**
	 * Returns the full output for this notification event.
	 *
	 * @return	string
	 */
	public function getOutput();

	/**
	 * Returns the human-readable title of this event.
	 *
	 * @return	string
	 */
	public function getTitle();

	/**
	 * Returns the human-readable description of this event.
	 *
	 * @return	string
	 */
	public function getDescription();
	
	/**
	 * Returns the author id for this notification event.
	 * 
	 * @return	integer
	 */
	public function getAuthorID();

	/**
	 * Returns true if this event supports the given notification type.
	 *
	 * @param	wcf\system\user\notification\type\IUserNotificationType	$notificationType
	 * @return	boolean
	 */
	public function supportsNotificationType(IUserNotificationType $notificationType);
	
	/**
	 * Sets the object for the event.
	 *
	 * @param	wcf\data\user\notification\UserNotification			$notification
	 * @param	wcf\system\user\notification\object\IUserNotificationObject	$object
	 * @param	array<mixed>							$additionalData
	 */
	public function setObject(UserNotification $notification, IUserNotificationObject $object, array $additionalData = array());
}
