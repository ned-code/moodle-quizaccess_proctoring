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
 * Trait plugin_dependencies
 *
 * WARNING: When create class with this trait, don't forget use init() method
 *
 * @package quizaccess_proctoring\shared
 *
 */
trait plugin_dependencies {
    use util;
    /**
     *
     * CONST static variables - init in init() method from calling by child classes
     *  it can't be set as real readonly, but please, don't change it from outside
     *
     */
    /**
     * @var string @readonly - short name (for example 'proctoring')
     */
    static public $PLUGIN;
    /**
     * @var string @readonly - 'block', 'local' etc
     */
    static public $PLUGIN_TYPE;
    /**
     * @var string @readonly - version plugin name (for example 'quizaccess_proctoring')
     */
    static public $PLUGIN_NAME;
    /**
     * @var string @readonly - Full URL for plugin directory, https://www.site.com/mod/quiz/accessrule/proctoring
     */
    static public $PLUGIN_FULL_URL;
    /**
     * @var string @readonly - absolute path for plugin directory, /var/www/mod/quiz/accessrule/proctoring
     */
    static public $PLUGIN_PATH;
    /**
     * @var string @readonly - relative path for plugin directory, /mod/quiz/accessrule/proctoring
     */
    static public $PLUGIN_URL;
    /**
     * @var string @readonly - absolute path for project directory, /var/www/
     */
    static public $DIRROOT;
    /**
     * @var string @readonly - absolute url to the project, https://www.site.com
     */
    static public $WWWROOT;
    /**
     * @var string @readonly - you can set plugin prefix here, if you set up data by dir names (not recommended)
     */
    static public $PLUGIN_PREFIX = '';

    /**
     *
     * Real protected variables
     *
     */
    static protected $_is_init = false;
    /**
     * @var array @protected - use functions is_PLUGIN_exists() instead
     */
    static protected $_PLUGINS_EXISTS = [];

    /**
     * plugin_dependencies constructor.
     */
    public function __construct(){
        static::init();
    }

    /**
     * Call this method before using the class
     */
    static public function init(){
        if (static::$_is_init) return;

        static::_before_init();
        static::_real_init();
        static::_after_init();
    }

    /**
     * You can set here some data before main init method
     */
    static protected function _before_init(){
        return;
    }

    /**
     * You can set here some data after main init method
     */
    static protected function _after_init(){
        return;
    }

    /**
     * Main init method
     * Not recommended rewriting it in child classes
     */
    static protected function _real_init(){
        global $CFG;

        static::$DIRROOT = $CFG->dirroot;
        static::$WWWROOT = $CFG->wwwroot;

        $class = static::class;
        $reflector = new \ReflectionClass($class);
        $class_path =  $reflector->getFileName();
        $search_dir = dirname($class_path);
        while ($search_dir != '/'){
            if ($search_dir == static::$DIRROOT){
                break;
            }

            if (file_exists($search_dir.'/version.php')){
                static::$PLUGIN_PATH = $search_dir;
                break;
            }

            $search_dir = dirname($search_dir);
        }

        if (empty(static::$PLUGIN_PATH)){
            print_error("Can't find plugin directory!");
        }

        static::$PLUGIN = basename(static::$PLUGIN_PATH);
        static::$PLUGIN_URL = str_replace('\\', '/', substr(static::$PLUGIN_PATH, strlen(static::$DIRROOT)));
        static::$PLUGIN_FULL_URL = $CFG->wwwroot.static::$PLUGIN_URL;

        if ($reflector->inNamespace()){
            // try to get plugin data from namespace
            $namespace = $reflector->getNamespaceName();
            $namespace_path = explode('\\', $namespace);
            static::$PLUGIN_NAME = reset($namespace_path);
            $plugin_name_path = explode('_', static::$PLUGIN_NAME);
            static::$PLUGIN_TYPE = reset($plugin_name_path);
        } else {
            // try to get plugin data from dirs - it's not good, but better than nothing
            static::$PLUGIN_TYPE = basename(dirname(static::$PLUGIN_PATH));
            static::$PLUGIN_NAME = static::$PLUGIN_PREFIX.static::$PLUGIN_TYPE.'_'.static::$PLUGIN;
        }

        static::$_is_init = true;
    }

    /**
     * Check is plugin exists
     *
     * @param string $plugin - it should be one of the C::PLUGIN_DIRS keys
     *                       or plugin full frankenstyle name, e.g. mod_forum
     *
     * @return bool
     */
    static public function is_plugin_exists($plugin){
        static $_res = [];

        if (!isset($_res[$plugin])){
            if (isset(static::$_PLUGINS_EXISTS[$plugin])){
                $_res[$plugin] = static::$_PLUGINS_EXISTS[$plugin];
            } else {
                $_res[$plugin] = static::check_plugin_enabled($plugin);
            }
        }

        return $_res[$plugin];
    }

