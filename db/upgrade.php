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
 * This file keeps track of upgrades to Moodle.
 *
 * Sometimes, changes between versions involve
 * alterations to database structures and other
 * major things that may break installations.
 *
 * The upgrade function in this file will attempt
 * to perform all the necessary actions to upgrade
 * your older installation to the current version.
 *
 * If there's something it cannot do itself, it
 * will tell you what you need to do.
 *
 * The commands in here will all be database-neutral,
 * using the methods of database_manager class
 *
 * Please do not forget to use upgrade_set_timeout()
 * before any action that may take longer time to finish.
 *
 * @package   core_install
 * @category  upgrade
 * @copyright 2006 onwards Martin Dougiamas  http://dougiamas.com
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use quizaccess_proctoring\shared_lib as NED;

/**
 * Main upgrade tasks to be executed on Moodle version bump
 *
 * This function is automatically executed after one bump in the Moodle core
 * version is detected. It's in charge of performing the required tasks
 * to raise core from the previous version to the next one.
 *
 * It's a collection of ordered blocks of code, named "upgrade steps",
 * each one performing one isolated (from the rest of steps) task. Usually
 * tasks involve creating new DB objects or performing manipulation of the
 * information for cleanup/fixup purposes.
 *
 * Each upgrade step has a fixed structure, that can be summarised as follows:
 *
 * if ($oldversion < XXXXXXXXXX.XX) {
 *     // Explanation of the update step, linking to issue in the Tracker if necessary
 *     upgrade_set_timeout(XX); // Optional for big tasks
 *     // Code to execute goes here, usually the XMLDB Editor will
 *     // help you here. See {@link http://docs.moodle.org/dev/XMLDB_editor}.
 *     upgrade_main_savepoint(true, XXXXXXXXXX.XX);
 * }
 *
 * All plugins within Moodle (modules, blocks, reports...) support the existence of
 * their own upgrade.php file, using the "Frankenstyle" component name as
 * defined at {@link http://docs.moodle.org/dev/Frankenstyle}, for example:
 *     - {@link xmldb_page_upgrade($oldversion)}. (modules don't require the plugintype ("mod_") to be used.
 *     - {@link xmldb_auth_manual_upgrade($oldversion)}.
 *     - {@link xmldb_workshopform_accumulative_upgrade($oldversion)}.
 *     - ....
 *
 * In order to keep the contents of this file reduced, it's allowed to create some helper
 * functions to be used here in the {@link upgradelib.php} file at the same directory. Note
 * that such a file must be manually included from upgrade.php, and there are some restrictions
 * about what can be used within it.
 *
 * For more information, take a look to the documentation available:
 *     - Data definition API: {@link http://docs.moodle.org/dev/Data_definition_API}
 *     - Upgrade API: {@link http://docs.moodle.org/dev/Upgrade_API}
 *
 * @param int $oldversion
 *
 * @return bool always true
 * @noinspection PhpUnused
 */

