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
 * Lib for the quizaccess_proctoring plugin.
 *
 * @package    quizaccess_proctoring
 * @copyright  2020 Brain Station 23
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later.
 */


defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot . '/local/aws/sdk/aws-autoloader.php');

use quizaccess_proctoring\shared_lib as NED;
use Aws\Rekognition\RekognitionClient;
/**
 * Serve the files.
 *
 * @param stdClass $course the course object.
 * @param stdClass $cm the course module object.
 * @param context $context the context.
 * @param string $filearea the name of the file area.
 * @param array $args extra arguments (itemid, path).
 * @param bool $forcedownload whether or not force download.
 * @param array $options additional options affecting the file serving.
 * @return bool false if the file not found, just send the file otherwise and do not return anything.
 */
function quizaccess_proctoring_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {

    $itemid = array_shift($args);
    $filename = array_pop($args);

    if (!$args) {
        $filepath = '/';
    } else {
        $filepath = '/' .implode('/', $args) . '/';
    }

    $fs = get_file_storage();

    $file = $fs->get_file($context->id, 'quizaccess_proctoring', $filearea, $itemid, $filepath, $filename);
    if (!$file) {
        return false;
    }
    send_stored_file($file, 0, 0, $forcedownload, $options);
}

/**
 * Updates match result
 *
 * @param int $rowid the reportid.
 * @param String $matchresult similarity.
 * @return array Similaritycheck.
 */
function update_match_result($rowid, $matchresult) {
    global $DB;
    $log = $DB->get_record(NED::TABLE_LOG, ['id' => $rowid]);
    if ($log){
        $log->awsflag = 2;
        $log->awsscore = (int)$matchresult;
        $DB->update_record(NED::TABLE_LOG, $log);
    }
}


/**
 * Returns face match similarity.
 *
 * @param String $referenceimageurl the courseid.
 * @param String $targetimageurl the course module id.
 * @return array Similaritycheck.
 */
function check_similarity_aws($referenceimageurl, $targetimageurl) {
    global $DB;
    $awskey = NED::get_config('awskey');
    $awssecret = NED::get_config('awssecret');
    $threshhold = (int)NED::get_config('awsfcthreshold');

    $credentials = new Aws\Credentials\Credentials($awskey, $awssecret);
    $rekognitionclient = new RekognitionClient([
        'region'    => "us-east-1",
        'version'   => 'latest',
        'credentials' => $credentials
    ]);

    try {
        $comparefaceresult = $rekognitionclient->compareFaces([
            'SimilarityThreshold' => $threshhold,
            'SourceImage' => [
                'Bytes' => file_get_contents($referenceimageurl)
            ],
            'TargetImage' => [
                'Bytes' => file_get_contents($targetimageurl)
            ],
        ]);
        $facematchresult = $comparefaceresult['FaceMatches'];
        return $facematchresult;
    } catch (Exception $e) {
         $similarity = array();
         return $similarity;
    }
}

/**
 * Execute facerecognition task.
 */
function execute_fm_task() {
    global $DB;
    // Get 5 task.
    $data = $DB->get_recordset(NED::TABLE_FACEMATCH, [], '', '*', 0, 5);
    $facematchmethod = NED::get_config("fcmethod");
    foreach ($data as $row){
        $rowid = $row->id;
        $reportid = $row->reportid;
        $refimageurl = $row->refimageurl;
        $targetimageurl = $row->targetimageurl;
        if ($facematchmethod == "AWS"){
            // Get Match result.
            $similarityresult = check_similarity_aws($refimageurl, $targetimageurl);

            // Log AWS API Call.
            $apiresponse = json_encode($similarityresult);
            log_aws_api_call($reportid, $apiresponse);

            // Update Match result.
            if (count($similarityresult) > 0) {
                if (isset($similarityresult[0]['Similarity'])) {
                    $similarity = $similarityresult[0]['Similarity'];
                } else {
                    $similarity = 0;
                    log_fm_warning($reportid);
                }
            } else {
                $similarity = 0;
                log_fm_warning($reportid);
            }
            update_match_result($reportid, $similarity);
            // Delete from task table.
            $DB->delete_records(NED::TABLE_FACEMATCH, ['id' => $rowid]);
        } elseif ($facematchmethod == "BS"){
            $similarityresult = check_similarity_bs($refimageurl, $targetimageurl);
            $jsonarray = json_decode($similarityresult, true);
            // Update Match result.
            if (isset($jsonarray['process']) && isset($jsonarray['facematched'])) {
                if ($jsonarray['facematched'] == "True") {
                    $similarity = 100;
                } else {
                    $similarity = 0;
                    log_fm_warning($reportid);
                }
            } else {
                $similarity = 0;
                log_fm_warning($reportid);
            }
            update_match_result($reportid, $similarity);
            // Delete from task table.
            $DB->delete_records(NED::TABLE_FACEMATCH, ['id' => $rowid]);
        } else {
            echo "Invalid fc method<br/>";
        }
    }
}

