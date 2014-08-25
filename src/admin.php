<?php namespace JFusion\Plugins\smf;

/**
 * file containing administrator function for the jfusion plugin
 *
 * PHP version 5
 *
 * @category   JFusion
 * @package    JFusionPlugins
 * @subpackage SMF1
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

use JFusion\Application\Application;
use JFusion\Factory;
use JFusion\Framework;

use Joomla\Language\Text;

use Psr\Log\LogLevel;

use Exception;
use stdClass;

/**
 * JFusion Admin Class for SMF 1.1.x
 * For detailed descriptions on these functions please check Plugin_Admin
 *
 * @category   Plugins
 * @package    JFusion\Plugins
 * @subpackage smf
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */

class Admin extends \JFusion\Plugin\Admin
{
    /**
     * return table name
     *
     * @return string table name
     */
    function getTablename()
    {
        return 'members';
    }

    /**
     * setup plugin from path
     *
     * @param string $softwarePath Source path user to find config files
     *
     * @return array
     */
    function setupFromPath($softwarePath)
    {
	    $myfile = $softwarePath . 'Settings.php';

        $params = array();
        //try to open the file
	    $lines = $this->readFile($myfile);
        if ($lines === false) {
            Framework::raise(LogLevel::WARNING, Text::_('WIZARD_FAILURE') . ': ' . $myfile . ' ' . Text::_('WIZARD_MANUAL'), $this->getJname());
	        return false;
        } else {
            //parse the file line by line to get only the config variables
            $config = array();
	        foreach ($lines as $line) {
		        if (strpos($line, '$') === 0) {
			        $vars = explode('\'', $line);
			        if (isset($vars[1]) && isset($vars[0])) {
				        $name = trim($vars[0], ' $=');
				        $value = trim($vars[1], ' $=');
				        $config[$name] = $value;
			        }
		        }
	        }

            $params['database_host'] = isset($config['db_server']) ? $config['db_server'] : '';
            $params['database_type'] = 'mysql';
            $params['database_name'] = isset($config['db_name']) ? $config['db_name'] : '';
            $params['database_user'] = isset($config['db_user']) ? $config['db_user'] : '';
            $params['database_password'] = isset($config['db_passwd']) ? $config['db_passwd'] : '';
            $params['database_prefix'] = isset($config['db_prefix']) ? $config['db_prefix'] : '';
            $params['source_url'] = isset($config['boardurl']) ? $config['boardurl'] : '';
            $params['cookie_name'] = isset($config['cookiename']) ? $config['cookiename'] : '';
            $params['source_path'] = $softwarePath;
        }
        return $params;
    }

    /**
     * Returns the a list of users of the integrated software
     *
     * @param int $limitstart start at
     * @param int $limit number of results
     *
     * @return array
     */
    function getUserList($limitstart = 0, $limit = 0)
    {
	    try {
		    // initialise some objects
		    $db = Factory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('memberName as username, emailAddress as email')
			    ->from('#__members');

		    $db->setQuery($query, $limitstart, $limit);
		    $userlist = $db->loadObjectList();
	    } catch (Exception $e) {
		    Framework::raise(LogLevel::ERROR, $e, $this->getJname());
			$userlist = array();
	    }
        return $userlist;
    }

    /**
     * returns user count
     *
     * @return int user count
     */
    function getUserCount()
    {
	    try {
		    //getting the connection to the db
		    $db = Factory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('count(*)')
			    ->from('#__members');

		    $db->setQuery($query);
		    //getting the results
		    return $db->loadResult();
	    } catch (Exception $e) {
		    Framework::raise(LogLevel::ERROR, $e, $this->getJname());
		    return 0;
	    }
    }

    /**
     * get default user group list
     *
     * @return array array with object with default user group list
     */
    function getUsergroupList()
    {
	    //getting the connection to the db
	    $db = Factory::getDatabase($this->getJname());

	    $query = $db->getQuery(true)
		    ->select('ID_GROUP as id, groupName as name')
		    ->from('#__membergroups')
		    ->where('minPosts = -1');

	    $db->setQuery($query);
	    $usergrouplist = $db->loadObjectList();

	    //append the default usergroup
	    $default_group = new stdClass;
	    $default_group->id = 0;
	    $default_group->name = 'Default User';
	    $usergrouplist[] = $default_group;

        return $usergrouplist;
    }

    /**
     * get default user group
     *
     * @return array object with default user group
     */
    function getDefaultUsergroup()
    {
	    $usergroup = Framework::getUserGroups($this->getJname(), true);

	    $group = array();
	    if ($usergroup !== null) {
		    $db = Factory::getDatabase($this->getJname());

		    if (isset($usergroup->groups)) {
			    $groups = $usergroup->groups;
		    } else {
			    $groups = array();
		    }

		    $groups[] = $usergroup->defaultgroup;

		    foreach($groups as $g) {
			    if ($g != 0) {
				    //we want to output the usergroup name

				    $query = $db->getQuery(true)
					    ->select('groupName')
					    ->from('#__membergroups')
					    ->where('ID_GROUP = ' . (int)$g);

				    $db->setQuery($query);
				    $group[] = $db->loadResult();
			    } else {
				    $group[] = 'Default Usergroup';
			    }
		    }
	    }
	    return $group;
    }

