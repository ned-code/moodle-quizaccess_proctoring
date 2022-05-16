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
 * Trait data_util
 *
 * @package quizaccess_proctoring\shared
 */
trait data_util {
    use global_util, util, db_util, pd_util;

    /**
     * @return object
     */
    static public function session(){
        global $SESSION;

        // Initialise $SESSION if necessary.
        if (!is_object($SESSION)) {
            $SESSION = new \stdClass();
        }

        return $SESSION;
    }

    // OBJECTS

    /**
     * Return saved groupid from block settings, or from the global SESSION
     *
     * @param numeric $courseid
     *
     * @return int|null - return null, if find nothing
     */
    static public function get_cached_groupid($courseid){
        return static::session()->currentgroup[$courseid] ?? null;
    }

    /**
     * Return user object by object, id, or return global $USER
     * NOTICE: it tries to return global $USER if id/object is incorrect, not null
     *
     * @param object|numeric|null $user_or_id
     * @param bool                      $check_global_user
     *
     * @return \stdClass|null $user
     */
    static public function get_chosen_user($user_or_id=null, $check_global_user=true){
        global $USER;

        $userid = $user_or_id->id ?? ($user_or_id ?? 0);
        $res = static::g_get(__FUNCTION__, $userid);
        if (!is_null($res)){
            return $res ?: null;
        }

        $user = null;
        if ($user_or_id){
            if (!is_object($user_or_id)){
                $user = static::get_user($user_or_id);
            } else {
                $user = $user_or_id;
            }
        }

        if ($check_global_user){
            $user = static::choose_obj_with_id($user, $USER);
        }

        if ($user->id ?? false){
            static::g_set(__FUNCTION__, $user->id, $user);
        }
        static::g_set(__FUNCTION__, $userid, $user);

        return $user ?: null;
    }

    /**
     * Return received userid or global USER id
     *
     * @param object|numeric $user_or_id
     *
     * @return int
     */
    static public function get_userid_or_global($user_or_id=0){
        global $USER;

        $userid = static::get_id($user_or_id);

        return $userid ?: $USER->id;
    }

    /**
     * Return received courseid or global COURSE id
     *
     * @param object|numeric $course_or_id
     *
     * @return int
     */
    static public function get_courseid_or_global($course_or_id=null){
        global $COURSE;

        $courseid = static::get_id($course_or_id);

        return $courseid ?: $COURSE->id;
    }

    /**
     * Return course object by object, id, or return global $COURSE
     * NOTICE: it tries to return global $COURSE if id/object is incorrect, not null
     *
     * @param object|numeric|null $course_or_id
     * @param bool                      $check_global_course
     *
     * @return \stdClass|null
     */
    static public function get_chosen_course($course_or_id=null, $check_global_course=true){
        global $COURSE;

        $courseid = $course_or_id->id ?? ($course_or_id ?? 0);
        $res = static::g_get(__FUNCTION__, $courseid);
        if (!is_null($res)){
            return $res ?: null;
        }

        $course = null;
        if ($course_or_id){
            if (!is_object($course_or_id)){
                $course = static::get_course($course_or_id);
            } else {
                $course = $course_or_id;
            }
        }

        if ($check_global_course){
            $course = static::choose_obj_with_id($course, $COURSE);
        }

        if ($course->id ?? false){
            static::g_set(__FUNCTION__, $course->id, $course);
        }
        static::g_set(__FUNCTION__, $courseid, $course);

        return $course ?: null;
    }

    /**
     * If have $groupid and user has such group - return group with such group id,
     * otherwise, try get group id from the TT or global cache, and return such group, if found it,
     * otherwise, return just the first group from the list
     *
     * Note: this function for the global USER only
     *
     * @param numeric|object|null $course_or_id - course or its id, otherwise load global $COURSE
     * @param numeric|null $groupid - group id
     *
     * @return object|null - return null, if there are none groups, otherwise some group object
     */
    static public function get_chosen_group($course_or_id=null, $groupid=null){
        $courseid = static::get_courseid_or_global($course_or_id);
        $groups  = static::get_all_user_course_groups($courseid);
        if (empty($groups)){
            return null;
        }

        if (count($groups) == 1){
            return reset($groups);
        }

        if (!empty($groups[$groupid])){
            return $groups[$groupid];
        }

        $cached_groupid = static::get_cached_groupid($courseid);
        if (!empty($groups[$cached_groupid])){
            return $groups[$cached_groupid];
        }

        return reset($groups);
    }

    /**
     * Return current device type
     *
     * @return string
     */
    static public function get_devicetype(){
        global $CFG;
        $res = static::g_get(__FUNCTION__);
        if (is_null($res)){
            $enabledevicedetection_option = $CFG->enabledevicedetection ?? 0;
            $CFG->enabledevicedetection = 1;
            $cu = \core_useragent::instance(!$enabledevicedetection_option);
            $res = $cu::get_user_device_type();
            $CFG->enabledevicedetection = $enabledevicedetection_option;
            static::g_set(__FUNCTION__, [], $res);
        }

        return $res;
    }

    /**
     * Returns current string_manager instance.
     *
     * @return \core_string_manager
     */
    static public function get_string_manager(){
        $res = static::g_get(__FUNCTION__);
        if (is_null($res)){
            $res = get_string_manager();
            static::g_set(__FUNCTION__, [], $res);
        }

        return $res;
    }

    /**
     * Gets a course object from database. If the course id corresponds to an
     * already-loaded $COURSE or $SITE object, then the loaded object will be used,
     * saving a database query.
     *
     * @param int|string $courseid Course id
     *
     * @return null|\stdClass A course object
     */
    static public function get_course($courseid) {
        if (!$courseid){
            return null;
        }
        $courseid = (int)$courseid;
        $res = static::g_get(__FUNCTION__, $courseid);
        if (is_null($res)){
            if (static::g_get(__FUNCTION__, [0, 0])){
                // we have load all courses already
                return null;
            }

            try{
                $res = get_course($courseid);
            } catch (\Throwable $e){
                $res = null;
            }
            static::g_set(__FUNCTION__, $courseid, $res);
        }

        return $res ?: null;
    }

    /**
     * Return all site courses
     *  (and save it in the cache)
     *
     * @param $skip_site - if true, there will be not result by SITEID key
     *
     * @return object[]
     */
    static public function get_all_courses($skip_site=true){
        global $DB;
        if (!static::g_get('get_course', [0, 0])){
            $courses = $DB->get_records('course');
            $courses[0] = true;
            static::g_set('get_course', [], $courses);
        } else {
            $courses = static::g_get('get_course');
        }

        unset($courses[0]);
        if ($skip_site){
            unset($courses[SITEID]);
        }

        return $courses;
    }

