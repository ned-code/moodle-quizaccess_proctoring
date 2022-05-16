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
 * Trait util
 *
 * To avoid recursive dependencies, don't use any others traits/classes/etc here
 *  - this trait only for using *by* something, not for depending on anything else.
 *
 * @package quizaccess_proctoring\shared
 */
trait util {
    /**
     * Return first true argument, or false (if none true arguments)
     *
     * @param mixed ...$args
     *
     * @return false|mixed
     */
    static public function any(...$args){
        foreach ($args as $arg){
            if ($arg){
                return $arg;
            }
        }

        return false;
    }

    /**
     * Return true, if all arguments are true
     *
     * @param mixed ...$args
     *
     * @return bool
     */
    static public function all(...$args){
        foreach ($args as $arg){
            if (!$arg){
                return false;
            }
        }

        return true;
    }

    /**
     * Return first object which has 'id' param
     *
     * @param mixed ...$args
     *
     * @return \stdClass|null
     */
    static public function choose_obj_with_id(...$args){
        foreach ($args as $arg){
            if ($arg && isset($arg->id)){
                return clone($arg);
            }
        }

        return null;
    }

    /**
     * Return received id, if it's numeric, or object id, if received object, or zero(0) if object hasn't key "id"
     *
     * @param object|numeric $obj
     *
     * @return int
     */
    static public function get_id($obj){
        if (is_numeric($obj)){
            $id = $obj;
        } else {
            $id = $obj->id ?? 0;
        }

        return (int)$id;
    }

    /**
     * Multi get_id
     * @see get_id()
     *
     * @param array|object[]|numeric[] ...$args
     *
     * @return array|int[]
     */
    static public function get_ids(...$args){
        $res = [];
        foreach ($args as $arg){
            $res[] = static::get_id($arg);
        }
        return $res;
    }

    /**
     * @param object $obj
     * @param array  $list
     * @param bool   $import_all
     */
    static public function import_array_to_object($obj, $list, $import_all=false){
        if (empty($list)) return;

        $list = (array)$list;
        foreach ($list as $key => $new_val){
            if (isset($obj->$key)){
                $val = $obj->$key;
                if (is_bool($val)){
                    $new_val = (bool)$new_val;
                } elseif (is_int($val)){
                    $new_val = (int)$new_val;
                }
            } elseif (!$import_all){
                continue;
            }

            $obj->$key = $new_val;
        }
    }

    /**
     * Join array to str, or return "it", if it's string
     * Used by any 'class' html parameters
     *
     * @param string|array $list
     * @param string       $add - add something to final string with $separator
     * @param string       $separator - space (' ') by default
     *
     * @return string
     */
    static public function arr2str($list, $add='', $separator=' '){
        if (empty($list) && !is_numeric($list)) return $add;

        if (is_array($list)){
            $res = implode($separator, $list);
        } elseif (is_scalar($list)) {
            $res = strval($list);
        } else {
            static::debugging('Wrong $list type for arr2str() function');
            $res = '';
        }

        if ((!empty($add) || is_numeric($add)) && is_scalar($add)){
            if (!empty($res) || is_numeric($res)){
                $res .= $separator;
            }
            $res .= $add;
        }

        return $res;
    }

    /**
     * Explode str to array, or return "it", if it's array
     * Used by any 'class' html parameters
     *
     * @param string|array $str
     * @param array  $add       - add something to final array
     * @param string $separator - space (' ') by default
     *
     * @return array
     */
    static public function str2arr($str, $add=[], $separator=' '){
        if (!is_array($add)) {
            $add = [$add];
        }
        if (empty($str) && !is_numeric($str)) return $add;

        if (is_string($str)){
            $res = explode($separator, $str);
        } elseif (is_object($str)){
            $res = (array)$str;
        } else {
            debugging('Wrong $str type for str2arr() function');
            $res = [];
        }

        if (!empty($add)){
            $res = array_merge($res, $add);
        }

        return $res;
    }

