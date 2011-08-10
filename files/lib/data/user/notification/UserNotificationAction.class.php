<?php
namespace wcf\data\user\notification;
use wcf\data\AbstractDatabaseObjectAction;
use wcf\system\user\notification\UserNotificationHandler;

/**
 * Executes user notification-related actions.
 * 
 * @author	Marcel Werk
 * @copyright	2001-2011 WoltLab GmbH
 * @license	GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package	com.woltlab.wcf.notification
 * @subpackage	data.user.notification
 * @category 	Community Framework
 */
class UserNotificationAction extends AbstractDatabaseObjectAction {
	/**
	 * @see	wcf\data\AbstractDatabaseObjectAction::$className
	 */
	protected $className = 'wcf\data\user\notification\UserNotificationEditor';
	
	/**
	 * @todo	validate if user is not a guest
	 */	
	public function validateLoad() { }
	
	/**
	 * Loads user notifications.
	 * 
	 * @return	array<array>
	 */	
	public function load() {
		return UserNotificationHandler::getInstance()->getNotifications();
	}
}
