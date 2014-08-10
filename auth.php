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

use JFusion\Plugin\Plugin_Auth;
use JFusion\User\Userinfo;

/**
 * JFusion Auth plugin class
 *
 * @category   Plugins
 * @package    JFusion\Plugins
 * @subpackage smf
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class Auth extends Plugin_Auth
{
    /**
     * Generate a encrypted password from clean password
     *
     * @param Userinfo $userinfo holds the user data
     *
     * @return string
     */
    function generateEncryptedPassword(Userinfo $userinfo)
    {
        $testcrypt = sha1(strtolower($userinfo->username) . $userinfo->password_clear);
        return $testcrypt;
    }
}