    /**
     * This function gets the list of courses that this user has a particular capability in.
     *
     * It is now reasonably efficient, but bear in mind that if there are users who have the capability
     * everywhere, it may return an array of all courses.
     *
     * @param string    $capability Capability in question
     * @param int|null  $userid User ID or null for current user
     * @param string    $fieldsexceptid Leave blank if you only need 'id' in the course records;
     *   otherwise use a comma-separated list of the fields you require, not including id.
     *   Add ctxid, ctxpath, ctxdepth etc to return course context information for preloading.
     * @param string    $orderby If set, use a comma-separated list of fields from course
     *   table with sql modifiers (DESC) if needed
     * @param bool      $all_for_admin - if True - don't check permissions for admin (default)
     *
     * @return object[] list of courses
     *          Note: it's simple array-list AND it hasn't course ids as array keys
     *          If $fieldsexceptid is empty, it will be only course id in course objects
     */
    static public function get_course_by_capability($capability, $fieldsexceptid='', $userid=null, $orderby='', $all_for_admin=true){
        return get_user_capability_course($capability, $userid, $all_for_admin, $fieldsexceptid, $orderby, 0) ?: [];
    }

    /**
     * Return all courses, for which user has access
     *  You can add $capabilities to check, but then we can't save result in the local storage
     *
     * @param int|string|null  $userid User ID or null for current user
     * @param array|string     $capabilities - additional capability(es) to check
     *
     * @return array|mixed|object[]
     */
    static public function get_course_with_access($userid=null, $capabilities=[]){
        $userid = static::get_userid_or_global($userid);
        if (is_siteadmin($userid)){
            return static::get_all_courses();
        }

        $courses = static::g_get(__FUNCTION__, [$userid]);
        $check_access = false;
        $capabilities = static::val2arr($capabilities);
        $can_save = empty($capabilities); // we can't save all possible capabilities

        if (is_null($courses)){
            $check_access = true;
            if (empty($capabilities)){
                $courses = static::get_all_courses();
            } else {
                $main_cap = array_shift($capabilities);
                $courses = static::get_course_by_capability($main_cap, '*', $userid);
            }
        } elseif ($can_save){
            return $courses;
        }

        $access_courses = [];
        foreach ($courses as $course){
            if ($check_access){
                if (!can_access_course($course, $userid, '', true)){
                    continue;
                }
            }

            if (!empty($capabilities)){
                $ctx = \context_course::instance($course->id);
                if (!has_all_capabilities($capabilities, $ctx, $userid)){
                    continue;
                }
            }

            $access_courses[$course->id] = $course;
        }

        if ($can_save){
            static::g_set(__FUNCTION__, [$userid], $access_courses);
        }

        return $access_courses;
    }

    /**
     * Efficiently retrieves the $course (stdclass) and $cm (cm_info) objects, given
     * a cmid. If module name is also provided, it will ensure the cm is of that type.
     *
     * Usage:
     * list($course, $cm) = get_course_and_cm_from_cmid($cmid, 'forum');
     *
     * Using this method has a performance advantage because it works by loading
     * modinfo for the course - which will then be cached and it is needed later
     * in most requests. It also guarantees that the $cm object is a cm_info and
     * not a stdclass.
     *
     * The $course object can be supplied if already known and will speed
     * up this function - although it is more efficient to use this function to
     * get the course if you are starting from a cmid.
     *
     * @param \stdClass|int $cmorid - Id of course-module, or database object
     * @param string        $modulename Optional modulename (improves security)
     * @param \stdClass|int $courseorid Optional course object if already loaded
     * @param \stdClass|int $userorid Optional userid (default = current)
     *
     * @return \stdClass|\course_modinfo|null[] Array with 2 elements $course and $cm (or null and null)
     */
    static public function get_course_and_cm_from_cmid($cmorid, $modulename='', $courseorid=null, $userorid=null){
        $cmid = static::get_id($cmorid);
        if (!$cmid){
            return [null, null];
        }
        $courseid = (int)($cmorid->course ?? 0);
        $userid = static::get_userid_or_global($userorid);

        $res = static::g_get(__FUNCTION__, $cmid);
        if (is_null($res)){
            try{
                $res = get_course_and_cm_from_cmid($cmorid, '', $courseorid ?: $courseid, $userid);
            } catch (\Throwable $e){
                $res = null;
            }
            static::g_set(__FUNCTION__, $cmid, $res);
        }

        list($course, $cm) = $res ?: [null, null];
        if ($cm && !empty($modulename) && $cm->modname !== $modulename){
            list($course, $cm) = [null, null];
        }

        return [$course, $cm];
    }

    /**
     * @param \stdClass|int     $courseorid - Course object (or its id) if already loaded
     * @param \stdClass|int     $userorid   - Optional userid (default = current)
     * @param string[]|string   $filter_modnames - Optional array or single value to check cm modname
     *
     * @return \cm_info[] Array from course-module instance to cm_info object within this course, in
     *   order of appearance
     */
    static public function get_course_cms($courseorid, $userorid=null, $filter_modnames=[]){
        $userid = static::get_userid_or_global($userorid);
        $courseid = static::get_id($courseorid);
        $course_info = static::get_fast_modinfo($courseid, $userid);

        $filter_modnames = static::val2arr($filter_modnames);
        if (count($filter_modnames) == 1){
            $cms = [];
            $cms_by_inst = $course_info->get_instances_of(reset($filter_modnames));
            foreach ($cms_by_inst as $cm){
                $cms[$cm->id] = $cm;
            }
        } else {
            $cms = static::filter_cms_by_modnames($course_info->get_cms(), $filter_modnames);
        }

        return $cms;
    }

    /**
     * @param \stdClass|int $courseorid - Course object (or its id) if already loaded
     * @param \stdClass|int $userorid   - Optional userid (default = current)
     *
     * @return \cm_info[] Array from course-module instance to cm_info object within this course,
     *                      only which user can see and  which has view
     */
    static public function get_course_activities($courseorid, $userorid=null){
        $userid = static::get_userid_or_global($userorid);
        $courseid = static::get_id($courseorid);

        $activities = static::g_get(__FUNCTION__, [$courseid, $userid]);
        if (is_null($activities)){
            $cms = static::get_course_cms($courseid, $userid);
            $activities = [];
            foreach ($cms as $cm) {
                // Exclude activities that aren't visible or have no view link (e.g. label)
                if (!$cm->uservisible || !$cm->has_view()) {
                    continue;
                }

                $activities[$cm->id] = $cm;
            }

            static::g_set(__FUNCTION__, [$courseid, $userid], $activities);
        }

        return $activities;
    }

