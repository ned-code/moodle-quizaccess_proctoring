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
 * Trait db_util
 *
 * @package quizaccess_proctoring\shared
 */
trait db_util {
    use util;

    /**
     * @return \moodle_database|\mysqli_native_moodle_database|\readonlydriver
     */
    static public function db(){
        global $DB;
        return $DB;
    }

    /**
     * Remove all empty values from param array
     *
     * @param array $params
     *
     * @return array
     */
    static public function sql_filter_params($params){
        foreach ($params as $key => $value){
            if (empty($value)){
                unset($params[$key]);
            }
        }

        return $params;
    }


    /**
     * @param string|array $select
     * @param string|array $joins
     * @param string       $table_name
     * @param string       $table_alias
     * @param string|array $where
     * @param string|array $groupby
     * @param string|array $orderby
     * @param string|array $limit
     *
     * @return string - $sql query
     */
    static public function sql_generate($select=[], $joins=[], $table_name='', $table_alias='', $where=[], $groupby=[], $orderby=[], $limit=[]){
        list($select, $joins, $where, $groupby, $orderby, $limit) =
            self::val2arr_multi(true, $select, $joins, $where, $groupby, $orderby, $limit);

        $select = "SELECT ". (empty($select) ? "$table_alias.*" : join(', ', $select));
        $from = array_merge(["\nFROM {{$table_name}} AS $table_alias"], $joins);
        $from = join("\n", $from);
        $where = static::sql_where($where);
        $groupby = !empty($groupby) ? ("\nGROUP BY " . join(',', $groupby)) : '';
        $orderby = !empty($orderby) ? ("\nORDER BY " . join(',', $orderby)) : '';
        $limit = !empty($limit) ? ("\nLIMIT " . join(',', $limit)) : '';

        return $select.$from.$where.$groupby.$orderby.$limit;
    }

    /**
     * Transform array where condition to the string
     *
     * @param array  $where
     * @param string $condition -  AND or OR
     * @param bool   $without_word_where - if true, not including WHERE in result string
     *
     * @return string
     */
    static public function sql_where($where=[], $condition="AND", $without_word_where=false){
        $where = static::val2arr($where);
        $start = $without_word_where ? "\n((" : "\nWHERE ((";
        $where = !empty($where) ? ($start . join(") $condition (", $where) . '))') : '';
        return $where;
    }

    /**
     * Add constructs 'IN()' or '=' sql fragment for $where & $params
     *
     * @param        $cond_name - name of where condition
     * @param        $items     - items adding in where condition as params
     * @param array  $where     - where list, if you already has it
     * @param array  $params    - params array, if you already has it
     * @param string $prefix    - prefix for SQL parameters
     */
    static public function sql_add_get_in_or_equal_options($cond_name, $items, &$where=[], &$params=[], $prefix='param'){
        list($col_sql, $col_params) = static::db()->get_in_or_equal($items, SQL_PARAMS_NAMED, $prefix.'_');
        $where[] = "$cond_name $col_sql";
        $params = array_merge($params, $col_params);
    }

    /**
     * Constructs 'IN()' or '=' sql fragment for all options
     * Useful for DB->get_records_select()
     *
     * @param array  $options - options to transform in where and parameters
     * @param string $prefix  - prefix for SQL parameters
     * @param array|null  $columns - key filter for options, if not null
     * @param false  $return_where_as_array - return where as array, to continue work with it, otherwise it will be string
     * @param string $condition -  AND or OR for $where options
     * @param bool   $without_word_where - if true, not including WHERE in result $where string
     *
     * @return array($where, $params)
     */
    static public function sql_get_in_or_equal_options($options, $prefix='param', $columns=null,
        $return_where_as_array=false, $condition="AND", $without_word_where=true){
        if (empty($options)){
            return [$return_where_as_array ? [] : '', []];
        }
        $columns = $columns ?? array_keys($options);
        $all_params = [];
        $where = [];

        foreach ($columns as $column){
            if (!array_key_exists($column, $options)){
                continue;
            }

            $items = $options[$column];
            if (is_array($items) && empty($items)){
                // moodle_database::get_in_or_equal() does not accept empty arrays
                continue;
            }

            $prefix_column = str_replace('.', '_', $column);
            list($col_sql, $col_params) = static::db()->get_in_or_equal($items, SQL_PARAMS_NAMED, $prefix.'_'.$prefix_column.'_');
            $where[] = "$column $col_sql";
            $all_params[] = $col_params;
        }

        if (empty($where)){
            return [$return_where_as_array ? [] : '', []];
        }

        if (!$return_where_as_array){
            $where = static::sql_where($where, $condition, $without_word_where);
        }
        $params = array_merge(...$all_params);

        return [$where, $params];
    }

    /**
     * Constructs 'IN()' or '=' sql fragment for all options
     * Adding them to your where and params arrays, and return them
     * Alias @see db_util::sql_get_in_or_equal_options()
     *
     * @param array         $options - options to transform in where and parameters
     * @param array         $where - your where array
     * @param array         $params - your params array
     * @param array|null    $columns - key filter for options, if not null
     * @param string        $prefix  - prefix for SQL parameters
     *
     * @return array($where, $params)
     */
    static public function sql_get_in_or_equal_options_list($options, &$where=[], &$params=[], $columns=null, $prefix='param'){
        list($new_where, $new_params) = static::sql_get_in_or_equal_options($options, $prefix, $columns, true);
        $where = array_merge($where, $new_where);
        $params = array_merge($params, $new_params);
        return [$where, $params];
    }
}