    /**
     * @param array|object  $obj
     * @param array|mixed   $keys
     * @param mixed         $def
     *
     * @return mixed|null
     */
    static public function isset2($obj, $keys=[], $def=null){
        if (empty($obj)){
            return $def;
        } elseif (is_object($obj)){
            $obj = (array)$obj;
        } elseif(!is_array($obj)){
            return $def;
        }

        $keys = is_array($keys) ? $keys : (array)$keys;
        foreach ($keys as $key){
            if (!isset($obj[$key])){
                return $def;
            }
            $obj = $obj[$key];
        }

        return $obj;
    }

    /**
     * Return $key, it exists in $obj, $def otherwise
     *  if $def not set, return first key from $obj
     *  use $return_null if wish to get null as $def value
     *
     * @param      $obj
     * @param      $key
     * @param null $def
     * @param bool $return_null
     *
     * @return int|string|null
     */
    static public function isset_key($obj, $key, $def=null, $return_null=false){
        if (is_object($obj)){
            $obj = (array)$obj;
        } elseif(!is_array($obj)){
            return $def;
        }
        if (empty($obj)){
            return $def;
        }
        reset($obj);
        $def = (is_null($def) && !$return_null) ? key($obj) : $def;
        $key = isset($obj[$key]) ? $key : $def;
        return $key;
    }

    /**
     * Return $val, it exists in $list, $def otherwise
     *  if $def not set, return first val from $list
     *  use $return_null if wish to get null as $def value
     *
     * @param array $list
     * @param       $val
     * @param null  $def
     * @param bool  $return_null
     *
     * @return mixed|null
     */
    static public function isset_in_list($list, $val, $def=null, $return_null=false){
        if(!is_array($list) || empty($list)){
            return $def;
        }
        if (in_array($val, $list)){
            return $val;
        } elseif (is_null($def)){
            if ($return_null){
                return null;
            } else {
                return reset($list);
            }
        } else {
            return $def;
        }
    }

