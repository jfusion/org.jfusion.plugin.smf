<?php namespace JFusion\Plugins\smf;
/**
 * @category   Plugins
 * @package    JFusion\Plugins
 * @subpackage smf
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

use JFusion\Factory;
use JFusion\Framework;
use JFusion\User\Userinfo;

use Joomla\Language\Text;

use Psr\Log\LogLevel;

use RuntimeException;
use Exception;
use stdClass;

/**
 * JFusion User Class for SMF 1.1.x
 * For detailed descriptions on these functions please check Plugin_User
 *
 * @category   Plugins
 * @package    JFusion\Plugins
 * @subpackage smf
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class User extends \JFusion\Plugin\User
{
    /**
     * get user
     *
     * @param Userinfo $userinfo holds the new user data
     *
     * @access public
     *
     * @return null|Userinfo
     */
    function getUser(Userinfo $userinfo)
    {
	    $user = null;
	    try {
		    //get the identifier
		    list($identifier_type, $identifier) = $this->getUserIdentifier($userinfo, 'a.memberName', 'a.emailAddress', 'a.ID_MEMBER');
		    // initialise some objects
		    $db = Factory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('a.ID_MEMBER as userid, a.memberName as username, a.realName as name, a.emailAddress as email, a.passwd as password, a.passwordSalt as password_salt, a.validation_code as activation, a.is_activated, null as reason, a.lastLogin as lastvisit, a.ID_GROUP as group_id, a.ID_POST_GROUP as postgroup, a.additionalGroups')
			    ->from('#__members as a')
		        ->where($db->quoteName($identifier_type) . ' = ' . $db->quote($identifier));

		    $db->setQuery($query);
		    $result = $db->loadObject();
		    if ($result) {
			    if ($result->group_id == 0) {
				    $result->group_name = 'Default Usergroup';
			    } else {
				    $query = $db->getQuery(true)
					    ->select('groupName')
					    ->from('#__membergroups')
					    ->where('ID_GROUP = ' . (int)$result->group_id);

				    $db->setQuery($query);
				    $result->group_name = $db->loadResult();
			    }
			    $result->groups = array($result->group_id);
			    $result->groupnames = array($result->group_name);

			    if (!empty($result->additionalGroups)) {
				    $groups = explode(',', $result->additionalGroups);

				    foreach($groups as $group) {
					    $query = $db->getQuery(true)
						    ->select('groupName')
						    ->from('#__membergroups')
						    ->where('ID_GROUP = ' . (int)$group);

					    $db->setQuery($query);
					    $result->groups[] = $group;
					    $result->groupnames[] = $db->loadResult();
				    }
			    }

			    //Check to see if they are banned
			    $query = $db->getQuery(true)
				    ->select('ID_BAN_GROUP, expire_time')
				    ->from('#__ban_groups')
				    ->where('name = ' . $db->quote($result->username));

			    $db->setQuery($query);
			    $expire_time = $db->loadObject();
			    if ($expire_time) {
				    if ($expire_time->expire_time == '' || $expire_time->expire_time > time()) {
					    $result->block = true;
				    } else {
					    $result->block = false;
				    }
			    } else {
				    $result->block = false;
			    }
			    if ($result->is_activated == 1) {
				    $result->activation = null;
			    }
			    $user = new Userinfo($this->getJname());
			    $user->bind($result);
		    }
	    } catch (Exception $e) {
		    Framework::raise(LogLevel::ERROR, $e, $this->getJname());
	    }
        return $user;
    }

	/**
	 * delete user
	 *
	 * @param Userinfo $userinfo holds the new user data
	 *
	 * @throws \RuntimeException
	 * @access public
	 *
	 * @return boolean returns true on success and false on error
	 */
    function deleteUser(Userinfo $userinfo)
    {
	    $db = Factory::getDatabase($this->getJname());

	    $query = $db->getQuery(true)
		    ->delete('#__members')
		    ->where('memberName = ' . $db->quote($userinfo->username));

	    $db->setQuery($query);
	    $db->execute();

	    //update the stats
	    $query = $db->getQuery(true)
		    ->update('#__settings')
		    ->set('value = value - 1')
		    ->where('variable = ' . $db->quote('totalMembers'));

	    $db->setQuery($query);
	    $db->execute();

	    $query = $db->getQuery(true)
		    ->select('MAX(ID_MEMBER) as ID_MEMBER')
		    ->from('#__members')
		    ->where('is_activated = 1');

	    $db->setQuery($query);
	    $resultID = $db->loadObject();
	    if (!$resultID) {
		    //return the error
		    throw new RuntimeException($userinfo->username);
	    } else {
		    $query = $db->getQuery(true)
			    ->select('realName as name')
			    ->from('#__members')
			    ->where('ID_MEMBER = ' . $db->quote($resultID->ID_MEMBER));

		    $db->setQuery($query, 0 , 1);
		    $resultName = $db->loadObject();
		    if (!$resultName) {
			    //return the error
			    throw new RuntimeException($userinfo->username);
		    } else {
			    $query = 'REPLACE INTO #__settings (variable, value) VALUES (\'latestMember\', ' . (int)$resultID->ID_MEMBER . '), (\'latestRealName\', ' . $db->quote($resultName->name) . ')';
			    $db->setQuery($query);
			    $db->execute();
		    }
	    }
        return true;
    }

    /**
     * destroy session
     *
     * @param Userinfo $userinfo holds the new user data
     * @param array  $options  Status array
     *
     * @access public
     *
     * @return array
     */
    function destroySession(Userinfo $userinfo, $options)
    {
        $status = array(LogLevel::ERROR => array(), LogLevel::DEBUG => array());
	    try {
		    $status[LogLevel::DEBUG][] = $this->addCookie($this->params->get('cookie_name'), '', 0, $this->params->get('cookie_path'), $this->params->get('cookie_domain'), $this->params->get('secure'), $this->params->get('httponly'));

		    $db = Factory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->delete('#__log_online')
			    ->where('ID_MEMBER = ' . (int)$userinfo->userid);

		    $db->setQuery($query);
		    $db->execute();
	    } catch (Exception $e) {
		    $status[LogLevel::ERROR][] = $e->getMessage();
	    }
        return $status;
    }

    /**
     * create session
     *
     * @param Userinfo $userinfo holds the new user data
     * @param array  $options  options
     *
     * @access public
     *
     * @return array
     */
    function createSession(Userinfo $userinfo, $options)
    {
        $status = array('error' => array(), 'debug' => array());
        //do not create sessions for blocked users
        if (!empty($userinfo->block) || !empty($userinfo->activation)) {
            $status['error'][] = Text::_('FUSION_BLOCKED_USER');
        } else {
	        /**
	         * TODO: trying setting cookies direct curl may not be needed anymore?
	         * $status = $this->curlLogin($userinfo, $options, $this->params->get('brute_force'));
	         */
	        $cookie_expire = $this->params->get('cookie_expire');

	        // Get the data and path to set it on.
	        $data = serialize(array($userinfo->userid, sha1($userinfo->password . $userinfo->password_salt), time() + $cookie_expire, 2));

	        // Set the cookie, $_COOKIE, and session variable.
	        $status['debug'][] = $this->addCookie($this->params->get('cookie_name'), $data, $cookie_expire, $this->params->get('cookie_path'), $this->params->get('cookie_domain'), $this->params->get('secure'), $this->params->get('httponly'));
        }
        return $status;
    }

    /**
     * @param Userinfo $userinfo      holds the new user data
     * @param Userinfo &$existinguser holds the existing user data
     *
     * @access public
     *
     * @return void
     */
    function updatePassword(Userinfo $userinfo, Userinfo &$existinguser)
    {
	    $existinguser->password = sha1(strtolower($userinfo->username) . $userinfo->password_clear);
	    $existinguser->password_salt = substr(md5(rand()), 0, 4);
	    $db = Factory::getDatabase($this->getJname());

	    $query = $db->getQuery(true)
		    ->update('#__members')
		    ->set('passwd = ' . $db->quote($existinguser->password))
		    ->set('passwordSalt = ' . $db->quote($existinguser->password_salt))
		    ->where('ID_MEMBER = ' . (int)$existinguser->userid);

	    $db->setQuery($query);
	    $db->execute();

	    $this->debugger->addDebug(Text::_('PASSWORD_UPDATE') . ' ' . substr($existinguser->password, 0, 6) . '********');
    }

    /**
     * @param Userinfo $userinfo      holds the new user data
     * @param Userinfo &$existinguser holds the existing user data
     *
     * @access public
     *
     * @return void
     */
    function updateEmail(Userinfo $userinfo, Userinfo &$existinguser)
    {
	    //we need to update the email
	    $db = Factory::getDatabase($this->getJname());

	    $query = $db->getQuery(true)
		    ->update('#__members')
		    ->set('emailAddress = ' . $db->quote($userinfo->email))
		    ->where('ID_MEMBER = ' . (int)$existinguser->userid);

	    $db->setQuery($query);
	    $db->execute();

	    $this->debugger->addDebug(Text::_('EMAIL_UPDATE') . ': ' . $existinguser->email . ' -> ' . $userinfo->email);
    }

	/**
	 * @param Userinfo $userinfo      holds the new user data
	 * @param Userinfo &$existinguser holds the existing user data
	 *
	 * @throws RuntimeException
	 * @access public
	 *
	 * @return void
	 */
	public function updateUsergroup(Userinfo $userinfo, Userinfo &$existinguser)
    {
	    $usergroups = $this->getCorrectUserGroups($userinfo);
	    if (empty($usergroups)) {
		    throw new RuntimeException(Text::_('ADVANCED_GROUPMODE_MASTERGROUP_NOTEXIST'));
	    } else {
		    $usergroup = $usergroups[0];

		    if (!isset($usergroup->groups)) {
			    $usergroup->groups = array();
		    }

		    $db = Factory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->update('#__members')
			    ->set('ID_GROUP = ' . $db->quote($usergroup->defaultgroup));

		    if ($this->params->get('compare_postgroup', false) ) {
			    $query->set('ID_POST_GROUP = ' . $db->quote($usergroup->postgroup));
		    }
		    if ($this->params->get('compare_membergroups', true) ) {
			    $query->set('additionalGroups = ' . $db->quote(join(',', $usergroup->groups)));
		    }
		    $query->where('ID_MEMBER = ' . (int)$existinguser->userid);

		    $db->setQuery($query);
		    $db->execute();

		    $groups = $usergroup->groups;
		    $groups[] = $usergroup->defaultgroup;

		    $existinggroups = $existinguser->groups;
		    $existinggroups[] = $existinguser->group_id;

		    $this->debugger->addDebug(Text::_('GROUP_UPDATE') . ': ' . implode(' , ', $existinggroups) . ' -> ' . implode(' , ', $groups));
	    }
    }

	/**
	 * @param Userinfo &$userinfo
	 * @param Userinfo &$existinguser
	 *
	 * @return bool
	 */
	function executeUpdateUsergroup(Userinfo $userinfo, Userinfo &$existinguser)
	{
		$update_groups = false;
		$usergroups = $this->getCorrectUserGroups($userinfo);
		$usergroup = $usergroups[0];

		$groups = (isset($usergroup->groups)) ? $usergroup->groups : array();

		//check to see if the default groups are different
		if ($usergroup->defaultgroup != $existinguser->group_id ) {
			$update_groups = true;
		} else if ($this->params->get('compare_postgroup', false) && $usergroup->postgroup != $existinguser->postgroup ) {
			$update_groups = true;
		} elseif ($this->params->get('compare_membergroups', true)) {
			if (count($existinguser->groups) != count($groups)) {
				$update_groups = true;
			} else {
				foreach ($groups as $gid) {
					if (!in_array($gid, $existinguser->groups)) {
						$update_groups = true;
						break;
					}
				}
			}
		}

		if ($update_groups) {
			$this->updateUsergroup($userinfo, $existinguser);
		}
		return $update_groups;
	}

    /**
     * @param Userinfo $userinfo      holds the new user data
     * @param Userinfo &$existinguser holds the existing user data
     *
     * @access public
     *
     * @return void
     */
    function blockUser(Userinfo $userinfo, Userinfo &$existinguser)
    {
	    $db = Factory::getDatabase($this->getJname());
	    $ban = new stdClass;
	    $ban->ID_BAN_GROUP = null;
	    $ban->name = $existinguser->username;
	    $ban->ban_time = time();
	    $ban->expire_time = null;
	    $ban->cannot_access = 1;
	    $ban->cannot_register = 0;
	    $ban->cannot_post = 0;
	    $ban->cannot_login = 0;
	    $ban->reason = 'You have been banned from this software. Please contact your site admin for more details';
	    //now append the new user data
	    $db->insertObject('#__ban_groups', $ban, 'ID_BAN_GROUP');

	    $ban_item = new stdClass;
	    $ban_item->ID_BAN_GROUP = $ban->ID_BAN_GROUP;
	    $ban_item->ID_MEMBER = $existinguser->userid;
	    $db->insertObject('#__ban_items', $ban_item, 'ID_BAN');

	    $this->debugger->addDebug(Text::_('BLOCK_UPDATE') . ': ' . $existinguser->block . ' -> ' . $userinfo->block);
    }

    /**
     * unblock user
     *
     * @param Userinfo $userinfo      holds the new user data
     * @param Userinfo &$existinguser holds the existing user data
     *
     * @access public
     *
     * @return void
     */
    function unblockUser(Userinfo $userinfo, Userinfo &$existinguser)
    {
	    $db = Factory::getDatabase($this->getJname());

	    $query = $db->getQuery(true)
		    ->delete('#__ban_groups')
		    ->where('name = ' . $db->quote($existinguser->username));

	    $db->setQuery($query);
	    $db->execute();

	    $query = $db->getQuery(true)
		    ->delete('#__ban_items')
		    ->where('ID_MEMBER = ' . (int)$existinguser->userid);

	    $db->setQuery($query);
	    $db->execute();

	    $this->debugger->addDebug(Text::_('BLOCK_UPDATE') . ': ' . $existinguser->block . ' -> ' . $userinfo->block);
    }

    /**
     * activate user
     *
     * @param Userinfo $userinfo      holds the new user data
     * @param Userinfo &$existinguser holds the existing user data
     *
     * @access public
     *
     * @return void
     */
    function activateUser(Userinfo $userinfo, Userinfo &$existinguser)
    {
	    $db = Factory::getDatabase($this->getJname());

	    $query = $db->getQuery(true)
		    ->update('#__members')
		    ->set('is_activated = 1')
		    ->set('validation_code = ' . $db->quote(''))
		    ->where('ID_MEMBER = ' . (int)$existinguser->userid);

	    $db->setQuery($query);
	    $db->execute();

	    $this->debugger->addDebug(Text::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation);
    }

    /**
     * deactivate user
     *
     * @param Userinfo $userinfo      holds the new user data
     * @param Userinfo &$existinguser holds the existing user data
     *
     * @access public
     *
     * @return void
     */
    function inactivateUser(Userinfo $userinfo, Userinfo &$existinguser)
    {
	    $db = Factory::getDatabase($this->getJname());

	    $query = $db->getQuery(true)
		    ->update('#__members')
		    ->set('is_activated = 0')
		    ->set('validation_code = ' . $db->quote($userinfo->activation))
		    ->where('ID_MEMBER = ' . (int)$existinguser->userid);

	    $db->setQuery($query);
	    $db->execute();

	    $this->debugger->addDebug(Text::_('ACTIVATION_UPDATE') . ': ' . $existinguser->activation . ' -> ' . $userinfo->activation);
    }

	/**
	 * Creates a new user
	 *
	 * @param Userinfo $userinfo holds the new user data
	 *
	 * @throws \RuntimeException
	 * @access public
	 *
	 * @return Userinfo
	 */
    function createUser(Userinfo $userinfo)
    {
	    //we need to create a new SMF user
	    $db = Factory::getDatabase($this->getJname());

	    $usergroups = $this->getCorrectUserGroups($userinfo);
	    if (empty($usergroups)) {
		    throw new RuntimeException('USERGROUP_MISSING');
	    } else {
		    $usergroup = $usergroups[0];

		    if (!isset($usergroup->groups)) {
			    $usergroup->groups = array();
		    }

		    //prepare the user variables
		    $user = new stdClass;
		    $user->ID_MEMBER = null;
		    $user->memberName = $userinfo->username;
		    $user->realName = $userinfo->name;
		    $user->emailAddress = $userinfo->email;
		    if (isset($userinfo->password_clear)) {
			    $user->passwd = sha1(strtolower($userinfo->username) . $userinfo->password_clear);
			    $user->passwordSalt = substr(md5(rand()), 0, 4);
		    } else {
			    $user->passwd = $userinfo->password;
			    if (!isset($userinfo->password_salt)) {
				    $user->passwordSalt = substr(md5(rand()), 0, 4);
			    } else {
				    $user->passwordSalt = $userinfo->password_salt;
			    }
		    }
		    $user->posts = 0;
		    $user->dateRegistered = time();
		    if ($userinfo->activation) {
			    $user->is_activated = 0;
			    $user->validation_code = $userinfo->activation;
		    } else {
			    $user->is_activated = 1;
			    $user->validation_code = '';
		    }
		    $user->personalText = '';
		    $user->pm_email_notify = 1;
		    $user->hideEmail = 1;
		    $user->ID_THEME = 0;

		    $user->ID_GROUP = $usergroup->defaultgroup;
		    $user->additionalGroups = join(',', $usergroup->groups);
		    $user->ID_POST_GROUP = $usergroup->postgroup;

		    $db->insertObject('#__members', $user, 'ID_MEMBER');
		    //now append the new user data

		    //update the stats

		    $query = $db->getQuery(true)
			    ->update('#__settings')
			    ->set('value = value + 1')
			    ->where('variable = ' . $db->quote('totalMembers'));

		    $db->setQuery($query);
		    $db->execute();

		    $date = strftime('%Y-%m-%d');

		    $query = $db->getQuery(true)
			    ->update('#__log_activity')
			    ->set('registers = registers + 1')
			    ->where('date = ' . $db->quote($date));

		    $db->setQuery($query);
		    $db->execute();

		    $query = 'REPLACE INTO #__settings (variable, value) VALUES (\'latestMember\', ' . $user->ID_MEMBER . '), (\'latestRealName\', ' . $db->quote($userinfo->name) . ')';
		    $db->setQuery($query);
		    $db->execute();

		    //return the good news
		    return $this->getUser($userinfo);
	    }
    }

	/**
	 * Function That find the correct user group index
	 *
	 * @param Userinfo $userinfo
	 *
	 * @return int
	 */
	function getUserGroupIndex(Userinfo $userinfo)
	{
		$index = 0;

		$master = Framework::getMaster();
		if ($master) {
			$mastergroups = Framework::getUserGroups($master->name);

			$groups = array();
			if ($userinfo) {
				if (isset($userinfo->groups)) {
					$groups = $userinfo->groups;
				} elseif (isset($userinfo->group_id)) {
					$groups[] = $userinfo->group_id;
				}
			}

			foreach ($mastergroups as $key => $mastergroup) {
				if ($mastergroup) {
					$found = true;
					//check to see if the default groups are different
					if ($mastergroup->defaultgroup != $userinfo->group_id) {
						$found = false;
					} else {
						if ($this->params->get('compare_postgroup', false) && $mastergroup->postgroup != $userinfo->postgroup) {
							//check to see if the display groups are different
							$found = false;
						} else {
							if ($this->params->get('compare_membergroups', true) && isset($mastergroup->membergroups)) {
								//check to see if member groups are different
								if (count($userinfo->groups) != count($mastergroup->membergroups)) {
									$found = false;
									break;
								} else {
									foreach ($mastergroup->membergroups as $gid) {
										if (!in_array($gid, $userinfo->groups)) {
											$found = false;
											break;
										}
									}
								}
							}
						}
					}
					if ($found) {
						$index = $key;
						break;
					}
				}
			}
		}
		return $index;
	}
}