    /**
     * Return $cm (cm_info) objects, by a cmid. If module name is also provided,
     *  it will ensure the cm is of that type.
     *
     * The $course object (or its id) can be supplied if already known and will speed
     * up this function.
     *
     * Return null if find nothing (or $modulename is wrong)
     *
     * @see get_course_and_cm_from_cmid
     *
     * @param int           $cmid       - Id of course-module, or database object
     * @param \stdClass|int $courseorid - Optional course object (or its id) if already loaded
     * @param \stdClass|int $userorid   - Optional user object (or its id; default = current)
     * @param string        $modulename - Optional modulename (improves security)
     *
     * @return \cm_info|null
     */
    static public function get_cm_by_cmid($cmid, $courseorid=null, $userorid=null, $modulename=''){
        if ($courseorid){
            $cms = static::get_course_cms($courseorid, $userorid);
            $cm = $cms[$cmid] ?? null;
            if ($cm && !empty($modulename) && $cm->modname !== $modulename){
                $cm = null;
            }
        } else {
            list($course, $cm) = static::get_course_and_cm_from_cmid($cmid, $modulename, $courseorid, $userorid);
        }

        return $cm;
    }

    /**
     * @param \cm_info|object|numeric $cm_or_id
     * @param object|numeric   $courseorid - Optional course object (or its id) if already loaded
     * @param object|numeric   $userorid   - Optional user object (or its id; default = current)
     * @param string           $modulename - Optional modulename (improves security)
     *
     * @return \cm_info|object|null
     */
    static public function get_cm_by_cmorid($cm_or_id, $courseorid=null, $userorid=null, $modulename=''){
        if ($cm_or_id instanceof \cm_info){
            $cm = $cm_or_id;
            if ($courseorid && $cm->course != static::get_id($courseorid)) return null;

            if (!empty($modulename) && $cm->modname != $modulename) return null;

            if ($userorid && $cm->get_modinfo()->userid != static::get_id($userorid)){
                return static::get_cm_by_cmid($cm->id, $courseorid, $userorid, $cm->modname);
            }

            return $cm;
        } else {
            if (is_object($cm_or_id)){
                $courseorid = $courseorid ?? ($cm_or_id->course ?? null);
                $modulename = $modulename ?? ($cm_or_id->modname ?? null);
            }
            return static::get_cm_by_cmid(static::get_id($cm_or_id), $courseorid, $userorid, $modulename);
        }
    }

    /**
     * Get course id from cm
     *
     * @param \cm_info|int|string $cm_or_id
     *
     * @return int
     */
    static public function get_courseid_by_cmorid($cm_or_id){
        $cm = static::get_cm_by_cmorid($cm_or_id);
        return $cm ? $cm->course : 0;
    }

    /**
     * @see get_fast_modinfo()
     *
     * @param object|numeric $course_or_id
     * @param object|numeric $user_or_id - Current (global) user by default (if null|0)
     *
     * @return \course_modinfo|null
     */
    static public function get_fast_modinfo($course_or_id, $user_or_id=null){
        $courseid = static::get_id($course_or_id);
        $g_userid = static::get_userid_or_global();
        $userid = static::get_userid_or_global($user_or_id);
        if ($userid == $g_userid){
            $res = static::g_get(__FUNCTION__, [$courseid, $userid]);
            if (is_null($res)){
                $res = get_fast_modinfo($course_or_id, $userid);
                static::g_set(__FUNCTION__, [$courseid, $userid], $res);
            }
        } else {
            // do not save fast_modinfo for other users: it takes some time to create, but it takes too much memory to store
            $res = get_fast_modinfo($course_or_id, $userid);
        }

        return $res ?: null;
    }

    /**
     * @param \cm_info|numeric $cm_or_id
     *
     * @return \core_availability\info_module
     */
    static public function get_availability_info_module($cm_or_id){
        $cmid = static::get_id($cm_or_id);
        $res = static::g_get(__FUNCTION__, [$cmid]);
        if (is_null($res)){
            $res = new \core_availability\info_module(static::get_cm_by_cmorid($cm_or_id));
            static::g_set(__FUNCTION__, [$cmid], $res);
        }
        return $res;
    }

    /**
     * Return course module by courseid, itemmodule, iteminstance
     *
     * @param object|numeric    $course_or_id
     * @param string            $itemmodule - modname
     * @param string|numeric    $iteminstance - instance
     *
     * @return null|object|\cm_info
     */
    static public function get_cm_by_params($course_or_id, $itemmodule, $iteminstance){
        $modinfo = static::get_fast_modinfo($course_or_id);

        return $modinfo->instances[$itemmodule][$iteminstance] ?? null;
    }

    // USERS

    /**
     * Return user object from db or create noreply or support user,
     * if userid matches core_user::NOREPLY_USER or core_user::SUPPORT_USER
     * respectively. If userid is not found, then return null.
     *
     * @param int|string $userid
     *
     * @return \stdClass|null
     */
    static public function get_user($userid){
        if (!$userid){
            return null;
        }

        $userid = (int)$userid;
        $res = static::g_get(__FUNCTION__, $userid);
        if (is_null($res)){
            $res = \core_user::get_user($userid);
            static::g_set(__FUNCTION__, $userid, $res);
        }

        return $res ?: null;
    }

    /**
     * Gets a group object from database.
     * If the group id corresponds to an already-loaded group object,
     *  then the loaded object will be used, saving a database query.
     *
     * @see groups_get_group
     *
     * @param int|string $groupid Group id
     * @param string     $field - get only field value
     *
     * @return null|\stdClass|mixed - group object or null if not found,
     *                                if field specified - return its value (or null)
     */
    static public function get_group($groupid, $field=null){
        if (!$groupid){
            return null;
        }

        $groupid = (int)$groupid;
        $res = static::g_get(__FUNCTION__, $groupid);
        if (is_null($res)){
            $res = groups_get_group($groupid);
            static::g_set(__FUNCTION__, $groupid, $res);
        }

        if ($field){
            return $res->$field ?? null;
        }
        return $res ?: null;
    }

    /**
     * Gets the name of a group with a specified id
     * Alias for @see \local_ned_controller\shared\data_util::get_group()
     *
     * @param int|string $groupid Group id
     *
     * @return string|null - The name of the group
     */
    static public function get_groupname($groupid){
        return static::get_group($groupid, 'name');
    }

    /**
     * Gets a group object from database.
     * If the group id corresponds to an already-loaded group object,
     *  then the loaded object will be used, saving a database query.
     *
     * @see groups_get_members
     *
     * @param int|string $groupid Group id
     * @param bool       $only_ids return only user id or full record
     *
     * @return array|object[]|int[] users by id or list of userids
     */
    static public function get_group_users($groupid, $only_ids=false){
        if (!$groupid){
            return null;
        }
        $groupid = (int)$groupid;
        $only_ids = (int)$only_ids;
        $res = static::g_get(__FUNCTION__, [$groupid, $only_ids]);
        if (is_null($res)){
            if ($only_ids){
                $users = static::g_get(__FUNCTION__, [$groupid, 0]);
                if (is_null($users)){
                    $users = groups_get_members($groupid, 'u.id');
                }
                $res = array_keys($users);
            } else {
                $res = groups_get_members($groupid, 'u.*');
            }

            static::g_set(__FUNCTION__, [$groupid, $only_ids], $res);
        }

        return $res ?: [];
    }

