<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Additional Settings Helper for the quizaccess_proctoring plugin.
 *
 * @package    quizaccess_proctoring
 * @copyright  2020 Brain Station 23
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

use quizaccess_proctoring\shared_lib as NED;

class addtional_settings_helper {
    /**
     * Search for specific user proctoring log.
     *
     * @param string $username The username of a user.
     * @param string $email The email of the user.
     * @param string $coursename The coursename.
     * @param string $quizname The quizname for the specific course.
     * @return array|\moodle_recordset
     */
    public function search(
        $username,
        $email,
        $coursename,
        $quizname
    ){
        global $DB;
        $params = [];
        $whereclausearray1 = [];
        $whereclausearray2 = [];

        if ($username !== ""){
            $namesplit = explode(" ", $username);
            $namelike1 = "(".$DB->sql_like('u.firstname', ':firstnamelike', false).")";
            $namelike2 = "(".$DB->sql_like('u.lastname', ':lastnamelike', false).")";
            $whereclausearray1[] = $namelike1;
            $whereclausearray2[] = $namelike2;

            if (count($namesplit) > 1){
                $params['firstnamelike'] = $namesplit[0];
                $params['lastnamelike'] = $namesplit[1];
            } else {
                $params['firstnamelike'] = $username;
                $params['lastnamelike'] = $username;
            }
        }

        if ($email !== ""){
            $emaillike1 = " ( ".$DB->sql_like('u.email', ':emaillike1', false)." ) ";
            if ($username !== ""){
                $emaillike2 = " ( ".$DB->sql_like('u.email', ':emaillike2', false)." ) ";
                $whereclausearray1[] = $emaillike1;
                $whereclausearray2[] = $emaillike2;
                $params['emaillike1'] = $email;
                $params['emaillike2'] = $email;
            } else {
                $whereclausearray1[] = $emaillike1;
                $params['emaillike1'] = $email;
            }
        }

        if ($coursename !== ""){
            $coursenamelike1 = " ( ".$DB->sql_like('c.fullname', ':coursenamelike1', false)." ) ";

            if ($username !== ""){
                $coursenamelike2 = " ( ".$DB->sql_like('c.fullname', ':coursenamelike2', false)." ) ";
                $whereclausearray1[] = $coursenamelike1;
                $whereclausearray2[] = $coursenamelike2;
                $params['coursenamelike1'] = $coursename;
                $params['coursenamelike2'] = $coursename;
            } else {
                $whereclausearray1[] = $coursenamelike1;
                $params['coursenamelike1'] = $coursename;
            }
        }

        if ($quizname !== ""){
            $quiznamelike1 = " ( ".$DB->sql_like('q.name', ':quiznamelike1', false)." ) ";
            if ($username !== ""){
                $quiznamelike2 = " ( ".$DB->sql_like('q.name', ':quiznamelike2', false)." ) ";
                $whereclausearray1[] = $quiznamelike1;
                $whereclausearray2[] = $quiznamelike2;
                $params['quiznamelike1'] = $quizname;
                $params['quiznamelike2'] = $quizname;
            } else {
                $whereclausearray1[] = $quiznamelike1;
                $params['quiznamelike1'] = $quizname;
            }
        }

        $totalclausecount = count($whereclausearray1) + count($whereclausearray2);
        $secondclausecount = count($whereclausearray2);

        if ($totalclausecount > 0){
            $andjoin1 = implode(" AND ", $whereclausearray1);
            if ($secondclausecount > 0){
                $andjoin2 = implode( " AND ", $whereclausearray2);
                $whereclause = " (".$andjoin1.") OR (".$andjoin2.") ";
            } else {
                $whereclause = " (".$andjoin1.")";
            }
        } else {
            // $sqlexecuted
            return [];
        }

        $sql = "SELECT"
            ." e.id as reportid, "
            ." e.userid as studentid, "
            ." e.webcampicture as webcampicture, "
            ." e.status as status, "
            ." e.cmid as cmid, "
            ." e.courseid as courseid, "
            ." e.timemodified as timemodified, "
            ." u.firstname as firstname, "
            ." u.lastname as lastname, "
            ." u.email as email, "
            ." c.fullname as coursename, "
            ." q.name as quizname "
            ." FROM {".NED::TABLE_LOG."} e "
            ." INNER JOIN {user} u  ON u.id = e.userid "
            ." INNER JOIN {course} c  ON c.id = e.courseid "
            ." INNER JOIN {course_modules} cm  ON cm.id = e.cmid "
            ." INNER JOIN {quiz} q  ON q.id = cm.instance "
            ." WHERE $whereclause ";

        return $DB->get_recordset_sql($sql, $params);
    }

    /**
     * search by course id.
     *
     * @param int $courseid The id of the course.
     *
     * @return \moodle_recordset
     */
    public function searchbycourseid ($courseid){
        global $DB;
        $sql = "SELECT *
            FROM {".NED::TABLE_LOG."} e
            WHERE e.courseid = :courseid";
        $params = [];
        $params['courseid'] = $courseid;
        return $DB->get_recordset_sql($sql, $params);
    }

