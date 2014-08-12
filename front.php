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

/**
 * JFusion Front Class for SMF 1.1.x
 * For detailed descriptions on these functions please check Plugin_Front
 *
 * @category   Plugins
 * @package    JFusion\Plugins
 * @subpackage smf
 * @author     JFusion Team <webmaster@jfusion.org>
 * @copyright  2008 JFusion. All rights reserved.
 * @license    http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @link       http://www.jfusion.org
 */
class Front extends \JFusion\Plugin\Front
{
    /**
     * Get registration url
     *
     * @return string url
     */
    function getRegistrationURL()
    {
        return 'index.php?action=register';
    }

    /**
     * Get lost password url
     *
     * @return string url
     */
    function getLostPasswordURL()
    {
        return 'index.php?action=reminder';
    }

    /**
     * Get url for lost user name
     *
     * @return string url
     */
    function getLostUsernameURL()
    {
        return 'index.php?action=reminder';
    }
}