    /**
     * Returns info about user's groups in course (or all).
     * Can use cache created by load_all_user_groups
     *
     * @see static::load_all_user_groups
     * @see groups_get_user_groups
     *
     * @param int $courseid
     * @param int $userid $USER if not specified
     *
     * @return array Array[groupingid][groupid_1, groupid_2, ...] including grouping id 0 which means all groups if $courseid,
     *               else Array[courseid][groupid_1, groupid_2, ...] including course id 0 which means all groups
     */
    static public function get_user_groupings($courseid, $userid=0){
        $get_it = static::g_get('load_all_user_groups', $courseid) ||
            ($courseid && static::g_get('load_all_user_groups', 0));

        if (!$get_it){
            $usergroups = groups_get_user_groups($courseid, $userid);
            if ($courseid){
                return $usergroups;
            }
        }

        // else: we have checked all users, don't need DB query from the base function
        $cache = \cache::make('core', 'user_group_groupings');
        $usergroups = $cache->get($userid);
        if ($courseid || empty($usergroups)){
            return $usergroups[$courseid] ?? [0 => []];
        }

        $all_user_groups = [];
        foreach ($usergroups as $cid => $usergroup){
            $all_user_groups[$cid] = $usergroup[0] ?? [];
        }
        $all_user_groups[0] = array_merge(...$all_user_groups);

        return $all_user_groups;
    }

    /**
     * Returns user's group ids in course (or all).
     * Can use cache created by load_all_user_groups
     * alias for the get_user_groupings to get group ids without grouping ids
     *
     * @see get_user_groupings
     * @see static::load_all_user_groups
     * @see groups_get_user_groups
     *
     * @param int $courseid
     * @param int $userid $USER if not specified
     *
     * @return array [groupid_1, groupid_2, ...]
     */
    static public function get_user_groupids($courseid, $userid=0){
        $groupings = static::get_user_groupings($courseid, $userid);
        return $groupings[0] ?? [];
    }

    /**
     * Returns course's group ids.
     * alias for the get_all_course_groups to get only group ids
     * @see get_all_course_groups
     *
     * @param int $courseid
     *
     * @return array [groupid_1, groupid_2, ...]
     */
    static public function get_course_groupids($courseid){
        return array_keys(static::get_all_course_groups($courseid, 0, 'g.id') ?: []);
    }

    /**
     * Load all groups by courseid in the cache
     * BE CAREFUL with $courseid = 0, it will load ALL groups
     *
     * If you will change this function, change also get_user_groups
     * @see static::get_user_groupids
     *
     * @param int $courseid
     */
    static public function load_all_user_groups($courseid=0){
        if (static::g_get(__FUNCTION__, $courseid) ||
            ($courseid && static::g_get(__FUNCTION__, 0))){
            return;
        }

        global $DB;
        $cache = \cache::make('core', 'user_group_groupings');

        $sql = "SELECT CONCAT(g.id, '_', gm.userid) AS id, 
                    g.id AS gropid, gm.userid, g.courseid, gg.groupingid
                  FROM {groups} g
                  JOIN {groups_members} gm ON gm.groupid = g.id
             LEFT JOIN {groupings_groups} gg ON gg.groupid = g.id
        ";
        $params = [];
        if ($courseid){
            $sql .= "\nWHERE g.courseid = :courseid";
            $params['courseid'] = $courseid;
        }

        $all_groups = [];
        $user_groups = [];

        $rs = $DB->get_recordset_sql($sql, $params);
        foreach ($rs as $group) {
            $all_groups[$group->userid][$group->courseid][$group->gropid] = $group->gropid;
            if (is_null($group->groupingid)) {
                continue;
            }
            $user_groups[$group->userid][$group->courseid][$group->groupingid][$group->gropid] = $group->gropid;
        }
        $rs->close();

        foreach ($all_groups as $userid => $allgroups){
            foreach (array_keys($allgroups) as $cid) {
                $user_groups[$userid][$cid]['0'] = array_keys($allgroups[$cid]); // All user groups in the course.
            }
            // Cache the data.
            $cache->set($userid, $user_groups[$userid]);
        }

        static::g_set(__FUNCTION__, $courseid, true);
    }

    /**
     * Gets array of all groups in a specified course (subject to the conditions imposed by the other arguments).
     * alias for the groups_get_all_groups
     * @see groups_get_all_groups
     *
     * @param int $courseid The id of the course.
     * @param int|int[] $userid optional user id or array of ids, returns only groups continaing one or more of those users.
     * @param string $fields defaults to g.*. This allows you to vary which fields are returned.
     *      If $groupingid is specified, the groupings_groups table will be available with alias gg.
     *      If $userid is specified, the groups_members table will be available as gm.
     * @param bool $withmembers if true return an extra field members (int[]) which is the list of userids that
     *      are members of each group. For this to work, g.id (or g.*) must be included in $fields.
     *      In this case, the final results will always be an array indexed by group id.
     * @param int $groupingid optional returns only groups in the specified grouping.
     *
     * @return array returns an array of the group objects (unless you have done something very weird
     *      with the $fields option).
     */
    static public function get_all_course_groups($courseid, $userid=0, $fields='g.*', $withmembers=false, $groupingid=0){
        return groups_get_all_groups($courseid, $userid, $groupingid, $fields, $withmembers);
    }

    /**
     * Gets array of all groups in a specified course (subject to the conditions imposed by the other arguments).
     * If user has access to all groups on course - return all groups on course, otherwise only groups with user
     * @see get_all_course_groups
     *
     * @param int|object    $courseorid The course object or its id
     * @param int           $userid optional user id, who should have access to the groups (global $USER by default)
     * @param string        $fields defaults to g.*. This allows you to vary which fields are returned.
     *      If $groupingid is specified, the groupings_groups table will be available with alias gg.
     *      If $userid is specified, the groups_members table will be available as gm.
     * @param bool          $withmembers if true return an extra field members (int[]) which is the list of userids that
     *      are members of each group. For this to work, g.id (or g.*) must be included in $fields.
     *      In this case, the final results will always be an array indexed by group id.
     * @param int           $groupingid optional returns only groups in the specified grouping.
     *
     * @return array returns an array of the group objects (unless you have done something very weird
     *      with the $fields option).
     */
    static public function get_all_user_course_groups($courseorid, $userid=0, $fields='g.*', $withmembers=false, $groupingid=0){
        $courseid = static::get_id($courseorid);
        $userid = static::get_userid_or_global($userid);
        if (!$courseid){
            return [];
        }

        $context = \context_course::instance($courseid);
        $aag = has_capability('moodle/site:accessallgroups', $context, $userid);

        return static::get_all_course_groups($courseid, $aag ? 0 : $userid, $fields, $withmembers, $groupingid);
    }