    /**
     * @param string $haystack
     * @param string|array $needle - if array, return true if at least one is true
     * @param int    $offset
     * @param bool   $case_sensitive true by default
     *
     * @return bool
     */
    static public function str_has($haystack, $needle, $offset=0, $case_sensitive=true){
        if (is_string($needle)){
            if ($case_sensitive){
                return strpos($haystack, $needle, $offset) !== false;
            } else {
                return stripos($haystack, $needle, $offset) !== false;
            }
        } elseif (is_array($needle)){
            foreach ($needle as $item){
                if (static::str_has($haystack, $item, $offset, $case_sensitive)){
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param string        $haystack
     * @param string|array  $needle
     * @param bool          $case_sensitive true by default
     *
     * @return bool
     */
    static public function str_starts_with($haystack, $needle, $case_sensitive=true){
        if (empty($needle)) return true;

        if (is_string($needle)){
            $needle_len = strlen($needle);
            if (strlen($haystack) < $needle_len) return false;

            $check_str = substr($haystack, 0, $needle_len);
            if ($case_sensitive){
                return strpos($check_str, $needle) === 0;
            } else {
                return stripos($check_str, $needle) === 0;
            }
        } elseif (is_array($needle)){
            foreach ($needle as $item){
                if (static::str_starts_with($haystack, $item, $case_sensitive)){
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * @param string        $haystack
     * @param string|array  $needle
     * @param bool          $case_sensitive true by default
     *
     * @return bool
     */
    static public function str_ends_with($haystack, $needle, $case_sensitive=true){
        if (empty($needle)) return true;

        if (is_string($needle)){
            $needle_len = strlen($needle);
            if (strlen($haystack) < $needle_len) return false;

            $check_str = substr($haystack, -$needle_len);
            if ($case_sensitive){
                return strpos($check_str, $needle) === 0;
            } else {
                return stripos($check_str, $needle) === 0;
            }
        } elseif (is_array($needle)){
            foreach ($needle as $item){
                if (static::str_ends_with($haystack, $item, $case_sensitive)){
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Add prefix to string, if it hasn't yet
     * Return true, if it hasn't, false otherwise
     *
     * @param string $string
     * @param string $prefix
     * @param bool   $case_sensitive true by default
     *
     * @return bool
     */
    static public function str_add_prefix(&$string, $prefix, $case_sensitive=true){
        if (!static::str_starts_with($string, $prefix, $case_sensitive)){
            $string = $prefix.$string;
            return true;
        }
        return false;
    }

    /**
     * Remove prefix to string, if it has
     * Return true, if it has, false otherwise
     *
     * @param string $string
     * @param string $prefix
     * @param bool   $case_sensitive true by default
     *
     * @return bool
     */
    static public function str_rem_prefix(&$string, $prefix, $case_sensitive=true){
        if (static::str_starts_with($string, $prefix, $case_sensitive)){
            $string = substr($string, strlen($prefix));
            return true;
        }
        return false;
    }

    /**
     * Turn value into array, if it not array
     *
     * @param array|mixed $value
     * @param bool        $empty2empty - true, by default
     *
     * @return array
     */
    static public function val2arr($value, $empty2empty=true){
        if ($empty2empty && empty($value)){
            $value = [];
        } elseif (!is_array($value)){
            $value = [$value];
        }
        return $value;
    }

    /**
     * Use val2arr for many values
     * @see val2arr
     *
     * @param bool        $empty2empty
     * @param array       $values
     *
     * @return array
     */
    static public function val2arr_multi($empty2empty=true, ...$values){
        $res = [];
        foreach ($values as $value){
            $res[] = static::val2arr($value, $empty2empty);
        }

        return $res;
    }

    /**
     * Turn value into object, if it not object
     *
     * @param object|array $value
     * @param bool         $empty2empty - if true, return null when empty($value), otherwise return empty object
     *
     * @return object|\stdClass|null
     */
    static public function val2obj($value, $empty2empty=false){
        if (empty($value)){
            if ($empty2empty){
                $value = null;
            } else {
                $value = new \stdClass();
            }
        } elseif (!is_object($value)){
            $value = (object)$value;
        }
        return $value;
    }

    /**
     * Use val2obj for many values
     * @see val2obj
     *
     * @param bool        $empty2empty
     * @param array       $values
     *
     * @return array
     */
    static public function val2obj_multi($empty2empty=false, ...$values){
        $res = [];
        foreach ($values as $value){
            $res[] = static::val2obj($value, $empty2empty);
        }

        return $res;
    }

    /**
     * Transform all arguments to int
     * @param array $values
     *
     * @return int[]
     */
    static public function val2int_multi(...$values){
        $res = [];
        foreach ($values as $value){
            $res[] = (int)$value;
        }

        return $res;
    }

    /**
     * Pack list in new array by $keys
     *
     * @param object[]|array[]|array $list
     * @param array                  $keys
     * @param bool                   $list_with_arrays - set true, if list with arrays, set false if with objects
     * @param int|string             $def_val          - default key, if key from $keys wasn't found in the item
     * @param array                  $def_key_values   - list with default values by $keys
     *
     * @return array|mixed
     */
    static public function pack_in_array($list, $keys, $list_with_arrays=false, $def_val=0, $def_key_values=[]){
        if (empty($list) || empty($keys)){
            return [];
        }

        $res = [];
        $list = static::val2arr($list);
        foreach ($keys as $key){
            $def_key_values[$key] = $def_key_values[$key] ?? $def_val;
        }
        foreach ($list as $item){
            $obj = &$res;
            foreach ($keys as $i => $key){
                $key_item = $list_with_arrays ? ($item[$key] ?? $def_key_values[$key]) : ($item->$key ?? $def_key_values[$key]);
                if (!isset($obj[$key_item])){
                    $obj[$key_item] = [];
                }
                $obj = &$obj[$key_item];
            }
            $obj = $item;
        }

        return $res;
    }

    /**
     * @param null|int|string $timestamp - if null, set to 'now'
     * @param null|int|string $timezone  - if null - won't set it;
     *
     * @return \DateTime
     */
    static public function get_datetime_by_timestamp($timestamp=null, $timezone=null){
        $dt = new \DateTime();
        if (!is_null($timestamp)){
            $dt->setTimestamp((int)$timestamp);
        }
        if ($timezone){
            $dt->setTimezone(new \DateTimeZone($timezone));
        }

        return $dt;
    }

    /**
     * Check current time for set hour and/or minute.
     * Null values will be not checked
     *
     * @param numeric|null    $check_hour
     * @param numeric|null    $check_minute
     * @param null|int|string $timestamp - if null, set to 'now'
     * @param null|int|string $timezone  - if null - won't set it
     *
     * @return bool
     */
    static public function check_time_value($check_hour=null, $check_minute=null, $timestamp=null, $timezone=null){
        if (is_null($check_hour) && is_null($check_minute)){
            debugging('You should set for checking hour or minute!');
            return false;
        }

        $date = static::get_datetime_by_timestamp($timestamp, $timezone);

        /**
         * @var array $check
         *
         * For string keys see:
         *  @link https://php.net/manual/en/datetime.format.php
         */
        $check = [
            'G' => $check_hour,
            'i' => $check_minute,
        ];

        foreach ($check as $key => $value){
            if (is_null($value)) continue;

            $value = (int)$value;
            $now_v = (int)$date->format($key);
            if ($value !== $now_v){
                return false;
            }
        }

        return true;
    }

    /**
     * Return start & end of day by $time (all in timestamps)
     * By default, uses NED timezone
     *
     * @param int|string $timestamp
     * @param int|string $timezone  - if null - won't set it; by default use NED timezone
     *
     * @return array($start, $end)
     */
    static public function get_day_start_end($timestamp, $timezone=null){
        $dt = static::get_datetime_by_timestamp($timestamp ?? 0, $timezone);
        $dt->setTime(0, 0, 0, 0);
        $start = $dt->getTimestamp();
        $end = $start + DAYSECS - 1;
        return [$start, $end];
    }

    /**
     * Return difference between time1 & time2 as "hours:min"
     *
     * @param int|string      $timestamp1
     * @param int|string|null $timestamp2 - if null, uses 'now'
     *
     * @return string
     */
    static public function time_diff_to_h_m($timestamp1, $timestamp2=null){
        $timestamp2 = $timestamp2 ?? time();

        $diff = abs($timestamp2 - $timestamp1);
        $h = floor($diff/HOURSECS);
        $diff -= $h*HOURSECS;
        $m = floor($diff/MINSECS);
        $m = ($m < 10) ? '0'.$m : $m;

        return "$h:$m";
    }

    /**
     * Return difference between time1 & time2 as max string value: 5s, 6m, 8h, 10d, 12w, 125y
     *
     * @param int|string      $timestamp1
     * @param int|string|null $timestamp2 - if null, uses 'now'
     * @param int             $count - count of time variables in result
     * @param bool            $fullname - use full name for time string names
     * @param string          $separator - separator to join result string
     * @param string          $ifnull - what return, if difference is null (zero)
     *
     * @return string
     */
    static public function time_diff_to_str_max($timestamp1, $timestamp2=null, $count=1, $fullname=false, $separator=' ', $ifnull='0'){
        $res = [];
        $timestamp2 = $timestamp2 ?? time();
        $diff = abs($timestamp2 - $timestamp1);
        if ($diff == 0){
            return $ifnull;
        }

        $delays = ['year' => YEARSECS, 'week' => WEEKSECS, 'day' => DAYSECS, 'hour' => HOURSECS, 'minute' => MINSECS, 'second' => 1];
        foreach ($delays as $name => $delay){
            if ($diff >= $delay){
                $val = floor($diff/$delay);

                $str_val = $val;
                if ($fullname){
                    $str_val .= ' ';
                    if ($val == 1){
                        $str_val .= $name;
                    } else {
                        $str_val .= $name.'s';
                    }
                } else {
                    $str_val .= $name[0];
                }
                $res[] = $str_val;

                $count--;
                if ($count < 1){
                    break;
                }

                $diff -= $val*$delay;
            }
        }

        return join($separator, $res);
    }

    /**
     * Add $val to the list with free key
     *
     * @param array $list
     * @param mixed $val
     * @param bool  $at_start - if true, key will be decrease, otherwise it will be increase
     * @param bool  $add_if_empty - if false, will not add element it the empty $list
     * @param int   $key_try - first key to check, 0 by default
     *
     * @return array - new $list
     */
    static public function add2list($list, $val, $at_start=true, $add_if_empty=true, $key_try=null){
        if (empty($list)){
            if (!$add_if_empty){
                return [];
            }
        }

        $list = static::val2arr($list);
        $key = $key_try ?? 0;
        while (isset($list[$key])){
            $key += $at_start ? -1 : 1;
        }

        if ($at_start){
            $list = [$key => $val] + $list;
        } else {
            $list[$key] = $val;
        }

        return $list;
    }

    /**
     * Filter course-modules by its modnames (e.g. ['forum', 'quiz'])
     *
     * @param \cm_info[]|object[]   $cms
     * @param string[]|string       $modnames
     *
     * @return array|\cm_info[]|object[]
     */
    static public function filter_cms_by_modnames($cms, $modnames=[]){
        if (empty($cms) || empty($modnames)){
            return $cms;
        }

        $modnames = static::val2arr($modnames);
        $checked_cms = [];
        foreach ($cms as $cm){
            if (in_array($cm->modname, $modnames)){
                $checked_cms[$cm->id] = $cm;
            }
        }

        return $checked_cms;
    }

    /**
     * Get menu from cm records
     *
     * @param array|\cm_info[] $cms
     * @param string[]|string  $filter_modnames - Optional array or single value to check cm modname
     *
     * @return array - array [cmid => activity_name]
     */
    static public function cms2menu($cms, $filter_modnames=[]){
        $menu = [];
        if (empty($cms)){
            return $menu;
        }

        $filter_modnames = static::val2arr($filter_modnames);
        foreach ($cms as $cm){
            if (!empty($filter_modnames) && !in_array($cm->modname, $filter_modnames)) continue;

            $activity_name = strip_tags($cm->get_formatted_name());
            if (!$cm->visible) {
                $activity_name .= " (hidden)";
            }
            $menu[$cm->id] = $activity_name;
        }

        return $menu;
    }

    /**
     * Get menu from user records
     *
     * @param array|object[] $users
     *
     * @return array - array [cmid => modname]
     */
    static public function users2menu($users){
        $menu = [];
        if (empty($users)){
            return $menu;
        }

        foreach ($users as $user){
            $menu[$user->id] = fullname($user);
        }

        return $menu;
    }

    /**
     * It will remove all values, which are the same in string representation
     *
     * @param array         $array
     * @param array|\mixed  $values - value or values, which should be removed
     * @param bool          $recalculate - if true, return new array list
     *
     * @return array
     */
    static public function array_remove_by_values($array, $values, $recalculate=false){
        $values = static::val2arr($values);
        $res = array_diff($array, $values);
        if ($recalculate){
            $res = array_values($res);
        }

        return $res;
    }

    /**
     * It will remove all keys, which are the same in string representation
     * If you would like remove only one exactly key, it will be better to use unset()
     *
     * @param array         $array
     * @param array|\mixed  $keys - key or keys, which should be removed
     * @param bool          $recalculate - if true, return new array list
     *
     * @return array
     */
    static public function array_remove_by_keys($array, $keys, $recalculate=false){
        $keys = static::val2arr($keys);
        $rem = array_fill_keys($keys, true);
        $res = array_diff_key($array, $rem);
        if ($recalculate){
            $res = array_values($res);
        }

        return $res;
    }

    /**
     * Find highest value
     *
     * @param array ...$args
     *
     * @return mixed|null
     */
    static public function max(...$args){
        if (empty($args)){
            return null;
        }
        if (count($args) == 1){
            $res = reset($args);
            if (is_array($res)){
                return static::max(...$res);
            }

            return $res;
        }

        return max(...$args);
    }

    /**
     * Find lowest value
     *
     * @param array ...$args
     *
     * @return mixed|null
     */
    static public function min(...$args){
        if (empty($args)){
            return null;
        }
        if (count($args) == 1){
            $res = reset($args);
            if (is_array($res)){
                return static::min(...$res);
            }

            return $res;
        }

        return min(...$args);
    }

    /**
     * Returns the amount of memory allocated to PHP
     * @link https://php.net/manual/en/function.memory-get-usage.php
     *
     * @param bool  $real_usage - (optional) Set this to true to get the real size of memory allocated from
     *                              system. If not set or false only the memory used by emalloc() is reported.
     * @param int   $precision - (optional) The optional number of decimal digits to round to.
     *
     * @return float - the memory amount in megabytes.
     */
    static public function memory_get_usage($real_usage=false, $precision=2){
        return round(memory_get_usage($real_usage)/1000000, $precision);
    }

    /**
     * Returns the peak of memory allocated by PHP
     * @link https://php.net/manual/en/function.memory-get-peak-usage.php
     *
     * @param bool  $real_usage - (optional) Set this to true to get the real size of memory allocated from
     *                              system. If not set or false only the memory used by emalloc() is reported.
     * @param int   $precision - (optional) The optional number of decimal digits to round to.
     *
     * @return float - the memory amount in megabytes.
     */
    static public function memory_get_peak_usage($real_usage=false, $precision=2){
        return round(memory_get_peak_usage($real_usage)/1000000, $precision);
    }

    /**
     * Helper method to get Yes/No list
     *
     * @return array
     */
    static public function get_yesno_list() {
        return [get_string('no'), get_string('yes')];
    }

    /**
     * Helper method to return Yes/No string
     *
     * @param bool|mixed $value
     *
     * @return string
     */
    static public function get_yesno($value) {
        return $value ? get_string('yes') : get_string('no');
    }

    /**
     * Quote regular expression list characters with the preg_quote
     * @link https://php.net/manual/en/function.preg-quote.php
     *
     * @param array|string  $list - input list of strings
     * @param bool          $join - if true, return regexp string group join by '|'
     * @param string        $delimiter - [optional] If the optional delimiter is specified, it
     *      will also be escaped. This is useful for escaping the delimiter
     *      that is required by the PCRE functions. The / is the most commonly
     *      used delimiter.
     *
     * @return array|string - if not $join, return array with the quoted (escaped) strings,
     *                      otherwise return the quoted (escaped) string as regexp string group join by '|'
     */
    static public function preg_quote_list($list, $join=false, $delimiter=null){
        if (empty($list)){
            return $join ? '' : [];
        }

        $res = [];
        $list = static::val2arr($list);
        foreach ($list as $key => $item){
            $res[$key] = preg_quote($item, $delimiter);
        }

        if ($join){
            return '('.join('|', $res).')';
        }

        return $res;
    }

    /**
     * Make id key from the args, all non-string and non-numbers transforms to '0' and '1'
     *
     * @param mixed ...$args
     *
     * @return string
     */
    static public function make_key(...$args){
        $keys = [];
        foreach ($args as $arg){
            if (is_string($arg) || is_numeric($arg)){
                $keys[] = $arg;
            } else {
                $keys[] = $arg ? '1' : '0';
            }
        }

        return empty($keys) ? '0' : join('_', $keys);
    }

    /**
     * Transform single value ot string key
     *
     * @param array|string|numeric|mixed $val
     *
     * @return string
     */
    static public function val2key($val){
        if (is_array($val)){
            return static::make_key(...$val);
        } if (is_string($val) || is_numeric($val)){
            return (string)$val;
        } else {
            return $val ? '1' : '0';
        }
    }

    /**
     * Return average of array data
     *
     * @param array $list
     *
     * @return float|int|null
     */
    static public function array_avg($list){
        if (empty($list)) return null;

        return array_sum($list) / count($list);
    }

    /**
     * Return, whether or not the current page is being served over HTTPS
     *
     * @return bool
     */
    public static function is_secure(){
        static $_data = null;

        if (is_null($_data)){
            global $_SERVER;
            $https = $_SERVER['HTTPS'] ?? $_SERVER['REQUEST_SCHEME'] ?? $_SERVER['HTTP_X_FORWARDED_PROTO'] ?? null;
            $_data = (bool)($https && (strcasecmp('on', $https) == 0 || strcasecmp('https', $https) == 0));
        }

        return $_data;
    }
}