/**
 * Execute facerecognition task.
 *
 * @return bool false if no record found.
 */
function log_facematch_task() {
    global $DB;
    $data = $DB->get_recordset(NED::TABLE_LOG, ['awsflag' => 0], '', "DISTINCT courseid, cmid, userid");
    foreach ($data as $row) {
        $courseid = $row->courseid;
        $cmid = $row->cmid;
        $userid = $row->userid;
        log_specific_quiz($courseid, $cmid, $userid);
    }

    echo "Log success";
}

/**
 * Log for analysis.
 *
 * @param int $courseid the courseid.
 * @param int $cmid the course module id.
 * @param int $studentid the context.
 * @return bool false if no record found.
 */
function log_specific_quiz($courseid, $cmid, $studentid) {
    global $DB;
    // Get user profile image.
    $user = core_user::get_user($studentid);
    $profileimageurl = "";
    if ($user->picture) {
        $profileimageurl = new moodle_url('/user/pix.php/'.$user->id.'/f1.jpg');
    }
    // Update all as attempted.
    $DB->set_field(NED::TABLE_LOG, 'awsflag', '1', ['courseid' => $courseid, 'cmid' => $cmid, 'userid' => $studentid]);

    // Check random.
    $limit = 5;
    $awschecknumber = NED::get_config('awschecknumber');
    if ($awschecknumber != "") {
        $limit = (int) $awschecknumber;
    }

    if ($limit == -1) {
        $sql = " SELECT e.id as reportid, e.userid as studentid, e.webcampicture as webcampicture, e.status as status, "
        ." e.timemodified as timemodified, u.firstname as firstname, u.lastname as lastname, u.email as email "
        ." FROM {".NED::TABLE_LOG."} e INNER JOIN {user} u  ON u.id = e.userid "
        ." WHERE e.courseid = '$courseid' AND e.cmid = '$cmid' AND u.id = '$studentid' AND e.webcampicture != '' ";
    } else if ($limit > 0) {
        $sql = " SELECT e.id as reportid, e.userid as studentid, e.webcampicture as webcampicture, e.status as status, "
        ." e.timemodified as timemodified, u.firstname as firstname, u.lastname as lastname, u.email as email "
        ." FROM {".NED::TABLE_LOG."} e INNER JOIN {user} u  ON u.id = e.userid "
        ." WHERE e.courseid = '$courseid' AND e.cmid = '$cmid' AND u.id = '$studentid' AND e.webcampicture != '' "
        ." ORDER BY RAND() "
        ." LIMIT $limit ";
    } else {
        $sql = " SELECT e.id as reportid, e.userid as studentid, e.webcampicture as webcampicture, e.status as status, "
        ." e.timemodified as timemodified, u.firstname as firstname, u.lastname as lastname, u.email as email "
        ." FROM {".NED::TABLE_LOG."} e INNER JOIN {user} u  ON u.id = e.userid "
        ." WHERE e.courseid = '$courseid' AND e.cmid = '$cmid' AND u.id = '$studentid' AND e.webcampicture != ''";
    }

    $sqlexecuted = $DB->get_recordset_sql($sql);

    foreach ($sqlexecuted as $row) {
        $reportid = $row->reportid;
        $snapshot = $row->webcampicture;
        echo $snapshot;
        if ($snapshot != "") {
            $inserttaskrow = new stdClass();
            $inserttaskrow->refimageurl = $profileimageurl->__toString();
            $inserttaskrow->targetimageurl = $snapshot;
            $inserttaskrow->reportid = $reportid;
            $inserttaskrow->timemodified = time();
            $DB->insert_record(NED::TABLE_FACEMATCH, $inserttaskrow);
        }
    }
    return true;
}