    /**
     * search by quiz id.
     *
     * @param int $cmid The id of the course module.
     *
     * @return \moodle_recordset
     */
    public function search_by_cmid ($cmid){
        global $DB;
        $sql = "SELECT *
            FROM {".NED::TABLE_LOG."} e
            WHERE e.cmid = :cmid";
        $params = [];
        $params['cmid'] = $cmid;
        return $DB->get_recordset_sql($sql, $params);
    }

    /**
     * Get all data.
     *
     * @return \moodle_recordset
     */
    public function getalldata (){
        global $DB;
        $sql = "SELECT
        e.id as reportid,
        e.userid as studentid,
        e.webcampicture as webcampicture,
        e.status as status,
        e.cmid as cmid,
        e.courseid as courseid,
        e.timemodified as timemodified,
        u.firstname as firstname,
        u.lastname as lastname,
        u.email as email,
        c.fullname as coursename,
        q.name as quizname
        FROM {".NED::TABLE_LOG."} e
        INNER JOIN {user} u  ON u.id = e.userid
        INNER JOIN {course} c  ON c.id = e.courseid
        INNER JOIN {course_modules} cm  ON cm.id = e.cmid
        INNER JOIN {quiz} q  ON q.id = cm.instance";

        // Prepare data.
        return $DB->get_recordset_sql($sql);
    }

    /**
     * Delete logs
     *
     * @param string $deleteidstring The id of the quiz.
     * @return void
     */
    public function deletelogs ($deleteidstring){
        global $DB;
        $deleteids = explode(",", $deleteidstring);
        if (count($deleteids) > 0){
            // Get report rows.
            list($insql, $inparams) = $DB->get_in_or_equal($deleteids);
            $logs = $DB->get_records_select(NED::TABLE_LOG, "id $insql", $inparams);
            foreach ($logs as $row){
                $id = $row->id;
                $fileurl = $row->webcampicture;
                $patharray = explode("/", $fileurl);
                $filename = end($patharray);

                $DB->delete_records(NED::TABLE_WARNINGS, ['reportid' => $id]);
                $DB->delete_records(NED::TABLE_LOG, ['id' => $id]);

                $params = [
                    'component' => NED::PLUGIN_NAME,
                    'filearea' => 'picture',
                    'filename' => $filename,
                ];
                $usersfiles = $DB->get_records('files', $params);
                foreach ($usersfiles as $u_row){
                    $this->deletefile($u_row);
                }
            }
        }
    }

    /**
     * Delete file.
     *
     * @param object $filerow The id of the quiz.
     * @return void
     */
    public function deletefile ($filerow){
        $fs = get_file_storage();
        $fileinfo = [
                        'component' => 'quizaccess_proctoring',
                        'filearea' => 'picture',     // Usually = table name.
                        'itemid' => $filerow->itemid,               // Usually = ID of row in table.
                        'contextid' => $filerow->contextid, // ID of context.
                        'filepath' => '/',           // Any path beginning and ending in /.
                        'filename' => $filerow->filename
        ]; // Any filename.

        // Get file.
        $file = $fs->get_file($fileinfo['contextid'], $fileinfo['component'], $fileinfo['filearea'],
        $fileinfo['itemid'], $fileinfo['filepath'], $fileinfo['filename']);

        // Delete it if it exists.
        if ($file){
            $file->delete();
        }
    }

    /**
     * search by course id.
     *
     * @param int $courseid The id of the course.
     * @return array
     */
    public function searchssbycourseid ($courseid){
        return NED::db()->get_records(NED::TABLE_SCREENSHOT, ['courseid' => $courseid]);
    }

    /**
     * search by cm id.
     *
     * @param int $cmid The id of the course module.
     * @return array
     */
    public function search_ss_by_cmid ($cmid){
        return NED::db()->get_records(NED::TABLE_SCREENSHOT, ['cmid' => $cmid]);
    }


    /**
     * Delete logs
     *
     * @param string $deleteidstring The id of the quiz.
     * @return void
     */
    public function deletesslogs ($deleteidstring){
        global $DB;
        $deleteids = explode(",", $deleteidstring);
        if (count($deleteids) > 0){
            // Get report rows.
            list($insql, $inparams) = $DB->get_in_or_equal($deleteids);
            $logs = NED::db()->get_records_select(NED::TABLE_SCREENSHOT, "id $insql", $inparams);

            foreach ($logs as $row){
                $id = $row->id;
                $fileurl = $row->screenshot;
                $patharray = explode("/", $fileurl);
                $filename = end($patharray);

                $DB->delete_records(NED::TABLE_SCREENSHOT, ['id' => $id]);
                $params = [
                    'component' => NED::PLUGIN_NAME,
                    'filearea' => 'picture',
                    'filename' => $filename,
                ];
                $usersfiles = $DB->get_records('files', $params);
                foreach ($usersfiles as $u_row){
                    $this->deletefile($u_row);
                }
            }
        }
    }

}