    /**
     * Get moodle get_string for this plugin
     *
     * @param string       $identifier - the key identifier for the localized string
     * @param array|mixed  $params - (optional), an object, string or number that can be used within translation strings
     * @param string       $plugin - (optional), use other plugin name (current by default)
     *
     * @return string
     */
    static public function str($identifier, $params=null, $plugin=null){
        if (empty($identifier)){
            return is_numeric($identifier) ? strval($identifier) : '';
        }

        $plugin = $plugin ?? static::$PLUGIN_NAME;
        if ($params && is_array($params)){
            $a = [];
            foreach ($params as $key => $val){
                if (is_int($key)){
                    $a['{' . $key . '}'] = $val;
                } else {
                    $a[$key] = $val;
                }
            }
        } else {
            $a = $params;
        }
        return get_string($identifier, $plugin, $a);
    }

    /**
     * Check and return moodle get_string for this plugin, or $def if didn't find
     *
     * @param string|array $identifier - if array, return first founded in the plugin strings
     * @param array|mixed  $params - (optional), an object, string or number that can be used within translation strings
     * @param string       $def    - (optional), default value, if there is no such string, if null - return $identifier
     * @param string       $plugin - (optional), use other plugin name (current by default)
     *
     * @return string
     */
    static public function str_check($identifier, $params=null, $def=null, $plugin=null){
        if (empty($identifier)){
            return is_numeric($identifier) ? strval($identifier) : ($def ?: '');
        }

        $plugin = $plugin ?? static::$PLUGIN_NAME;
        $identifiers = is_array($identifier) ? $identifier : [$identifier];
        $str_m = static::get_string_manager();

        foreach ($identifiers as $identifier){
            if ($str_m->string_exists($identifier, $plugin)){
                return static::str($identifier, $params, $plugin);
            }
        }

        return $def ?? $identifier;
    }

    /**
     * Get moodle get_strings for this plugin by array
     *
     * @param array        $identifiers - the keys identifier for the localized string
     * @param array|mixed  $params - (optional), an object, string or number that can be used within translation strings
     * @param string       $plugin - (optional), use other plugin name (current by default)
     * @param bool         $check - (optional), if true, use str_check fo strings
     * @param string       $def    - (optional), default value, if there is no such string, if null - return $identifier
     *
     * @return array
     */
    static public function str_arr($identifiers, $params=null, $plugin=null, $check=false, $def=null){
        $res = [];
        foreach ($identifiers as $key => $item){
            if ($check){
                $res[$key] = static::str_check($item, $params, $def, $plugin);
            } else {
                $res[$key] = static::str($item, $params, $plugin);
            }

        }

        return $res;
    }

    /**
     * Get configuration values from the config_plugins table.
     *
     * If called without name, it will load all the config
     * variables for one plugin, and return them as an object.
     *
     * If called with name, it will return a string single
     * value or false if the value is not found.
     *
     * @param string $name default null
     *
     * @return false|\stdClass|string|mixed hash-like object or single value, return false no config found
     */
    static public function get_config($name=null){
        return \get_config(static::$PLUGIN_NAME, $name);
    }

    /**
     * Update keys for plugin-scoped configs in config_plugin table.
     *
     * A NULL value will delete the entry.
     *
     * @param string $name the key to set
     * @param string $value the value to set (without magic quotes)
     *
     * @return bool true or exception
     */
    static public function set_config($name, $value){
        return \set_config($name, $value, static::$PLUGIN_NAME);
    }

    /**
     * Removes a key from plugin-scoped configuration.
     *
     * @param string $name the key to set
     *
     * @return boolean whether the operation succeeded.
     */
    static public function unset_config($name){
        return \unset_config($name, static::$PLUGIN_NAME);
    }

    /**
     * Create new instance of moodle_url
     * If url is string and it starts from ~ - it will be relative to plugin folder
     *
     * @param string $url    - relative url form plugin url
     * @param array  $params these params override current params or add new
     * @param string $anchor The anchor to use as part of the URL if there is one.
     * @param bool   $from_plugin - make url relative to plugin.
     *
     * @return \moodle_url
     */
    static public function url($url, $params=null, $anchor=null, $from_plugin=false){
        return new \moodle_url(static::path($url, false, $from_plugin), $params, $anchor);
    }