/**
 * Analyze specific Quiz images.
 *
 * @param int $courseid the courseid.
 * @param int $cmid the course module id.
 * @param int $studentid the context.
 * @return bool false if no record found.
 */
function aws_analyze_specific_quiz($courseid, $cmid, $studentid) {
    global $DB;
    // Get user profile image.
    $user = core_user::get_user($studentid);
    $profileimageurl = "";
    if ($user->picture) {
        $profileimageurl = new moodle_url('/user/pix.php/'.$user->id.'/f1.jpg');
    }
    // Update all as attempted.
    $DB->set_field(NED::TABLE_LOG, 'awsflag', '1', ['courseid' => $courseid, 'cmid' => $cmid, 'userid' => $studentid, 'awsflag' => 0]);

    // Check random.
    $limit = 5;
    $awschecknumber = NED::get_config('awschecknumber');
    if ($awschecknumber != "") {
        $limit = (int)$awschecknumber;
    }

    if ($limit == -1) {
        $sql = " SELECT e.id as reportid, e.userid as studentid, e.webcampicture as webcampicture, e.status as status, "
        ." e.timemodified as timemodified, u.firstname as firstname, u.lastname as lastname, u.email as email "
        ." FROM {".NED::TABLE_LOG."} e INNER JOIN {user} u  ON u.id = e.userid "
        ." WHERE e.courseid = '$courseid' AND e.cmid = '$cmid' AND u.id = '$studentid' AND e.webcampicture != '' ";
    } else if ($limit > 0) {
        $sql = " SELECT e.id as reportid, e.userid as studentid, e.webcampicture as webcampicture, e.status as status, "
        ." e.timemodified as timemodified, u.firstname as firstname, u.lastname as lastname, u.email as email "
        ." FROM {".NED::TABLE_LOG."} e INNER JOIN {user} u  ON u.id = e.userid "
        ." WHERE e.courseid = '$courseid' AND e.cmid = '$cmid' AND u.id = '$studentid' AND e.webcampicture != '' "
        ." ORDER BY RAND() "
        ." LIMIT $limit";
    } else {
        $sql = " SELECT e.id as reportid, e.userid as studentid, e.webcampicture as webcampicture, e.status as status, "
        ." e.timemodified as timemodified, u.firstname as firstname, u.lastname as lastname, u.email as email "
        ." FROM {".NED::TABLE_LOG."} e INNER JOIN {user} u  ON u.id = e.userid "
        ." WHERE e.courseid = '$courseid' AND e.cmid = '$cmid' AND u.id = '$studentid' AND e.webcampicture != ''";
    }

    $sqlexecuted = $DB->get_recordset_sql($sql);

    foreach ($sqlexecuted as $row) {
        $reportid = $row->reportid;
        $refimageurl = $profileimageurl->__toString();
        $targetimageurl = $row->webcampicture;
        $similarityresult = check_similarity_aws($refimageurl, $targetimageurl);
        // Log AWS API Call.
        $apiresponse = json_encode($similarityresult);
        log_aws_api_call($reportid, $apiresponse);

        // Update Match result.
        if (count($similarityresult) > 0) {
            if (isset($similarityresult[0]['Similarity'])) {
                $similarity = $similarityresult[0]['Similarity'];
            } else {
                $similarity = 0;
                log_fm_warning($reportid);
            }
        } else {
            $similarity = 0;
            log_fm_warning($reportid);
        }
        update_match_result($reportid, $similarity);

    }
    return true;
}

/**
 * Analyze specific Quiz images.
 *
 * @param int $courseid the courseid.
 * @param int $cmid the course module id.
 * @param int $studentid the context.
 * @return bool false if no record found.
 */