    /**
     * Get list with users by there id
     *
     * @param $ids_or_users - list of users, or there id
     *
     * @return array
     */
    static public function get_user_list($ids_or_users){
        if (empty($ids_or_users)){
            return [];
        }

        $ids_or_users = static::val2arr($ids_or_users);
        $k = key($ids_or_users);
        $v = reset($ids_or_users);
        $res = [];
        if ($k === 0){
            if (is_object($v)){
                foreach ($ids_or_users as $user){
                    $res[$user->id] = $user;
                }
            } else {
                foreach ($ids_or_users as $userid){
                    $res[$userid] = static::get_user($userid);
                }
            }
        } else {
            if (is_object($v)){
                return $ids_or_users;
            } else {
                foreach ($ids_or_users as $userid => $smth){
                    $res[$userid] = static::get_user($userid);
                }
            }
        }

        return $res;
    }

    /**
     * @param int|\cm_info|object   $cm_or_id   - Id of course-module, or database object
     *
     * @return string[]|array tags[$id => $name]
     */
    static public function get_tags_by_cm($cm_or_id){
        $cmid = static::get_id($cm_or_id);
        $tags = static::g_get(__FUNCTION__, $cmid);
        if (is_null($tags)){
            $tags = \core_tag_tag::get_item_tags_array('core', 'course_modules', $cmid);
            static::g_set(__FUNCTION__, $cmid, $tags);
        }

        return $tags;
    }

    /**
     * Get course modules ids by tag names OR tag ids
     *
     * @param array   $tags_name - array of raw (human read) tag names
     * @param array   $tags_id - array of tag id
     * @param object|numeric $course_or_id
     *
     * @return array
     */
    static public function get_cmids_by_tags($tags_name=[], $tags_id=[], $course_or_id=null){
        global $DB;
        if (empty($tags_name) && empty($tags_id)){
            return [];
        }

        $tags_name = static::val2arr($tags_name);
        $tags_id = static::val2arr($tags_id);
        $courseid = static::get_id($course_or_id);

        $select = 'cm.id';
        $from = "
            JOIN {tag_instance} AS ti
                ON ti.tagid = tag.id
                AND ti.component = 'core'
                AND ti.itemtype = 'course_modules'
            JOIN {course_modules} AS cm
                ON cm.id = ti.itemid
        ";
        $where = [];
        $params = [];
        if (!empty($tags_name)){
            list($tg_sql, $tg_params) = $DB->get_in_or_equal($tags_name, SQL_PARAMS_NAMED, 'tag_name');
            $where[] = 'tag.rawname '.$tg_sql;
        } else {
            list($tg_sql, $tg_params) = $DB->get_in_or_equal($tags_id, SQL_PARAMS_NAMED, 'tag_ids');
            $where[] = 'tag.id '.$tg_sql;
        }
        $params = array_merge($params, $tg_params);

        if ($courseid){
            $where[] = 'cm.course = :courseid';
            $params['courseid'] = $courseid;
        }

        $sql = static::sql_generate($select, $from, 'tag', 'tag', $where);
        $records = $DB->get_records_sql($sql, $params);
        $cmids = array_keys($records);

        return array_combine($cmids, $cmids);
    }

    /**
     * Check, does course categories (array of ids) has such course id
     * If none $courseid, return array of all course id from the current course categories
     *
     * @param array|string $course_cats - if string, id values should be delimiter by ','
     * @param              $courseid
     *
     * @return array|bool
     */
    static public function course_cats_has_courseid($course_cats, $courseid=0){
        global $DB;
        if (empty($course_cats) && $course_cats != 0){
            return false;
        }
        if (!is_array($course_cats)){
            if (is_string($course_cats) && strpos($course_cats, ',') !== false){
                $course_cats = explode(',', $course_cats);
            } else {
                $course_cats = [$course_cats];
            }
        }
        if (in_array(0, $course_cats)){
            return true;
        }

        list($cc_sql, $params) = $DB->get_in_or_equal($course_cats, SQL_PARAMS_NAMED);
        $params['cat_contextlevel'] = CONTEXT_COURSECAT;
        $params['course_contextlevel'] = CONTEXT_COURSE;

        $sql = "SELECT DISTINCT c.id, c.id as courseid 
            FROM {course} c 
            JOIN {context} cat_ctx
                ON cat_ctx.instanceid $cc_sql
                AND cat_ctx.contextlevel = :cat_contextlevel
            JOIN {context} course_ctx
                ON course_ctx.instanceid = c.id 
                AND course_ctx.contextlevel = :course_contextlevel
                AND course_ctx.path LIKE CONCAT(cat_ctx.path, '/%')
        ";

        if ($courseid){
            $params['courseid'] = $courseid;
            $sql .= "\nWHERE c.id = :courseid";
            return $DB->record_exists_sql($sql, $params);
        } else {
            return $DB->get_records_sql_menu($sql, $params);
        }
    }

    /**
     * Checks whether mod/...:view capability restricts the user's access.
     * @see \cm_info::is_user_access_restricted_by_capability()
     * #core_moodle - you have to update this function to moodle core functionality
     *
     * @param \cm_info|numeric  $cm_or_id
     * @param object|numeric    $user_or_id
     *
     * @return bool True if the user access is restricted.
     */
    static public function cm_is_user_access_restricted_by_capability($cm_or_id, $user_or_id=null){
        $cm = static::get_cm_by_cmorid($cm_or_id);
        $capability = 'mod/' . $cm->modname . ':view';
        $capabilityinfo = get_capability_info($capability);
        if (!$capabilityinfo) {
            // Capability does not exist, no one is prevented from seeing the activity.
            return false;
        }

        // You are blocked if you don't have the capability.
        return !has_capability($capability, $cm->context, $user_or_id);
    }

    /**
     * NED calculation logic of activity visibility
     * Normally you ca get all this data (except $unavailable_as_invisible) from course-module(cm, \cm_info)
     *
     * If you wish to get data for other user by the same cm, you can use _get_visibility_data_by_cm_user()
     * @see _get_visibility_data_by_cm_user()
     *
     * @param bool      $unavailable_as_invisible  - if true, than with false uservisible - return false, despite available info
     * @param bool      $uservisible
     * @param string    $availableinfo
     * @param bool      $has_view
     *
     * @return bool
     */
    static protected function _calc_activity_visibility($unavailable_as_invisible=false, $uservisible=false, $availableinfo='',
        $has_view=false){
        if (!$uservisible){
            // this is a student who is not allowed to see the module but might be allowed
            // to see availability info (i.e. "Available from ...")
            if ($unavailable_as_invisible || empty($availableinfo)){
                return false;
            }
        }

        if (!$has_view){
            return false;
        }

        return true;
    }

