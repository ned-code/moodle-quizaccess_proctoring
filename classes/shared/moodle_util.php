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
 * Trait moodle_util
 *
 * @package quizaccess_proctoring\shared
 */
trait moodle_util {
    use plugin_dependencies, data_util, output;

    /**
     * Run function as site administrator
     *
     * @param callback $callback The function to be called.
     *                           Class methods may also be invoked statically using this function
     *                              by passing array($classname, $methodname) to this parameter.
     *                           Additionally class methods of an object instance may be called
     *                              by passing array($objectinstance, $methodname) to this parameter.
     * @param array $args - list of arguments to the function
     * @param array $required_files - list of file paths which need to be require
     *
     * @return mixed
     */
    static public function run_function_as_admin($callback, $args=[], $required_files=[]){
        global $USER;
        $is_not_admin = !is_siteadmin();
        $_user = $USER;
        $e = null;
        $res = null;
        static::require_files($required_files);

        if ($is_not_admin){
            $USER = get_admin();
        }

        try {
            $res = call_user_func_array($callback, $args);
        } catch (\Exception $ex){
            // detect active db transactions, rollback and log as error
            abort_all_db_transactions();
            $e = $ex;
        }

        if ($is_not_admin){
            $USER = $_user;
        }

        if ($e){
            default_exception_handler($e);
        }

        return $res;
    }

    /**
     * Return $CFG property or object itself
     *
     * @param null $property_name - if null, return full $CFG
     * @param null $def - return it, if property is unset or null
     *
     * @return mixed|object|null
     */
    static public function cfg($property_name=null, $def=null){
        global $CFG;
        if (is_null($property_name)){
            return $CFG;
        }

        return $CFG->$property_name ?? $def;
    }

    /**
     * Return context by one of the sent arguments
     * Checked context id, then course-module id, the course id
     * If all arguments are false, return system context
     *
     * @param int $courseid id from {course} table
     * @param int $cmid id of the record from {course_modules} table; pass cmid there, NOT id in the instance column
     * @param int $contextid - context id
     * @param int $strictness IGNORE_MISSING means compatible mode, false returned if record not found, debug message if more found;
     *                        MUST_EXIST means throw exception if no record found
     *
     * @return \context|\context_course|\context_module|\context_system|bool|null
     */
    static public function ctx($courseid=null, $cmid=null, $contextid=null, $strictness=MUST_EXIST){
        if ($contextid){
           return \context::instance_by_id($contextid, $strictness);
        } elseif ($cmid){
            return \context_module::instance($cmid, $strictness);
        } elseif ($courseid){
            return \context_course::instance($courseid, $strictness);
        } else {
            return \context_system::instance(0, $strictness);
        }
    }

    /**
     * Redirect with page catch
     *
     * @param \moodle_url|string $url
     * @param string             $message
     * @param int                $delay
     * @param string             $messagetype
     */
    static public function redirect($url, $message='', $delay=null, $messagetype=C::NOTIFY_INFO){
        global $PAGE;
        if ($PAGE->state == \moodle_page::STATE_PRINTING_HEADER){
            $PAGE->set_state(\moodle_page::STATE_IN_BODY);
        }
        \redirect($url, $message, $delay, $messagetype);
        die;
    }

    /**
     * Print notification and link "continue" as redirect attempt
     *
     * @param        $url
     * @param string $message
     * @param string $messagetype
     * @param bool   $return
     *
     * @return string
     */
    static public function redirect_continue($url, $message='', $messagetype=C::NOTIFY_INFO, $return=false){
        $output = '';
        if (!empty($message)){
            $output .= static::O()->notification($message, $messagetype);
        }
        $output .= static::div('(' . static::link($url, get_string('continue')) . ')', 'continuebutton');

        if (!$return){
            echo $output;
        }
        return $output;
    }

    /**
     * Load an AMD module and eventually call its method.
     *
     * This function creates a minimal inline JS snippet that requires an AMD module and eventually calls a single
     * function from the module with given arguments. If it is called multiple times, it will be create multiple
     * snippets.
     *
     * @param string $module The name of the AMD module to load, formatted as <component name>/<module name>.
     * @param string $func Optional function from the module to call, defaults to just loading the AMD module.
     * @param array $params The params to pass to the function (will be serialized into JSON).
     */
    static public function js_call_amd($module, $func='init', $params=[]){
        global $PAGE;
        if (empty($module)) return;

        $slash = strpos($module, '/');
        if ($slash !== false){
            if ($slash === 0){
                $fullmodule = static::$PLUGIN_NAME.$module;
            } else {
                $fullmodule = $module;
            }
        } else {
            $fullmodule = static::$PLUGIN_NAME.'/'.$module;
        }

        if (!is_array($params)){
            $params = [$params];
        }

        $PAGE->requires->js_call_amd($fullmodule, $func, $params);
    }

