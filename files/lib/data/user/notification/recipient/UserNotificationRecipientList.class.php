<?php
namespace wcf\data\user\notification\recipient;
use wcf\data\object\type\ObjectType;
use wcf\data\user\UserList;
use wcf\data\user\User;
use wcf\system\database\util\PreparedStatementConditionBuilder;
use wcf\system\WCF;

/**
 * Extends the user list to provide special functions for handling recipients of user notifications.
 * 
 * @author	Marcel Werk
 * @copyright	2001-2011 WoltLab GmbH
 * @license	GNU Lesser General Public License <http://opensource.org/licenses/lgpl-license.php>
 * @package	com.woltlab.wcf.notification
 * @subpackage	data.user.notification.recipient
 * @category 	Community Framework
 */
class UserNotificationRecipientList extends UserList {
	/**
	 * @see wcf\data\DatabaseObjectList\DatabaseObjectList::readObjects()
	 */
	public function readObjects() {
		if ($this->objectIDs === null) {
			$this->readObjectIDs();
		}
		
		if (!count($this->objectIDs)) {
			return;
		}
		
		// get notification types
		$notificationTypes = array();
		$conditionBuilder = new PreparedStatementConditionBuilder();
		$conditionBuilder->add('event_to_user.userID IN (?)', array($this->objectIDs));
		
		$sql = "SELECT		event_to_user.eventID, event_to_user.userID, notification_type.*
			FROM		wcf".WCF_N."_user_notification_event_notification_type event_to_user
			LEFT JOIN	wcf".WCF_N."_object_type notification_type
			ON		(notification_type.objectTypeID = event_to_user.notificationTypeID)
			".$conditionBuilder->__toString();
		$statement = WCF::getDB()->prepareStatement($sql);
		$statement->execute($conditionBuilder->getParameters());
		while ($row = $statement->fetchArray()) {
			$databaseObject = new ObjectType(null, $row);
			$notificationTypes[$row['userID']][$row['eventID']][] = $databaseObject->getProcessor();
		}

		// get users
		$sql = "SELECT	".(!empty($this->sqlSelects) ? $this->sqlSelects.',' : '')."
				".$this->getDatabaseTableAlias().".*
			FROM	".$this->getDatabaseTableName()." ".$this->getDatabaseTableAlias()."
				".$this->sqlJoins."
			WHERE	".$this->getDatabaseTableAlias().".".$this->getDatabaseTableIndexName()." IN (?".str_repeat(',?', count($this->objectIDs) - 1).")
				".(!empty($this->sqlOrderBy) ? "ORDER BY ".$this->sqlOrderBy : '');
		$statement = WCF::getDB()->prepareStatement($sql);
		$statement->execute($this->objectIDs);
		while ($row = $statement->fetchArray()) {
			$row['notificationTypes'] = (isset($notificationTypes[$row['userID']]) ? $notificationTypes[$row['userID']] : array());
			$this->objects[] = new UserNotificationRecipient(new User(null, $row)); 
		}
	}
}
