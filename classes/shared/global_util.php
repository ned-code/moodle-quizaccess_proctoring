<?php
/**
 * @package    quizaccess_proctoring
 * @subpackage shared
 * @category   NED
 * @copyright  2021 NED {@link http://ned.ca}
 * @author     NED {@link http://ned.ca}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @noinspection DuplicatedCode
 */

namespace quizaccess_proctoring\shared;

defined('MOODLE_INTERNAL') || die();

/**
 * Trait global_util
 *
 * Use only with classes, which has static protected property $_global_data (which should not conflict with other traits)
 * Originally created for the base_class
 *
 * To avoid trait conflicts, don't use any others traits/classes/etc here
 *  - this trait only for using *by* something, not for depending on anything else.
 *
 * @package quizaccess_proctoring\shared
 */
trait global_util {
    // DON'T uncomment next line: this property should has child class, and it shouldn't be declare in the trait!
    // static protected $_global_data = [];

    /**
     * @param string    $function
     * @param array     $add_keys
     *
     * @return array
     */
    static protected function _g_get_g_keys($function, $add_keys=[]){
        $keys = [$function];
        if (!empty($add_keys)){
            if(!is_array($add_keys)){
                $add_keys = [$add_keys ?? 0];
            }

            $keys = array_merge($keys, $add_keys);
        }

        return $keys;
    }

    /**
     * Get/Save value from/in NED global data
     *
     * @param string $function
     * @param array  $add_keys
     * @param mixed  $def_value - if none value, get this; if $set_value is true - save it
     * @param bool   $set_value - if true, saved new values
     * @param bool   $clone     - if true, return clone for objects
     *
     * @return array|mixed|null
     */
    static protected function g($function, $add_keys=[], $def_value=null, $set_value=false, $clone=false){
        $obj = &static::$_global_data;
        $keys = static::_g_get_g_keys($function, $add_keys);
        foreach ($keys as $key){
            if (!isset($obj[$key])){
                if (!$set_value){
                    return $def_value;
                }
                $obj[$key] = [];
            }
            $obj = &$obj[$key];
        }

        if ($set_value){
            $obj = $def_value;
        }

        if ($clone){
            return is_object($obj) ? clone($obj) : $obj;
        }

        return $obj;
    }

    /**
     * Get value form the NED global data
     *
     * @param       $function
     * @param array $add_keys
     * @param null  $def_value
     *
     * @return array|mixed|null
     */
    static protected function g_get($function, $add_keys=[], $def_value=null){
        return static::g($function, $add_keys, $def_value, false);
    }

    /**
     * Save value in the NED global data
     * Doesn't save null by default, set $save_null in true for this
     *
     * @param       $function
     * @param array $add_keys
     * @param mixed $value
     * @param bool  $save_null
     *
     * @return array|mixed|null
     */
    static protected function g_set($function, $add_keys=[], $value=null, $save_null=false){
        if (is_null($value) && !$save_null){
            $value = false;
        }
        return static::g($function, $add_keys, $value, true);
    }

    /**
     * Get value form the NED global data as independent clone
     * It make difference only for objects
     *
     * @param       $function
     * @param array $add_keys
     * @param null  $def_value
     *
     * @return array|mixed|null
     */
    static protected function g_get_clone($function, $add_keys=[], $def_value=null){
        return static::g($function, $add_keys, $def_value, false, true);
    }

    /**
     * Check, that there is isset some value in the NED global data by the current keys
     *
     * @param string $function
     * @param array  $add_keys
     *
     * @return bool - true, if there is such object, false otherwise
     */
    static protected function g_isset($function, $add_keys=[]){
        $obj = &static::$_global_data;
        $keys = static::_g_get_g_keys($function, $add_keys);
        foreach ($keys as $key){
            if (!isset($obj[$key])){
                return false;
            }

            $obj = &$obj[$key];
        }

        return true;
    }

    /**
     * Remove value from the NED global data
     *
     * @param string $function
     * @param array  $add_keys
     *
     * @return bool - true, if there was such object, false otherwise
     */
    static protected function g_remove($function, $add_keys=[]){
        $obj = &static::$_global_data;
        $keys = static::_g_get_g_keys($function, $add_keys);
        $count = count($keys);
        $i = 1;
        foreach ($keys as $key){
            if (!isset($obj[$key])){
                return false;
            } elseif ($i == $count){
                // using unset($obj) after "for" - working in the wrong way in our case
                unset($obj[$key]);
                return true;
            }

            $obj = &$obj[$key];
            $i++;
        }

        unset($obj);
        return true;
    }

    /**
     * Remove all function data from the NED global data
     *
     * WARNING: NED global data are used by several NED plugins - be careful with purging it without real necessary
     *
     * @param string|string[] $functions - name of one or list functions,
     *                                which data should be removed from the global data
     *
     * @return void
     */
    static protected function g_remove_functions_data($functions){
        $functions = is_array($functions) ? $functions : [$functions];
        foreach ($functions as $function){
            unset(static::$_global_data[$function]);
        }
    }

    /**
     * Remove data from the NED global data
     *
     * WARNING: NED global data are used by several NED plugins - be careful with purging it without real necessary
     *
     * @return void
     */
    static public function g_remove_all_ned_data(){
        static::$_global_data = [];
    }
}