    /**
     * Return path for file
     * If it starts from ~ - it will be relative to plugin folder
     *
     * @param      $path
     * @param bool $use_filepath - use file path (if true) or url path (if false)
     * @param bool $from_plugin  - it will be relative to plugin folder
     * @param bool $false_if_wrong
     *
     *
     * @return false|string
     */
    static public function path($path, $use_filepath=true, $from_plugin=false, $false_if_wrong=false){
        $prefix = $use_filepath ? static::$DIRROOT : static::$WWWROOT;
        if (empty($path) || !is_string($path)){
            if (!$use_filepath && $path instanceof \moodle_url){
                return $path;
            } else {
                return $false_if_wrong ? false : $prefix;
            }
        } elseif (!$use_filepath){
            if (static::str_starts_with($path, ['#', 'https://', 'http://'])){
                return $path;
            }
        }

        $plugin_prefix = $use_filepath ? static::$PLUGIN_PATH : static::$PLUGIN_URL;

        if (static::str_starts_with($path, [$prefix, $plugin_prefix])){
            // we have absolute path yet
            return $path;
        }

        $tilde = static::str_rem_prefix($path, '~');
        static::str_add_prefix($path, '/');

        if ($from_plugin || $tilde){
            $prefix = $plugin_prefix;
        }

        return $prefix.$path;
    }

    /**
     * Return full capability name considering plugin name
     *
     * @param string $capability
     *
     * @return string
     */
    static public function get_full_capability($capability){
        static::str_rem_prefix($capability, '~');

        if (!static::str_has($capability, '/')){
            static::str_add_prefix($capability, ':');
            $capability = static::$PLUGIN_TYPE.'/'.static::$PLUGIN.$capability;
        }

        return $capability;
    }

    /**
     * Check whether a user has a particular capability in a given context and current plugin
     *
     * For example:
     *      $context = context_module::instance($cm->id);
     *      pl_has_capability('replypost', $context) // has_capability('local/ned_controller:replypost', $context)
     *      pl_has_capability(':replypost', $context) // has_capability('local/ned_controller:replypost', $context)
     *
     * By default checks the capabilities of the current user, but you can pass a
     * different userid. By default will return true for admin users, but you can override that with the fourth argument.
     *
     * Guest and not-logged-in users can never get any dangerous capability - that is any write capability
     * or capabilities with XSS, config or data loss risks.
     *
     * @param string            $capability the name of the capability to check. For example mod/forum:view
     * @param \context          $context    (optional) the context to check the capability in. By default (null) check context_system
     * @param integer|\stdClass $user       (optional) A user id or object. By default (null) checks the permissions of the current user.
     * @param boolean           $doanything (optional) If false, ignores effect of admin role assignment
     *
     * @return boolean true if the user has this capability. Otherwise false.
     */
    static public function has_capability($capability, $context=null, $user=null, $doanything=true){
        if (empty($capability) || !is_string($capability)) return false;
        $context = $context ?? \context_system::instance();

        return has_capability(static::get_full_capability($capability), $context, $user, $doanything);
    }

    /**
     * Check if the user has any one of several capabilities from a list.
     *
     * This is just a utility method that calls has_capability in a loop. Try to put
     * the capabilities that most users are likely to have first in the list for best
     * performance.
     *
     * @param array|string[]    $capabilities an array of capability names.
     * @param \context          $context    (optional) the context to check the capability in. By default (null) check context_system
     * @param integer|\stdClass $user       (optional) A user id or object. By default (null) checks the permissions of the current user.
     * @param boolean           $doanything (optional) If false, ignores effect of admin role assignment
     *
     * @return boolean true if the user has any of these capabilities. Otherwise false.
     * @see has_capability()
     *
     * @category access
     */
    static public function has_any_capability(array $capabilities, $context=null, $user=null, $doanything=true) {
        foreach ($capabilities as $capability) {
            if (static::has_capability($capability, $context, $user, $doanything)) {
                return true;
            }
        }
        return false;
    }

    /**
     * Check if the user has all the capabilities in a list.
     *
     * This is just a utility method that calls has_capability in a loop. Try to put
     * the capabilities that fewest users are likely to have first in the list for best
     * performance.
     *
     * @category access
     * @see      has_capability()
     *
     * @param array|string[]    $capabilities an array of capability names.
     * @param \context          $context    (optional) the context to check the capability in. By default (null) check context_system
     * @param integer|\stdClass $user       (optional) A user id or object. By default (null) checks the permissions of the current user.
     * @param boolean           $doanything (optional) If false, ignores effect of admin role assignment
     *
     * @return boolean true if the user has all of these capabilities. Otherwise false.
     */
    static public function has_all_capabilities(array $capabilities, $context=null, $user=null, $doanything=true) {
        foreach ($capabilities as $capability) {
            if (!static::has_capability($capability, $context, $user, $doanything)) {
                return false;
            }
        }
        return true;
    }

