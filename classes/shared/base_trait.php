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
 * Trait base_trait
 *
 * @package quizaccess_proctoring\shared
 *
 * Resolve trait conflicts here.
 * WARNING: When create class with this trait, don't forget use init() method
 *
 * @example of using
 *         class CLASSNAME extends base_class {
 *              use base_trait;
 *         }
 *
 */
trait base_trait {
    use plugin_dependencies, util,
        moodle_util, db_util, data_util,
        pd_util,
        output;
}
