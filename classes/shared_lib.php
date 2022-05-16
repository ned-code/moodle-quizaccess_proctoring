<?php
/**
 * @package    quizaccess_proctoring
 * @category   NED
 * @copyright  2021 NED {@link http://ned.ca}
 * @author     NED {@link http://ned.ca}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quizaccess_proctoring;

defined('MOODLE_INTERNAL') || die();

/**
 * Class shared_lib
 *
 * @package quizaccess_proctoring
 */
class shared_lib extends \quizaccess_proctoring\shared\base_class {
    use \quizaccess_proctoring\shared\base_trait;

    const PLUGIN_NAME = 'quizaccess_proctoring';

    const CLASS_CONTAINER = self::PLUGIN_NAME.'-container';
    const CLASS_ROW = self::PLUGIN_NAME.'-row';
    const CLASS_COL = self::PLUGIN_NAME.'-col';

    /**
     * Return camshotdelay config
     *
     * @return int
     */
    public static function cfg_camshotdelay(){
        return (int)(static::get_config('autoreconfigurecamshotdelay') ?: 30) * 1000;
    }

    /**
     * Return imagewidth config
     *
     * @return int
     */
    public static function cfg_imagewidth(){
        return (int)(static::get_config('autoreconfigureimagewidth') ?: 230);
    }

    /**
     * Return enablescreenshare config
     *
     * @return int
     */
    public static function cfg_enablescreenshare(){
        return static::get_config('screenshareenablechk') ? 1 : 0;
    }

    /**
     * Return faceidcheck config
     *
     * @return int
     */
    public static function cfg_faceidcheck(){
        return static::get_config('fcheckstartchk') ? 1 : 0;
    }

    /**
     * Creates a special <div> content
     *
     * @param string|array  $content HTML content of tag
     * @param string|array  $main_class Optional CSS class (or classes as space-separated list)
     * @param string|array  $add_class Optional CSS class (or classes as space-separated list)
     * @param array|null    $attributes Optional other attributes as array
     *
     * @return string HTML code for div
     */
    protected static function _sp_div($content='', $main_class='', $add_class='', $attributes=null){
        $class = static::str2arr($main_class);
        if (!empty($add_class)){
            $class[] = static::arr2str($add_class);
        }
        return static::div($content, $class, $attributes);
    }

    /**
     * Create plugin div-container
     *
     * @param string|array  $content HTML content of tag
     * @param string|array  $add_class Optional add CSS class (or classes as space-separated list)
     * @param array|null    $attributes Optional other attributes as array
     *
     * @return string HTML code for div
     */
    public static function d_container($content='', $add_class=[], $attributes=null){
        return static::_sp_div($content, static::CLASS_CONTAINER, $add_class, $attributes);
    }

    /**
     * Create plugin div-row
     *
     * @param string|array  $content HTML content of tag
     * @param string|array  $add_class Optional add CSS class (or classes as space-separated list)
     * @param array|null    $attributes Optional other attributes as array
     *
     * @return string HTML code for div
     */
    public static function d_row($content='', $add_class=[], $attributes=null){
        return static::_sp_div($content, static::CLASS_ROW, $add_class, $attributes);
    }

    /**
     * Create plugin div-col
     *
     * @param string|array  $content HTML content of tag
     * @param string|array  $add_class Optional add CSS class (or classes as space-separated list)
     * @param array|null    $attributes Optional other attributes as array
     *
     * @return string HTML code for div
     */
    public static function d_col($content='', $add_class=[], $attributes=null){
        return static::_sp_div($content, static::CLASS_COL, $add_class, $attributes);
    }

    /**
     * Create plugin div-col inside div-row
     *
     * @param string|array  $content HTML content of tag
     * @param string|array  $add_class_row Optional add CSS class (or classes as space-separated list) to row
     * @param string|array  $add_class_col Optional add CSS class (or classes as space-separated list) to col
     * @param array|null    $attributes_row Optional other attributes as array to row
     * @param array|null    $attributes_col Optional other attributes as array to col
     *
     * @return string HTML code for div
     */
    public static function d_row_col($content='', $add_class_row=[], $add_class_col=[], $attributes_row=null, $attributes_col=null){
        return static::d_row(static::d_col($content, $add_class_col, $attributes_col), $add_class_row, $attributes_row);
    }
}

shared_lib::init();