function bs_analyze_specific_quiz($courseid, $cmid, $studentid) {
    global $DB;
    // Get user profile image.
    $user = core_user::get_user($studentid);
    $profileimageurl = "";
    if ($user->picture) {
        $profileimageurl = new moodle_url('/user/pix.php/'.$user->id.'/f1.jpg');
    }
    // Update all as attempted.
    $DB->set_field(NED::TABLE_LOG, 'awsflag', '1', ['courseid' => $courseid, 'cmid' => $cmid, 'userid' => $studentid, 'awsflag' => 0]);

    // Check random.
    $limit = 5;
    $awschecknumber = NED::get_config('awschecknumber');
    if ($awschecknumber != "") {
        $limit = (int)$awschecknumber;
    }

    if ($limit == -1) {
        $sql = "SELECT e.id as reportid, e.userid as studentid, e.webcampicture as webcampicture, e.status as status,
        e.timemodified as timemodified, u.firstname as firstname, u.lastname as lastname, u.email as email
        FROM {".NED::TABLE_LOG."} e INNER JOIN {user} u  ON u.id = e.userid
        WHERE e.courseid = '$courseid' AND e.cmid = '$cmid' AND u.id = '$studentid' AND e.webcampicture != ''";
    } else if ($limit > 0) {
        $sql = "SELECT e.id as reportid, e.userid as studentid, e.webcampicture as webcampicture, e.status as status,
        e.timemodified as timemodified, u.firstname as firstname, u.lastname as lastname, u.email as email
        FROM {".NED::TABLE_LOG."} e INNER JOIN {user} u  ON u.id = e.userid
        WHERE e.courseid = '$courseid' AND e.cmid = '$cmid' AND u.id = '$studentid' AND e.webcampicture != ''
        ORDER BY RAND()
        LIMIT $limit";
    } else {
        $sql = "SELECT e.id as reportid, e.userid as studentid, e.webcampicture as webcampicture, e.status as status,
        e.timemodified as timemodified, u.firstname as firstname, u.lastname as lastname, u.email as email
        FROM {".NED::TABLE_LOG."} e INNER JOIN {user} u  ON u.id = e.userid
        WHERE e.courseid = '$courseid' AND e.cmid = '$cmid' AND u.id = '$studentid' AND e.webcampicture != ''";
    }

    $sqlexecuted = $DB->get_recordset_sql($sql);

    foreach ($sqlexecuted as $row) {
        $reportid = $row->reportid;
        $refimageurl = $profileimageurl->__toString();
        $targetimageurl = $row->webcampicture;
        $similarityresult = check_similarity_bs($refimageurl, $targetimageurl);
        $jsonarray = json_decode($similarityresult, true);

        // Update Match result.
        if (isset($jsonarray['process']) && isset($jsonarray['facematched'])) {
            if ($jsonarray['facematched'] == "True") {
                $similarity = 100;
            } else {
                $similarity = 0;
                log_fm_warning($reportid);
            }
        } else {
            $similarity = 0;
            log_fm_warning($reportid);
        }
        update_match_result($reportid, $similarity);

    }
    return true;
}

/**
 * Analyze specific image.
 *
 * @param int $reportid the context.
 * @return bool false if no record found.
 */
function aws_analyze_specific_image($reportid) {
    global $DB;
    $reportdata = $DB->get_record(NED::TABLE_LOG, ['id' => $reportid], "id, courseid, cmid, userid, webcampicture");

    if ($reportdata) {
        $studentid = $reportdata->userid;
        $courseid = $reportdata->courseid;
        $cmid = $reportdata->cmid;
        $targetimage = $reportdata->webcampicture;

        // Get user profile image.
        $user = core_user::get_user($studentid);
        $profileimageurl = "";
        if ($user->picture) {
            $profileimageurl = new moodle_url('/user/pix.php/'.$user->id.'/f1.jpg');
        }
        // Update all as attempted.
        $DB->set_field(NED::TABLE_LOG, 'awsflag', '1', ['courseid' => $courseid, 'cmid' => $cmid, 'userid' => $studentid, 'awsflag' => 0]);

        $similarityresult = check_similarity_aws($profileimageurl, $targetimage);
        // Log AWS API Call.
        $apiresponse = json_encode($similarityresult);
        log_aws_api_call($reportid, $apiresponse);

        // Update Match result.
        if (count($similarityresult) > 0) {
            if (isset($similarityresult[0]['Similarity'])) {
                $similarity = $similarityresult[0]['Similarity'];
            } else {
                $similarity = 0;
                log_fm_warning($reportid);
            }
        } else {
            $similarity = 0;
            log_fm_warning($reportid);
        }
        update_match_result($reportid, $similarity);
    }
    return true;
}

