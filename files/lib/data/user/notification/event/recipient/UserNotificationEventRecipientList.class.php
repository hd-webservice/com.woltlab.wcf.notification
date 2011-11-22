<?php
namespace wcf\data\user\notification\event\recipient;
use wcf\data\user\notification\recipient\UserNotificationRecipientList;

/**
 * Extends the user list to provide special functions for handling recipients of user notifications.
 * 
 * @author	Marcel Werk
 * @copyright	2001-2011 WoltLab GmbH
 * @license	GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package	com.woltlab.wcf.notification
 * @subpackage	data.user.notification.event.recipient
 * @category 	Community Framework
 */
class UserNotificationEventRecipientList extends UserNotificationRecipientList {
	/**
	 * Creates a new UserNotificationEventRecipientList object.
	 */
	public function __construct() {
		parent::__construct();
		
		$this->sqlConditionJoins = ", wcf".WCF_N."_user_notification_event_to_user event_to_user";
		$this->getConditionBuilder()->add("event_to_user.userID = user_table.userID");
	}
}