    /**
     * return list of post groups
     *
     * @return object with default user group
     */
    function getUserpostgroupList()
    {
	    try {
		    //getting the connection to the db
		    $db = Factory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('ID_GROUP as id, groupName as name')
			    ->from('#__membergroups')
			    ->where('minPosts != -1');

		    $db->setQuery($query);
		    return $db->loadObjectList();
	    } catch (Exception $e) {
			Framework::raise(LogLevel::ERROR, $e, $this->getJname());
		    return array();
	    }
    }

    /**
     * function  return if user can register or not
     *
     * @return boolean true can register
     */
    function allowRegistration()
    {
	    $result = false;
	    try {
		    $db = Factory::getDatabase($this->getJname());

		    $query = $db->getQuery(true)
			    ->select('value')
			    ->from('#__settings')
			    ->where('variable = ' . $db->quote('registration_method'));

		    $db->setQuery($query);
		    $new_registration = $db->loadResult();
		    if ($new_registration != 3) {
			    $result = true;
		    }
	    } catch (Exception $e) {
		    Framework::raise(LogLevel::ERROR, $e, $this->getJname());
	    }
	    return $result;
    }

    /**
     * do plugin support multi usergroups
     *
     * @return bool
     */
    function isMultiGroup()
	{
		return false;
	}

    /**
     * do plugin support multi usergroups
     *
     * @return string UNKNOWN or JNO or JYES or ??
     */
    function requireFileAccess()
	{
		return 'JNO';
	}

	/**
	 * create the render group function
	 *
	 * @return string
	 */
	function getRenderGroup()
	{
		$jname = $this->getJname();

		Application::getInstance()->loadScriptLanguage(array('MAIN_USERGROUP', 'MEMBERGROUPS', 'POSTGROUP'));

		$postgroups = json_encode($this->getUserpostgroupList());

		$js = <<<JS
		if (typeof JFusion.postgroups === 'undefined') {
		    JFusion.postgroups = {};
		}
		JFusion.postgroups['{$jname}'] = {$postgroups};

		JFusion.renderPlugin['{$jname}'] = function(index, plugin, pair) {
			var usergroups = JFusion.usergroups[plugin.name];
			var postgroups = JFusion.postgroups[plugin.name];

			var div = new Element('div');

			// render default group
			div.appendChild(new Element('div', {'html': Joomla.JText._('MAIN_USERGROUP')}));

		    var defaultselect = new Element('select', {
		    	'name': 'usergroups['+plugin.name+']['+index+'][defaultgroup]',
		    	'id': 'usergroups_'+plugin.name+index+'defaultgroup'
		    });

		    Array.each(usergroups, function (group) {
			    var options = {'value': group.id,
					            'html': group.name};

		        if (pair && pair.defaultgroup && pair.defaultgroup == group.id) {
					options.selected = 'selected';
		        }

				defaultselect.appendChild(new Element('option', options));
		    });
		    div.appendChild(defaultselect);

		    // render default post groups
			div.appendChild(new Element('div', {'html': Joomla.JText._('POSTGROUP')}));

		    var postgroupsselect = new Element('select', {
		    	'name': 'usergroups['+plugin.name+']['+index+'][postgroup]',
		    	'id': 'usergroups_'+plugin.name+index+'postgroup'
		    });

		    Array.each(postgroups, function (group) {
			    var options = {'value': group.id,
					            'html': group.name};

	            if (pair && pair.postgroup && pair.postgroup == group.id) {
					options.selected = 'selected';
	            }

				postgroupsselect.appendChild(new Element('option', options));
		    });
		    div.appendChild(postgroupsselect);

			// render default member groups
			div.appendChild(new Element('div', {'html': Joomla.JText._('MEMBERGROUPS')}));

		    var membergroupsselect = new Element('select', {
		    	'name': 'usergroups['+plugin.name+']['+index+'][groups][]',
		    	'multiple': 'multiple',
		    	'id': 'usergroups_'+plugin.name+index+'groups'
		    });


		    Array.each(usergroups, function (group) {
			    if (group.id !== 0) {
					var options = {'value': group.id,
						            'html': group.name};

		            if (pair && pair.groups && pair.groups.contains(group.id)) {
						options.selected = 'selected';
		            }

					membergroupsselect.appendChild(new Element('option', options));
			    }
		    });
		    div.appendChild(membergroupsselect);
		    return div;
		};
JS;
		return $js;
	}
}