/**
 * Analyze specific image.
 *
 * @param int $reportid the context.
 * @return bool false if no record found.
 */
function bs_analyze_specific_image($reportid) {
    global $DB;
    $reportdata = $DB->get_record(NED::TABLE_LOG, ['id' => $reportid], "id, courseid, cmid, userid, webcampicture");

    if ($reportdata) {
        $studentid = $reportdata->userid;
        $courseid = $reportdata->courseid;
        $cmid = $reportdata->cmid;
        $targetimage = $reportdata->webcampicture;

        // Get user profile image.
        $user = core_user::get_user($studentid);
        $profileimageurl = "";
        if ($user->picture) {
            $profileimageurl = new moodle_url('/user/pix.php/'.$user->id.'/f1.jpg');
        }
        // Update all as attempted.
        $DB->set_field(NED::TABLE_LOG, 'awsflag', '1', ['courseid' => $courseid, 'cmid' => $cmid, 'userid' => $studentid, 'awsflag' => 0]);

        $similarityresult = check_similarity_bs($profileimageurl, $targetimage);
        $jsonarray = json_decode($similarityresult, true);

        // Update Match result.
        if (isset($jsonarray['process']) && isset($jsonarray['facematched'])) {
            if ($jsonarray['facematched'] == "True") {
                $similarity = 100;
            } else {
                $similarity = 0;
                log_fm_warning($reportid);
            }
        } else {
            $similarity = 0;
            log_fm_warning($reportid);
        }

        update_match_result($reportid, $similarity);
    }
    return true;
}

/**
 * Analyze specific image.
 *
 * @param int $reportid the context.
 * @param string $apiresponse the context.
 * @return bool false if no record found.
 */
function log_aws_api_call($reportid, $apiresponse) {
    global $DB;
    $log = new stdClass();
    $log->reportid = $reportid;
    $log->apiresponse = $apiresponse;
    $log->timecreated = time();

    return $DB->insert_record(NED::TABLE_AWS, $log);
}

/**
 * Returns face match similarity.
 *
 * @param String $referenceimageurl the courseid.
 * @param String $targetimageurl the course module id.
 * @return array Similaritycheck.
 */
function check_similarity_bs($referenceimageurl, $targetimageurl) {
    global $CFG;
    $bsapi = NED::get_config('bsapi');
    $bstoken = NED::get_config('bstoken');

    // Load File.
    $image1 = basename($referenceimageurl);
    file_put_contents( $CFG->dataroot ."/temp/".$image1, file_get_contents($referenceimageurl));
    $image2 = basename($targetimageurl);
    file_put_contents( $CFG->dataroot ."/temp/".$image2, file_get_contents($targetimageurl));

    // Check similarity.
    $curl = curl_init();
    curl_setopt_array($curl, array(
        CURLOPT_URL => $bsapi,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 0,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => 'POST',
        CURLOPT_POSTFIELDS => array('image1' => new CURLFILE($CFG->dataroot ."/temp/".$image1),
        'image2' => new CURLFILE($CFG->dataroot ."/temp/".$image2),
        'token' => $bstoken)
    ));
    $response = curl_exec($curl);
    curl_close($curl);

    // Clear File.
    unlink($CFG->dataroot ."/temp/".$image1);
    unlink($CFG->dataroot ."/temp/".$image2);
    return $response;
}

/**
 * Log fm warnings.
 *
 * @param String $reportid the reportid.
 * @return void.
 */
function log_fm_warning($reportid) {
    global $DB;
    $reportdata = $DB->get_record(NED::TABLE_LOG, ['id' => $reportid]);

    if ($reportdata) {
        $userid = $reportdata->userid;
        $courseid = $reportdata->courseid;
        $cmid = $reportdata->cmid;

        // Check availability.
        $params = ['userid' => $userid, 'courseid' => $courseid, 'cmid' => $cmid];
        $warnings = $DB->get_record(NED::TABLE_WARNINGS, $params);

        // If does not exists.
        if (!$warnings) {
            $params['reportid'] = $reportid;
            $DB->insert_record(NED::TABLE_WARNINGS, (object)$params);
        }
    }
}