    /**
     * Get cm-user data necessary for calculate activity visibility
     * @see _calc_activity_visibility()
     *
     * If cm was loaded for not called user, it tries to emulate standard cm checks for other user without loading cms for this user
     * #core_moodle - you have to update this function to moodle core functionality
     *
     * Note: Function is supposed to be used for check many users - for one activity,
     *      if you need checks only one user, you may load already checked activities with get_important_activities()
     * @see get_important_activities()
     *
     * @param \cm_info|numeric  $cm_or_id
     * @param object|numeric    $user_or_id
     *
     * @return array($uservisible, $availableinfo, $has_view) = list($uservisible, $availableinfo, $has_view)
     */
    static protected function _get_visibility_data_by_cm_user($cm_or_id, $user_or_id=null){
        $cmid = static::get_id($cm_or_id);
        $userid = static::get_userid_or_global($user_or_id);
        $res = static::g_get(__FUNCTION__, [$cmid, $userid]);
        if (is_null($res)){
            $uservisible = false;
            $availableinfo = '';
            $has_view = false;
            /**
             * For optimization purposes, we will NOT load cm for checked userid:
             *      if we will check many users, it will cause loading course cms for all of them
             */
            $cm = static::get_cm_by_cmorid($cm_or_id);
            do {
                if (!$cm || !$userid){
                    break;
                } elseif ($cm->get_modinfo()->get_user_id() == $userid){
                    // cm was loaded for the same user
                    list($uservisible, $availableinfo, $has_view) =
                        [$cm->uservisible, $cm->availableinfo, $cm->has_view()];
                    break;
                }

                /**
                 * Next we try to emulate course module checks, without loading cm for this user
                 * You will may have to update it, after moodle core update
                 * #core_moodle
                 */
                $uservisible = true;
                $available = true;
                $has_view = $cm->has_view();
                $modinfo = static::get_fast_modinfo($cm->course, $userid);

                /**
                 * @see \cm_info::obtain_dynamic_data()
                 */
                if (!empty(static::cfg('enableavailability'))){
                    // Get availability information.
                    $ci = static::get_availability_info_module($cm);

                    // Note that the modinfo currently available only includes minimal details (basic data)
                    // but we know that this function does not need anything more than basic data.
                    $available = $ci->is_available($availableinfo, true, $userid, $modinfo);
                }

                // Check parent section.
                if ($available) {
                    $parentsection = $modinfo->get_section_info($cm->sectionnum);
                    if (!$parentsection->available) {
                        // Do not store info from section here, as that is already
                        // presented from the section (if appropriate) - just change
                        // the flag
                        $available = false;
                    }
                }

                /**
                 * Update visible state for current user
                 * @see \cm_info::update_user_visible()
                 */
                // If the module is being deleted, set the uservisible state to false and return.
                if ($cm->deletioninprogress) {
                    $uservisible = false;
                    $availableinfo = '';
                    break;
                }

                // If the user cannot access the activity set the uservisible flag to false.
                // Additional checks are required to determine whether the activity is entirely hidden or just greyed out.
                $ctx = $cm->context;
                if ((!$cm->visible && !has_capability('moodle/course:viewhiddenactivities', $ctx, $userid)) ||
                    (!$available && !has_capability('moodle/course:ignoreavailabilityrestrictions', $ctx, $userid))){
                    $uservisible = false;
                }

                /**
                 * Check group membership
                 * @see \cm_info::is_user_access_restricted_by_capability()
                 */
                if (static::cm_is_user_access_restricted_by_capability($cm, $userid)){
                    $uservisible = false;
                    // Ensure activity is completely hidden from the user.
                    $availableinfo = '';
                }

                /*
                $uservisibleoncoursepage = $uservisible &&
                    ($cm->visibleoncoursepage ||
                        has_capability('moodle/course:manageactivities', $ctx, $userid) ||
                        has_capability('moodle/course:activityvisibility', $ctx, $userid));
                // Activity that is not available, not hidden from course page and has availability
                // info is actually visible on the course page (with availability info and without a link).
                if (!$uservisible && $cm->visibleoncoursepage && $availableinfo) {
                    $uservisibleoncoursepage = true;
                }
                */

                /**
                 * "Let module make dynamic changes at this point"
                 *      - we can't really check it here, so, let's hope, that there are nothing relevant
                 */
                //$cm->call_mod_function('cm_info_dynamic');
            } while(false);

            $res = [$uservisible, $availableinfo, $has_view];
            static::g_set(__FUNCTION__, [$cmid, $userid], $res);
        }

        return $res;
    }

    /**
     * Get cm visibility by NED logic and using custom user
     * @see _calc_activity_visibility
     *
     * WARNING: Function is supposed to be used for check many users - for one activity,
     *      if you need checks only one user, it will be better to load cm(s) for this user and uses check_activity_visible_by_cm()
     *      or load already checked activities with get_important_activities()
     * @see get_important_activities()
     *
     * @param \cm_info|numeric  $cm_or_id
     * @param object|numeric    $user_or_id - if null, use current(global) $USER by default
     * @param bool              $unavailable_as_invisible - if true, than with false uservisible - return false, despite available info
     * @param bool              $check_global_visibility - if true, return false, if loaded cm (or $USER) can't see this activity, despite $userid
     *
     * @return bool - visibility $cm for the $user_or_id by NED rules
     */
    static public function get_cm_visibility_by_user($cm_or_id, $user_or_id=null, $unavailable_as_invisible=false, $check_global_visibility=true){
        $cmid = static::get_id($cm_or_id);
        if (!$user_or_id){
            $check_global_visibility = false;
        }
        $userid = static::get_userid_or_global($user_or_id);
        $unavailable_as_invisible = (int)$unavailable_as_invisible;
        $check_global_visibility = (int)$check_global_visibility;

        $res = static::g_get(__FUNCTION__, [$cmid, $userid, $unavailable_as_invisible, $check_global_visibility]);
        if (is_null($res)){
            do {
                if ($check_global_visibility){
                    $g_userid = static::get_userid_or_global();
                    // we do not need check global user, if $userid - is global
                    if ($g_userid != $userid){
                        $res = static::get_cm_visibility_by_user($cm_or_id, $g_userid, $unavailable_as_invisible);
                        if (!$res){
                            break;
                        }
                    }
                }

                $v_data = static::_get_visibility_data_by_cm_user($cm_or_id, $user_or_id);
                $res = static::_calc_activity_visibility($unavailable_as_invisible, ...$v_data);
            } while(false);

            static::g_set(__FUNCTION__, [$cmid, $userid, $unavailable_as_invisible, $check_global_visibility], $res);
        }

        return $res;
    }