    /**
     * Call js_call_amd for list of modules
     * @see js_call_amd()
     *
     * @param array  $modules The name of the AMD module to load, formatted as <component name>/<module name>.
     * @param string $func Optional function from the module to call, defaults to just loading the AMD module.
     * @param array  $params The params to pass to the function (will be serialized into JSON).
     */
    static public function js_call_amds($modules, $func='init', $params=[]){
        $modules = static::val2arr($modules);
        foreach ($modules as $module){
            static::js_call_amd($module, $func, $params);
        }
    }

    /**
     * Return string script, if can't add class through the $PAGE, false in otherwise
     *
     * @param string|array $add_body_class
     * @param bool         $can_echo - echo to the page, if true
     *
     * @return false|string
     */
    static public function add_body_class($add_body_class, $can_echo=true){
        global $PAGE;
        $add_body_class = static::arr2str($add_body_class);
        if ($PAGE->state > \moodle_page::STATE_BEFORE_HEADER) {
            $res = \html_writer::script("window.document.body.className += ' $add_body_class';");
            if ($can_echo){
                echo $res;
            }

            return $res;
        } else {
            $PAGE->add_body_class($add_body_class);
        }

        return false;
    }

    /**
     * @param string $filepath
     * @param bool   $global_all
     *
     * @return bool
     */
    static public function require_file($filepath, $global_all=false){
        $filepath = static::path($filepath, true, false, true);
        if ($filepath && file_exists($filepath)){
            if ($global_all){
                foreach ($GLOBALS as $key => $val){
                    global $$key;
               }
            } else {
                global $CFG;
            }
            require_once($filepath);

            return true;
        }

        return false;
    }

    /**
     * Require list of files
     *
     * @param array $filepaths
     * @param bool  $global_all
     *
     * @return bool - true, if ALL files have been loaded correctly
     */
    static public function require_files($filepaths, $global_all=false){
        $res = true;
        $filepaths = static::val2arr($filepaths);
        foreach ($filepaths as $filepath){
            $res = $res && static::require_file($filepath, $global_all);
        }
        return $res;
    }

    /**
     * @param string $filepath
     * @param false  $global_all
     *
     * @return bool
     */
    static public function require_lib($filepath, $global_all=false){
        global $CFG;
        if (empty($filepath)){
            return false;
        }

        static::str_add_prefix($filepath, '/');
        return static::require_file($CFG->libdir.$filepath, $global_all);
    }

    /**
     * Require list of lib files
     *
     * @param array $filepaths
     * @param bool  $global_all
     *
     * @return bool - true, if ALL files have been loaded correctly
     */
    static public function require_libs($filepaths, $global_all=false){
        $res = true;
        $filepaths = static::val2arr($filepaths);
        foreach ($filepaths as $filepath){
            $res = $res && static::require_lib($filepath, $global_all);
        }
        return $res;
    }

    /**
     * Does user can access chosen course
     *
     * @param \stdClass|int|string|null $course
     * @param \stdClass|int|string|null $user
     *
     * @return bool
     */
    static public function has_user_access_course($course=null, $user=null){
        $user = static::get_chosen_user($user);
        if (!$user){
            return false;
        } elseif ($user->suspended || $user->deleted){
            return false;
        }

        $course = static::get_chosen_course($course);
        if (!$course){
            return false;
        }

        if ($course->id == SITEID) {
            return true;
        }

        $coursecontext = \context_course::instance($course->id, IGNORE_MISSING);
        if (!$coursecontext){
            return false;
        }

        if (is_siteadmin($user)){
            return true;
        }

        if (!$course->visible and !has_capability('moodle/course:viewhiddencourses', $coursecontext)){
            return false;
        }

        $access = false;
        if (is_viewing($coursecontext, $user)) {
            // Ok, no need to mess with enrol.
            $access = true;
        } else {
            if (isset($user->enrol['enrolled'][$course->id])) {
                if ($user->enrol['enrolled'][$course->id] > time()) {
                    $access = true;
                } else {
                    // Expired.
                    unset($user->enrol['enrolled'][$course->id]);
                }
            }
            if (isset($user->enrol['tempguest'][$course->id])) {
                if ($user->enrol['tempguest'][$course->id] == 0) {
                    $access = true;
                } else if ($user->enrol['tempguest'][$course->id] > time()) {
                    $access = true;
                } else {
                    // Expired.
                    unset($user->enrol['tempguest'][$course->id]);
                }
            }

            if (!$access) {
                // Cache not ok.
                $until = enrol_get_enrolment_end($coursecontext->instanceid, $user->id);
                if ($until !== false) {
                    // Active participants may always access, a timestamp in the future, 0 (always) or false.
                    if ($until == 0) {
                        $until = ENROL_MAX_TIMESTAMP;
                    }
                    $user->enrol['enrolled'][$course->id] = $until;
                    $access = true;
                }
            }
        }

        return (bool)$access;
    }