function xmldb_quizaccess_proctoring_upgrade($oldversion) {
    global $CFG, $DB;

    require_once($CFG->libdir.'/db/upgradelib.php'); // Core Upgrade-related functions.
    $dbman = $DB->get_manager(); // Loads ddl manager and xmldb classes.
    $plugin_savepoint = function($version, $result=true){
        upgrade_plugin_savepoint($result, $version, 'quizaccess', 'proctoring');
    };

    if ($oldversion < 2021061102) {
        // Define field output to be added to task_log.
        $table = new xmldb_table(NED::TABLE_LOG);
        $field1 = new xmldb_field('awsscore', XMLDB_TYPE_INTEGER, '10', null, true, false, 0, null);
        $field2 = new xmldb_field('awsflag', XMLDB_TYPE_INTEGER, '10', null, true, false, 0, null);

        // Conditionally launch add field forcedownload.
        if (!$dbman->field_exists($table, $field1)) {
            $dbman->add_field($table, $field1);
        }

        if (!$dbman->field_exists($table, $field2)) {
            $dbman->add_field($table, $field2);
        }

        $plugin_savepoint(2021061102);
    }

    if ($oldversion < 2021061104) {
        // Define field output to be added to task_log.
        $table = new xmldb_table(NED::TABLE_FACEMATCH);
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, true, true, null, null);
        $table->add_field('refimageurl', XMLDB_TYPE_TEXT, '500', null, true, false, null, null);
        $table->add_field('targetimageurl', XMLDB_TYPE_TEXT, '500', null, true, false, null, null);
        $table->add_field('reportid', XMLDB_TYPE_INTEGER, '10', null, true, false, 0, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, true, false, 0, null);
        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        // Conditionally launch create table for fees.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }
        $plugin_savepoint(2021061104);
    }

    if ($oldversion < 2021061106) {
        // Define field output to be added to task_log.
        $table = new xmldb_table(NED::TABLE_SCREENSHOT);
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, true, true, null, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, true, false, 0, null);
        $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, true, false, 0, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, true, false, 0, null);
        $table->add_field('screenshot', XMLDB_TYPE_TEXT, '10', null, true, false, null, null);
        $table->add_field('status', XMLDB_TYPE_INTEGER, '10', null, true, false, 0, null);
        $table->add_field('timemodified', XMLDB_TYPE_INTEGER, '10', null, true, false, 0, null);

        $table->add_key('id', XMLDB_KEY_PRIMARY, array('id'));

        if (!$dbman->table_exists($table)){
            $dbman->create_table($table);
        }

        $plugin_savepoint(2021061106);
    }

    if ($oldversion < 2021070702) {
        // Define field output to be added to task_log.
        $table = new xmldb_table(NED::TABLE_AWS);
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, true, true, null, null);
        $table->add_field('reportid', XMLDB_TYPE_INTEGER, '10', null, true, false, 0, null);
        $table->add_field('apiresponse', XMLDB_TYPE_TEXT, '1000', null, true, false, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, true, false, 0, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        // Conditionally launch create table for fees.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $plugin_savepoint(2021070702);
    }

    if ($oldversion < 2021071405) {
        // Define field output to be added to task_log.
        $table = new xmldb_table(NED::TABLE_WARNINGS);
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, true, true, null, null);
        $table->add_field('reportid', XMLDB_TYPE_INTEGER, '10', null, true, false, 0, null);
        $table->add_field('courseid', XMLDB_TYPE_INTEGER, '10', null, true, false, 0, null);
        $table->add_field('cmid', XMLDB_TYPE_INTEGER, '10', null, true, false, 0, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, true, false, 0, null);

        $table->add_key('primary', XMLDB_KEY_PRIMARY, array('id'));
        // Conditionally launch create table for fees.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        $plugin_savepoint(2021071405);
    }


    if ($oldversion < 2022042903){
        // Fix wrong definition of the plugin as mod_* plugin type
        $mod_proctoring_name = 'mod_quizaccess_proctoring';
        $clear_mod_data = false;
        // check, that such plugin really doesn't exist;
        $mod_proctoring_info = \core_plugin_manager::instance()->get_plugin_info($mod_proctoring_name);
        if ($mod_proctoring_info){
            switch ($mod_proctoring_info->get_status()){
                case core_plugin_manager::PLUGIN_STATUS_NODB:
                case core_plugin_manager::PLUGIN_STATUS_DELETE:
                case core_plugin_manager::PLUGIN_STATUS_MISSING:
                    $clear_mod_data = true;
                    break;
            }
        } else {
            $clear_mod_data = true;
        }

        if ($clear_mod_data){
                $DB->delete_records('upgrade_log', ['plugin' => $mod_proctoring_name]);
                $DB->delete_records('config_plugins', ['plugin' => $mod_proctoring_name]);
        }

        $plugin_savepoint(2022042903);
    }

    if ($oldversion < 2022060600){
        $rename_tables = [
            'proctoring_screenshot_logs' => NED::TABLE_SCREENSHOT,
            'proctoring_fm_warnings' => NED::TABLE_WARNINGS,
            'proctoring_facematch_task' => NED::TABLE_FACEMATCH,
            'aws_api_log' => NED::TABLE_AWS,
        ];
        foreach ($rename_tables as $old_name => $new_name){
            if ($old_name == $new_name) continue;
            if (!$dbman->table_exists($old_name) || $dbman->table_exists($new_name)) continue;

            $table = new xmldb_table($old_name);
            $dbman->rename_table($table, $new_name);
        }

        $tables_rename_quizid = [NED::TABLE_LOG, NED::TABLE_SCREENSHOT, NED::TABLE_WARNINGS];
        $old_field_name = 'quizid';
        foreach ($tables_rename_quizid as $table_name){
            if (!$dbman->table_exists($table_name) || !$dbman->field_exists($table_name, $old_field_name)) continue;

            $table = new xmldb_table($table_name);
            $field = new xmldb_field($old_field_name, XMLDB_TYPE_INTEGER, '10', null, true, false, 0, null);
            $dbman->rename_field($table, $field, 'cmid');
        }

        $plugin_savepoint(2022060600);
    }

    return true;
}