    /**
     * Get cm visibility by NED logic and using custom user
     * @see _calc_activity_visibility
     *
     * WARNING: Function is supposed to be used for check many users - for one activity,
     *      if you need checks only one user, it will be better to load cm(s) for this user and uses check_activity_visible_by_cm()
     *      or load already checked activities with get_important_activities()
     * @see get_important_activities()
     *
     * @param \cm_info|numeric          $cm_or_id
     * @param array|object|numeric      $users_or_ids - if empty, check only for current(global) $USER by default
     * @param bool                      $unavailable_as_invisible - if true, than with false uservisible - return false, despite available info
     * @param bool                      $check_global_visibility - if true, return false, if loaded cm (or $USER) can't see this activity
     * @param bool                      $rule_any - if true, check that at least one user can see activity, otherwise - that all user can see activity
     *
     * @return bool - visibility $cm for the $user_or_id by NED rules
     */
    static public function get_cm_visibility_by_userlist($cm_or_id, $users_or_ids=[], $unavailable_as_invisible=false,
        $check_global_visibility=true, $rule_any=true){
        if (empty($users_or_ids)){
            // check only global user
            return static::get_cm_visibility_by_user($cm_or_id, null, $unavailable_as_invisible, false);
        } else {
            if ($check_global_visibility){
                if (!static::get_cm_visibility_by_user($cm_or_id, null, $unavailable_as_invisible, false)){
                    return false;
                }
            }

            $users_or_ids = static::val2arr($users_or_ids);
            foreach ($users_or_ids as $user_or_id){
                if (static::get_cm_visibility_by_user($cm_or_id, $user_or_id, $unavailable_as_invisible, false)){
                    if ($rule_any){
                        return true;
                    }
                } elseif (!$rule_any){
                    return false;
                }
            }

            return !$rule_any;
        }
    }

    /**
     * NED standard check, to show activity in some list or not
     * NOTE: visibility check for user, for whom cm was loaded (or for global $USER, if you provided cm id)
     * @see get_cm_visibility_by_user()
     *
     * @param \cm_info|numeric  $cm_or_id
     * @param bool              $unavailable_as_invisible - if true, than with false uservisible - return false, despite available info
     *
     * @return bool - visibility $cm for the loaded user by NED rules
     */
    static public function check_activity_visible_by_cm($cm_or_id, $unavailable_as_invisible=false){
        $cm = static::get_cm_by_cmorid($cm_or_id);
        return static::get_cm_visibility_by_user($cm, $cm->get_modinfo()->userid, $unavailable_as_invisible, false);
    }

    /**
     * Use instead of get_gradable_activities to get activities for Student/Class progress
     *
     * @param numeric|object    $course_or_id
     * @param numeric|object    $user_or_id
     * @param bool              $unavailable_as_invisible - if true, than with false uservisible - return false, despite available info
     *
     * @return \cm_info[]|array
     */
    static public function get_important_activities($course_or_id, $user_or_id=null, $unavailable_as_invisible=false){
        $courseid = static::get_courseid_or_global($course_or_id);
        $userid = static::get_userid_or_global($user_or_id);
        $unavailable_as_invisible = (int)$unavailable_as_invisible;
        $res = static::g_get(__FUNCTION__, [$courseid, $userid, $unavailable_as_invisible]);

        if (is_null($res)){
            $cms = static::get_course_cms($courseid, $userid);
            $res = [];

            foreach ($cms as $key => $cm){
                if (static::get_cm_visibility_by_user($cm, $userid, $unavailable_as_invisible, false)){
                    $res[$key] = $cm;
                }
            }
            static::g_set(__FUNCTION__, [$courseid, $userid, $unavailable_as_invisible], $res);
        }

        return $res;
    }

    /**
     * Use instead of get_gradable_activities to get activities for Student/Class progress
     * if you need it with some filter activities list
     * Alias for get_important_activities_by_users_and_compare_list()
     * @see get_important_activities_by_users_and_compare_list()
     *
     * @param object|numeric    $courseid
     * @param object|numeric    $user_or_id
     * @param array             $compare_list
     * @param bool              $unavailable_as_invisible - if true, than with false uservisible - return false, despite available info
     *
     * @return \cm_info[]|array
     */
    static public function get_important_activities_by_compare_list($courseid, $user_or_id=null, $compare_list=[], $unavailable_as_invisible=false){
        return static::get_important_activities_by_users_and_compare_list($courseid, $user_or_id, null, $compare_list,
            $unavailable_as_invisible);
    }

    /**
     * Use instead of get_gradable_activities to get activities for Student/Class progress
     * if you need it with some filter user list
     * Alias for get_important_activities_by_users_and_compare_list()
     * @see get_important_activities_by_users_and_compare_list()
     *
     * @param object|numeric   $courseid
     * @param object|numeric   $main_user_or_id          - main user, for whom cm will be loaded (global $USER by default)
     * @param array|int|object $users_or_ids             - list of users or userids (or single user/id)
     * @param bool             $unavailable_as_invisible - if true, than with false uservisible - return false, despite available info
     * @param bool             $rule_any                 - if true, check that at least one user can see activities, otherwise - that all user can see activity
     *
     * @return \cm_info[]|array
     */
    static public function get_important_activities_by_users($courseid, $main_user_or_id=null, $users_or_ids=[],
        $unavailable_as_invisible=false, $rule_any=true){
        return static::get_important_activities_by_users_and_compare_list($courseid, $main_user_or_id, $users_or_ids, null,
            $unavailable_as_invisible, $rule_any);
    }

    /**
     * Use instead of get_gradable_activities to get activities for Student/Class progress
     * if you need it with some filter user list by global $USER as main user (teacher)
     * Alias @see data_util::get_important_activities_by_users_and_compare_list()
     *
     * If you need to specify other main user, than global, you can use get_important_activities_by_users()
     * @see get_important_activities_by_users()
     *
     * @param object|numeric   $courseid
     * @param array|int|object $users_or_ids             - list of users or userids (or single user/id)
     * @param bool             $unavailable_as_invisible - if true, than with false uservisible - return false, despite available info
     * @param bool             $rule_any                 - if true, check that at least one user can see activities, otherwise - that all user can see activity
     *
     * @return \cm_info[]|array
     */
    static public function get_important_activities_for_users($courseid, $users_or_ids=[], $unavailable_as_invisible=false, $rule_any=true){
        return static::get_important_activities_by_users_and_compare_list($courseid, null, $users_or_ids, null,
            $unavailable_as_invisible, $rule_any);
    }