    /**
     * A convenience function that tests has_capability, and displays an error if
     * the user does not have that capability.
     *
     * NOTE before Moodle 2.0, this function attempted to make an appropriate
     * require_login call before checking the capability. This is no longer the case.
     * You must call require_login (or one of its variants) if you want to check the
     * user is logged in, before you call this function.
     *
     * @see has_capability()
     *
     * @param string            $capability the name of the capability to check. For example mod/forum:view
     * @param \context          $context        (optional) the context to check the capability in. By default (null) check context_system
     * @param integer|\stdClass $userid         (optional) A user id or object. By default (null) checks the permissions of the current user.
     * @param boolean           $doanything     (optional) If false, ignores effect of admin role assignment
     * @param string            $errormessage   (optional) The error string to to user. Defaults to 'nopermissions'.
     * @param string            $stringfile     (optional) The language file to load the error string from. Defaults to 'error'.
     *
     * @return void terminates with an error if the user does not have the given capability.
     */
    static public function require_capability($capability, $context=null, $userid=null, $doanything=true,
        $errormessage='nopermissions', $stringfile='') {
        if (!static::has_capability($capability, $context, $userid, $doanything)) {
            throw new \required_capability_exception($context, static::get_full_capability($capability), $errormessage, $stringfile);
        }
    }

    /**
     * A convenience function that tests has_capability for a list of capabilities, and displays an error if
     * the user does not have that capability.
     *
     * This is just a utility method that calls has_capability in a loop. Try to put
     * the capabilities that fewest users are likely to have first in the list for best
     * performance.
     *
     * @see require_all_capabilities()
     *
     * @param array|string[]    $capabilities the name of the capability to check. For example mod/forum:view
     * @param \context          $context        (optional) the context to check the capability in. By default (null) check context_system
     * @param integer|\stdClass $userid         (optional) A user id or object. By default (null) checks the permissions of the current user.
     * @param boolean           $doanything     (optional) If false, ignores effect of admin role assignment
     * @param string            $errormessage   (optional) The error string to to user. Defaults to 'nopermissions'.
     * @param string            $stringfile     (optional) The language file to load the error string from. Defaults to 'error'.
     *
     * @return void terminates with an error if the user does not have all capabilities from a list.
     */
    static public function require_all_capabilities($capabilities, $context=null, $userid=null, $doanything=true,
        $errormessage='nopermissions', $stringfile='') {
        foreach ($capabilities as $capability) {
            static::require_capability($capability, $context, $userid, $doanything, $errormessage, $stringfile);
        }
    }

    /**
     * A convenience function that tests has_capability for a list of capabilities, and displays an error if
     * the user does not have any one of several capabilities from a list.
     *
     * This is just a utility method that calls has_capability in a loop. Try to put
     * the capabilities that most users are likely to have first in the list for best
     * performance.
     *
     * @see has_any_capability()
     *
     * @param array|string[]    $capabilities the name of the capability to check. For example mod/forum:view
     * @param \context          $context        (optional) the context to check the capability in. By default (null) check context_system
     * @param integer|\stdClass $userid         (optional) A user id or object. By default (null) checks the permissions of the current user.
     * @param boolean           $doanything     (optional) If false, ignores effect of admin role assignment
     * @param string            $errormessage   (optional) The error string to to user. Defaults to 'nopermissions'.
     * @param string            $stringfile     (optional) The language file to load the error string from. Defaults to 'error'.
     *
     * @return void terminates with an error if the user does not have any one of capabilities from a list
     */
    static public function require_any_capabilities($capabilities, $context=null, $userid=null, $doanything=true,
        $errormessage='nopermissions', $stringfile='') {
        if (!static::has_any_capability($capabilities, $context, $userid, $doanything)){
            throw new \required_capability_exception($context, static::get_full_capability(reset($capabilities)), $errormessage, $stringfile);
        }
    }

    /**
     * Renders a template from current plugin by name with the given context
     *
     * The provided data needs to be array/stdClass made up of only simple types.
     * Simple types are array,stdClass,bool,int,float,string
     *
     * @param                 $templatename
     * @param array|\stdClass $context Context containing data for the template.
     *
     * @return string|boolean
     */
    static public function render_from_template($templatename, $context=null){
        if (empty($templatename) || !is_string($templatename)) return false;

        static::str_rem_prefix($templatename, '~');
        $slash = strpos($templatename, '/');
        if (!$slash){
            if ($slash === false){
                $templatename = '/'.$templatename;
            }
            $templatename = static::$PLUGIN_NAME.$templatename;
        }

        return static::O()->render_from_template($templatename, $context ?: []);
    }
}