    /**
     * Does user can access chosen course module
     *
     * @param \stdClass|int|string      $cmorid
     * @param \stdClass|int|string|null $course
     * @param \stdClass|int|string|null $user
     *
     * @return bool
     */
    static public function has_user_access_cm($cmorid, $course=null, $user=null){
        if (!static::has_user_access_course($course, $user)){
            return false;
        }

        $user = static::get_chosen_user($user);
        $userid = $user->id;
        /** @var \cm_info $cm */
        if (!is_object($cmorid)){
            try{
                list($course, $cm) = get_course_and_cm_from_cmid($cmorid->id ?? $cmorid, '', $course->id ?? 0, $userid);
            } catch (\Throwable $e){
                return false;
            }
        } else {
            $cm = $cmorid;
        }

        $cmcontext = \context_module::instance($cm->id, IGNORE_MISSING);
        if (!$cmcontext){
            return false;
        }

        if ($cm->deletioninprogress || !$cm->uservisible){
            return false;
        }

        return true;
    }

    /**
     * @return bool
     */
    static public function is_desktop(){
        $devicetype = static::get_devicetype();
        return !($devicetype == \core_useragent::DEVICETYPE_MOBILE || $devicetype == \core_useragent::DEVICETYPE_TABLET);
    }

    /**
     * Use moodle print_error to show only title and and debug_info
     *
     * @see \print_error()
     *
     * @param string        $title
     * @param array|string  $debug_info
     *
     * @throws \moodle_exception
     */
    static public function print_simple_error($title, $debug_info=null){
        $debug_info = $debug_info ? static::arr2str($debug_info, '', "\n") : $debug_info;
        throw new \moodle_exception($title, 'error', '', null, $debug_info);
    }

    /**
     * Standard Debugging Function
     * alias for moodle debugging function
     * @see debugging()
     *
     * Returns true if the current site debugging settings are equal or above specified level.
     * If passed a parameter it will emit a debugging notice similar to trigger_error(). The
     * routing of notices is controlled by $CFG->debugdisplay
     * eg use like this:
     *
     * 1)  debugging('a normal debug notice');
     * 2)  debugging('something really picky', DEBUG_ALL);
     * 3)  debugging('annoying debug message only for developers', DEBUG_DEVELOPER);
     * 4)  if (debugging()) { perform extra debugging operations (do not use print or echo) }
     *
     * In code blocks controlled by debugging() (such as example 4)
     * any output should be routed via debugging() itself, or the lower-level
     * trigger_error() or error_log(). Using echo or print will break XHTML
     * JS and HTTP headers.
     *
     * It is also possible to define NO_DEBUG_DISPLAY which redirects the message to error_log.
     *
     * @param string $message a message to print
     * @param int $level the level at which this debugging statement should show
     * @param array $backtrace use different backtrace
     *
     * @return bool
     */
    static public function debugging($message='', $level=DEBUG_NORMAL, $backtrace=null){
        return debugging($message, $level, $backtrace);
    }

    /**
     * Additional debugging function
     *
     * @param string $message   - a message to print
     * @param int    $level     - some of the C::E_*, E_NOTICE by default
     * @param array  $backtrace - use different backtrace (works only for level C::E_NOTICE)
     *
     * @return bool
     */
    static public function e_debug($message='', $level=C::E_NOTICE, $backtrace=null){
        switch ($level){
            default:
            case C::E_NONE:
                return false;
            case C::E_NOTICE:
                return debugging($message, DEBUG_NORMAL, $backtrace);
            case C::E_WARNING:
                return \trigger_error($message, E_USER_WARNING);
            case C::E_ERROR:
                return \trigger_error($message, E_USER_ERROR);
        }
    }