    /**
     * Use instead of get_gradable_activities to get activities for Student/Class progress
     *      if you need it with some filter user list and filter compare list
     *
     * @param object|numeric   $course_or_id
     * @param object|numeric   $main_user_or_id          - main user, for whom cm will be loaded (global $USER by default)
     * @param array|int|object $users_or_ids             - list of users or userids (or single user/id)
     * @param array|null       $compare_list             - you get empty result, if $compare_list is empty, use NULL to not check this variable
     * @param bool             $unavailable_as_invisible - if true, than with false uservisible - return false, despite available info
     * @param bool             $rule_any                 - if true, check that at least one user can see activities, otherwise - that all user can see activity
     *
     * @return \cm_info[]|array
     */
    static public function get_important_activities_by_users_and_compare_list($course_or_id, $main_user_or_id=null, $users_or_ids=[],
        $compare_list=null, $unavailable_as_invisible=false, $rule_any=true){
        $check_compare_list = !is_null($compare_list);
        $check_users = !empty($users_or_ids);
        if ($check_compare_list && empty($compare_list)){
            return [];
        }

        $activities = static::get_important_activities($course_or_id, $main_user_or_id, $unavailable_as_invisible);
        if (!$check_compare_list && !$check_users){
            return $activities;
        }

        $users_or_ids = static::val2arr($users_or_ids);
        $res = [];
        foreach ($activities as $key => $cm){
            if ($check_compare_list && !isset($compare_list[$cm->id])){
                continue;
            }

            $add = true;
            if ($check_users){
                $add = !$rule_any;
                foreach ($users_or_ids as $user_or_id){
                    if (static::get_cm_visibility_by_user($cm, $user_or_id, $unavailable_as_invisible, false)){
                        if ($rule_any){
                            $add = true;
                            break;
                        }
                    } elseif (!$rule_any){
                        $add = false;
                        break;
                    }
                }
            }

            if ($add){
                $res[$key] = $cm;
            }
        }

        return $res;
    }

    /**
     * Return visibility data by course modules (cms) and users
     * Activity visibility checks much faster (around 10 times) when users - in outer circle, so,
     *  if in your case users should be in the inner circle, there will be more quickly to get visibility data in separate loop,
     *  for example by this function.
     *
     * @param array|object[]|\cm_info[]|numeric[]   $cm_or_ids - list of kica items
     * @param array|object[]|numeric[]              $users_or_ids - list of users or users ids
     * @param bool $by_userid_cmid - (optional) if true, result array will be by userid and cmid
     * @param bool $unavailable_as_invisible - (optional) if true, than with false uservisible - return false, despite available info
     * @param bool $check_global_visibility - (optional) if true, return false, if loaded cm (or $USER) can't see this activity, despite $userid
     *
     *
     * @return array [$cmid => [$userid => true]] or [$userid => [$cmid => true]]
     */
    static public function get_cm_visibility_data($cm_or_ids, $users_or_ids, $by_userid_cmid=false,
        $unavailable_as_invisible=false, $check_global_visibility=false){
        $visibility_data = [];
        foreach ($users_or_ids as $user_or_id){
            $userid = static::get_id($user_or_id);
            foreach ($cm_or_ids as $cm_or_id){
                if (static::get_cm_visibility_by_user($cm_or_id, $user_or_id, $unavailable_as_invisible, $check_global_visibility)){
                    $cmid = static::get_id($cm_or_id);
                    if ($by_userid_cmid){
                        $visibility_data[$userid][$cmid] = true;
                    } else {
                        $visibility_data[$cmid][$userid] = true;
                    }
                }
            }
        }

        return $visibility_data;
    }

    /**
     * Get instance object
     *
     * @param numeric $instance_id id of activity in its table
     * @param string  $modname Name of module (not full frankenstyle) e.g. 'label'
     * @param numeric $courseid (optional) only for additional check
     *
     * @return object|null instance record from the activity table, or null if nothing found
     */
    static public function get_module_instance($instance_id, $modname, $courseid=null){
        if (empty($instance_id) || empty($modname)){
            return null;
        }

        $instance_id = (int)$instance_id;
        $keys = [$instance_id, $modname];
        $res = static::g_get(__FUNCTION__, $keys);
        if (is_null($res)){
            $res = false;
            $modnames = \get_module_types_names(true);
            if (isset($modnames[$modname])){
                $res = static::db()->get_record($modname, ['id' => $instance_id]);
            }

            static::g_set(__FUNCTION__, $keys, $res ?: false);
        }

        if ($courseid){
            $courseid = (int)$courseid;
            $res_courseid = (int)($res->courseid ?? ($res->course ?? 0));
            if ($res_courseid != $courseid){
                return null;
            }
        }

        return $res ?: null;
    }

    /**
     * @param int|\cm_info|object   $cm_or_id  - Id of course-module, or database object
     *
     * @return object|null
     */
    static public function get_module_instance_by_cm($cm_or_id){
        $cm = static::get_cm_by_cmorid($cm_or_id);
        if ($cm){
            return static::get_module_instance($cm->instance, $cm->modname, $cm->course);
        }

        return null;
    }

    /**
     * Get enabled manual enrol by courseid
     *
     * @param object|numeric    $course_or_id
     * @param numeric           $id - (optional) enrol id for additional check
     *
     * @return object|false
     */
    static public function enrol_get_manual_enrol_instances($course_or_id, $id=null){
        $courseid = static::get_id($course_or_id);
        $res = static::g_get(__FUNCTION__, [$courseid]);
        if (is_null($res)){
            $params = ['enrol' => 'manual', 'courseid' => $courseid, 'status' => ENROL_INSTANCE_ENABLED];
            if ($id){
                $params['id'] = $id;
            }

            $instances = static::db()->get_records('enrol', $params, 'sortorder, id');
            if (empty($instances)){
                $res = false;
            } else {
                $res = reset($instances);
            }
        } elseif ($res && $id){
            if ($res->id != $id){
                $res = false;
            }
        }

        return $res;
    }

    /**
     * Remove data which depends on specific course
     * It can be useful, when you check many courses, and need to remove data from the previous (already checked) courses
     *
     * @param string|string[] $selected_keys - you can choose keys to deleting, when empty - choose all of them
     *
     * @return void
     */
    static public function purge_course_depended_caches($selected_keys=[]){
        $default_keys = [
            'get_fast_modinfo',                 /** @see shared_lib::get_fast_modinfo() */
            'get_course_and_cm_from_cmid',      /** @see shared_lib::get_course_and_cm_from_cmid() */
            'get_course_activities',            /** @see shared_lib::get_course_activities() */
            'get_availability_info_module',     /** @see shared_lib::get_availability_info_module() */
            'get_course_groupids',              /** @see shared_lib::get_course_groupids() */
            'get_tags_by_cm',                   /** @see shared_lib::get_tags_by_cm() */
            '_get_visibility_data_by_cm_user',  /** @see shared_lib::_get_visibility_data_by_cm_user() */
            'get_important_activities',         /** @see shared_lib::get_important_activities() */
            'get_module_instance',              /** @see shared_lib::get_module_instance() */
        ];

        if (empty($selected_keys)){
            $keys = $default_keys;
        } else {
            $keys = [];
            $selected_keys = static::val2arr($selected_keys);
            foreach ($default_keys as $def_key){
                if (($selected_keys[$def_key] ?? false) || in_array($def_key, $selected_keys)){
                    $keys[] = $def_key;
                }
            }
        }

        static::g_remove_functions_data($keys);
    }
}
