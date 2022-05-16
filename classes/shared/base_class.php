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
 * Class base_class
 *
 * @package quizaccess_proctoring\shared
 *
 * This class need for global data, which will be the same for all child classes
 * You can add other properties with the same intentions - but its should not conflict with other traits!
 *
 * Avoid using traits with own properties (as plugin_dependencies) - them should be in the base_trait.
 *
 * Be careful with redeclaration constants from "C" here or in child classes,
 *  as it will create conflicts with other trait methods (which use "raw" constants from "C" file)
 *
 * @example of using
 *         class CLASSNAME extends base_class {
 *              use base_trait;
 *         }
 *
 */
abstract class base_class implements C{
   use global_util;

   static protected $_global_data = [];
}