    /**
     * Debug for cli tasks
     *
     * @param string     $message
     * @param string[]   $add_info
     *
     */
    static public function cli_debugging($message='', $add_info=[]){
        if (!CLI_SCRIPT){
            return;
        }

        if (!empty($add_info)){
            $message .= ' '.join("\n\t", $add_info);
        }
        mtrace($message);
    }

    /**
     * Sends a formatted data file to the browser
     * If $use_raw_data is true, all parameters will been not changed
     * Otherwise:
     * - $filename try to translate and add date mark to it
     * - $columns values be try to translate, and if key is int - change it to str value
     * - If callback will be null, create default one, which get values from datum by $columns key.
     *  Send false, if you wish to avoid this
     *
     * @see base_trait::download_dataformat_selector() - to get html element for download
     *
     * Note: it exit script at the function end
     *
     * @param string                $filename
     * @param string                $dataformat - result of download_dataformat_selector, wrong format will cause an error
     * @param array|string[]        $columns
     * @param \Iterable             $data - usually it will be array or \moodle_recordset
     * @param callable|null         $callback - function to export table row data from $data row (record) object
     * @param bool                  $use_raw_data = false
     *
     * @return void
     */
    static public function download_data($filename, $dataformat, $columns, $data, $callback=null, $use_raw_data=false){

        if ($use_raw_data){
            $fields = $columns;
        } else {
            $fields = [];
            foreach ($columns as $key => $column){
                $fields[is_numeric($key) ? $column : $key] = static::str_check($column);
            }

            $filename = static::str_check($filename).userdate(time(), '(%Y-%m-%d)');
            $filename = str_replace(' ', '_', $filename);

            if (is_null($callback)){
                $callback = function($datum) use ($fields) {
                    $res = [];
                    foreach ($fields as $key => $column){
                        $res[] = $datum->$key ?? '';
                    }
                    return $res;
                };
            }
        }

        \core\dataformat::download_data($filename, $dataformat, $fields, $data, $callback ?: null);
        exit;
    }

    /**
     * Convert a number to float, string or a null
     *
     * @param numeric|null  $val - number value to convert
     * @param bool          $as_string - (optional) if true, return string result
     * @param bool|int      $round - (optional) if true, round result, if number - round with such precision
     *                      NOTE: 0 (zero) $round is interpreted as TRUE
     * @param string|mixed  $def_string_value - (optional) default string to return when value is NULL
     *
     * @return float|int|string|null
     */
    static public function grade_val($val, $as_string=false, $round=false, $def_string_value=''){
        $round = ($round === 0) ? true : $round;

        if (is_null($val)){
            if ($as_string){
                return $def_string_value;
            } else {
                return $round ? 0 : null;
            }
        }

        if ($round){
            if (is_bool($round)){
                $val = round($val, 0);
            } else {
                $val = round($val, $round);
            }
        }

        if ($as_string){
            return strval($val);
        } elseif ($round){
            return $val;
        } else {
            return grade_floatval($val);
        }
    }

    /**
     * Return url for the stored_file
     *
     * @param \stored_file $file
     * @param bool         $include_itemid - should include itemid in the url or not
     * @param bool         $forcedownload - add force download param to the url params
     *
     * @return \moodle_url
     */
    static public function file_get_url($file, $include_itemid=true, $forcedownload=false){
        $path = [$file->get_contextid(), $file->get_component(), $file->get_filearea()];
        if ($include_itemid){
            $path []= $file->get_itemid();
        }

        $path = '/'.join('/', $path).$file->get_filepath().$file->get_filename();
        return \moodle_url::make_file_url('/pluginfile.php', $path, $forcedownload);
    }

    /**
     * Factory method for creation of url pointing to plugin file.
     *
     * Please note this method can be used only from the plugins to
     * create urls of own files, it must not be used outside of plugins!
     *
     * @param \stored_file $file
     * @param bool         $forcedownload - add force download param to the url params
     * @param bool|\mixed  $includetoken Whether to use a user token when displaying this group image.
     *                True indicates to generate a token for current user, and integer value indicates to generate a token for the
     *                user whose id is the value indicated.
     *                If the group picture is included in an e-mail or some other location where the audience is a specific
     *                user who will not be logged in when viewing, then we use a token to authenticate the user.
     *
     * @return \moodle_url
     */
    static public function file_get_pluginfile_url($file, $forcedownload=false, $includetoken=false){
        return \moodle_url::make_pluginfile_url(
            $file->get_contextid(),
            $file->get_component(),
            $file->get_filearea(),
            $file->get_itemid(),
            $file->get_filepath(),
            $file->get_filename(),
            $forcedownload,
            $includetoken,
        );
    }

