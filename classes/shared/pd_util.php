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
 * Trait pd_util
 *  - simple utils, but which need plugin_dependencies
 *
 * @package quizaccess_proctoring\shared
 */
trait pd_util {
    use plugin_dependencies, util;

    /**
     * Get menu from records (or other list objects)
     *
     * @param object[]      $records
     * @param string|array  $secondname - (Optional)Name of value parameters, or list with them
     * @param string        $firstname  - (Optional)Name of key parameter, id by default
     * @param string        $separator  - (Optional)Separator, to join $secondnames, if $secondname is list
     * @param string        $plugin     - (Optional)Name of plugin, which should translate string data
     *
     * @return array - menu with $firstname data as key, and $secondname data as values
     */
    static public function records2menu($records=[], $secondname='name', $firstname='id', $separator=' ', $plugin=null){
        $menu = [];
        $plugin = $plugin ?? static::$PLUGIN_NAME;
        if (empty($records) || empty($secondname) || empty($firstname)){
            return $menu;
        }

        if (is_array($secondname)){
            if (count($secondname) == 1){
                $secondname = reset($secondname);
            }
        }

        foreach ($records as $key => $record){
            if (is_string($record)){
                $menu[$key] = static::str_check($record, null, null, $plugin);
                continue;
            }

            if (!isset($record->$firstname)){
                continue;
            }

            if (is_array($secondname)){
                $val = [];
                foreach ($secondname as $name){
                    if (!isset($record->$name)){
                        continue;
                    }
                    $val = $record->$name;
                }
                $menu[$record->$firstname] = join($separator, $val);
            } else {
                $menu[$record->$firstname] = $record->$secondname ?? null;
            }
        }

        return $menu;
    }

    /**
     * Get menu from the list with language identifiers
     *
     * @param string[] $strings
     * @param bool     $use_string_as_key - (Optional) Use current string value - as keys for future menu
     * @param string   $surround          - (Optional) String to add before and after string value
     * @param array    $additional_data   - (Optional) Some data which need to add to string value in ()
     * @param string   $plugin            - (Optional) Name of plugin, which should translate string data
     *
     * @return array - menu with translated strings
     */
    static public function strings2menu($strings=[], $use_string_as_key=false,  $surround='', $additional_data=[], $plugin=null){
        $menu = [];
        if (empty($strings)){
            return $menu;
        }

        $plugin = $plugin ?? static::$PLUGIN_NAME;
        foreach ($strings as $key => $string){
            $add = isset($additional_data[$key]) ? '('.$additional_data[$key].')' : '';
            $val = $surround . static::str($string, null, $plugin) . $add . $surround;
            $menu[$use_string_as_key ? $string : $key] = $val;
        }

        return $menu;
    }
}
