<?php
/**
 * @package    quizaccess_proctoring
 * @subpackage shared
 * @category   NED
 * @copyright  2021 NED {@link http://ned.ca}
 * @author     NED {@link http://ned.ca}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace quizaccess_proctoring\shared;

defined('MOODLE_INTERNAL') || die();


/**
 * Interface C
 *
 * @package quizaccess_proctoring\shared
 *
 * Be careful with redeclaration constants from "C" in child classes,
 *  as it will create conflicts with other trait methods (which use "raw" constants from "C" file)
 */
interface C {

    const NOTIFY_INFO = \core\output\notification::NOTIFY_INFO;
    const NOTIFY_SUCCESS = \core\output\notification::NOTIFY_SUCCESS;
    const NOTIFY_WARNING = \core\output\notification::NOTIFY_WARNING;
    const NOTIFY_ERROR = \core\output\notification::NOTIFY_ERROR;

    const E_NONE = 0;       // nothing
    const E_NOTICE = 1;     // show moodle notice
    const E_WARNING = 2;    // show php warning
    const E_ERROR = 3;      // stop script execution

    // SQL
    const SQL_NOW = "UNIX_TIMESTAMP()";
    const SQL_NONE_COND = '0<>0';
    const SQL_TRUE_COND = '1=1';

    // HTML
     const HTML_INVISIBLE = '&#8205;';
     const HTML_SPACE = '&nbsp;';
}