    /**
     * Returns information about the known plugin, or null
     *
     * @param string $plugin_component full frankenstyle name, e.g. mod_forum
     *
     * @return \core\plugininfo\base|null the corresponding plugin information.
     */
    static public function get_plugin_info($plugin_component){
        return \core_plugin_manager::instance()->get_plugin_info($plugin_component);
    }

    /**
     * Return list of installed plugins of given type.
     *
     * @param string $plugintype
     *
     * @return array [$name => $version]
     */
    static public function get_installed_plugins($plugintype){
        return \core_plugin_manager::instance()->get_installed_plugins($plugintype);
    }

    /**
     * Return exact absolute path to a plugin directory.
     *
     * @param string $plugin_component name such as 'moodle', 'mod_forum'
     *
     * @return string|null - full path to component directory; NULL if not found
     */
    static public function get_plugin_directory($plugin_component){
        return \core_component::get_component_directory($plugin_component);
    }

    /**
     * Returns the exact absolute path to plugin directory by its type and name
     *
     * @param string $plugintype type of plugin
     * @param string $pluginname name of the plugin
     *
     * @return string|null - full path to plugin directory; null if not found
     */
    static public function get_plugin_directory_by_type_name($plugintype, $pluginname){
        return \core_component::get_plugin_directory($plugintype, $pluginname);
    }

    /**
     * Check plugin installed and exists
     *
     * @param string $plugin_component full frankenstyle name, e.g. mod_forum
     *
     * @return bool true if plugin installed and exists, else false
     */
    static public function check_plugin_enabled($plugin_component){
        return !empty(static::get_plugin_info($plugin_component)) &&
            !empty(static::get_plugin_directory($plugin_component));
    }

    /**
     * Check plugin installed and exists by its type and name
     *
     * @param string $plugintype f.e. 'mod','local' etc.
     * @param string $pluginname
     *
     * @return bool true if plugin installed and exists, else false
     */
    static public function check_plugin_enabled_by_type_name($plugintype, $pluginname){
        return !empty(static::get_installed_plugins($plugintype)[$pluginname]) &&
           !empty(static::get_plugin_directory_by_type_name($plugintype, $pluginname));
    }

    /**
     * Get module information data required for updating the module.
     * NED Changes: run as admin, so it skip all capability checks
     *
     * @param \cm_info|int|string $cm_or_id
     * @param \stdClass|int       $courseorid - Optional course object (or its id) if already loaded
     *
     * @return array required data for updating a module, list of course module, context, module, moduleinfo, and course section.
     */
    static public function get_moduleinfo_data($cm_or_id, $courseorid=null){
        $cm = static::get_cm_by_cmorid($cm_or_id, $courseorid);
        $args = [$cm->get_course_module_record(true), $cm->get_course()];
        /** @see \get_moduleinfo_data() */
        list($cm, $context, $module, $data, $cw) = static::run_function_as_admin('get_moduleinfo_data', $args, '/course/modlib.php');

        return [$cm, $context, $module, $data, $cw];
    }

    /**
     * Update the module info.
     * This function doesn't check the user capabilities. It updates the course module and the module instance.
     * Then execute common action to create/update module process (trigger event, rebuild cache, save plagiarism settings...).
     * NED Changes: just an alias
     *
     * @param \cm_info|numeric  $cm_or_id
     * @param object            $moduleinfo module info
     * @param object|int        $courseorid - Optional course object (or its id) if already loaded
     * @param object            $mform - the mform is required by some specific module in the function MODULE_update_instance(). This is due to a hack in this function.
     *
     * @return array list of course module and module info.
     */
    static public function update_moduleinfo($cm_or_id, $moduleinfo, $courseorid=null, $mform=null){
        $cm = static::get_cm_by_cmorid($cm_or_id, $courseorid);
        $cm_object = $cm->get_course_module_record(true);
        list($cm_object, $moduleinfo) = update_moduleinfo($cm_object, $moduleinfo, $cm->get_course(), $mform);
        return [$cm_object, $moduleinfo];
    }
}
