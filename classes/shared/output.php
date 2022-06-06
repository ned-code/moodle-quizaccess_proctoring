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
 * Trait output
 *
 * @package quizaccess_proctoring\shared
 */
trait output {
    use plugin_dependencies, util;

    /**
     * Get global $OUTPUT
     *
     * @return \theme_boost\output\core_renderer|\bootstrap_renderer|\core_renderer|object
     */
    static public function O(){
        global $OUTPUT;
        return $OUTPUT;
    }

    /**
     * alias for O()
     * @see O()
     *
     * @return \theme_boost\output\core_renderer|\bootstrap_renderer|\core_renderer|object
     */
    static public function output(){
        return static::O();
    }

    /**
     * Get global $PAGE
     *
     * @return \moodle_page|object
     */
    static public function page(){
        global $PAGE;
        return $PAGE;
    }

    /**
     * Get $PAGE title and url
     * You need to call require_login() or $PAGE->set_context() before calling this method
     *
     * @param string                $title - string title or lang key of the current plugin
     * @param \moodle_url|string    $url - URL relative to $CFG->wwwroot or {@link moodle_url} instance
     *
     * @return void
     */
    static public function page_set_title($title='', $url=null){
        global $FULLME;
        $P = static::page();
        $title = static::str_check($title);
        $url = $url ?? $FULLME;

        $P->set_url($url);
        $P->set_title($title);
        $P->set_heading($title);

        $active_node = $P->settingsnav->find_active_node();
        if (!$active_node || $active_node->text != $title){
            $P->navbar->add($title, $url);
        }
    }

    /**
     * Render something through the global $OUTPUT
     *
     * @param mixed ...$args
     *
     * @return string
     */
    static public function render(...$args){
        return static::O()->render(...$args);
    }

    /**
     * Returns instance of page renderer
     *
     * @param string $component name such as 'core', 'mod_forum' or 'qtype_multichoice'.
     * @param string $subtype optional subtype such as 'news' resulting to 'mod_forum_news'
     * @param string $target one of rendering target constants
     *
     * @return \renderer_base
     */
    static public function get_renderer($component=null, $subtype=null, $target=null){
        return static::page()->get_renderer($component ?: static::$PLUGIN_NAME, $subtype, $target);
    }

    /**
     * Return html fa (<i>) element with fa (and $class) class
     *
     * @param string|array $class
     * @param string $content
     * @param string $title
     * @param array  $attr
     *
     * @return string
     */
    static public function fa($class='', $content='', $title='', $attr=[]){
        $attr = array_merge(['class' => static::arr2str($class, 'icon fa'), 'aria-hidden' => 'true'], $attr);
        if (!empty($title)){
            $attr['title'] = $title;
        }
        return \html_writer::tag('i', $content, $attr);
    }

    /**
     * Return html link
     *
     * @param string|\moodle_url|array  $url_params - if it's array, that used [$url_text='', $params=null, $anchor=null]
     * @param string       $text
     * @param string|array $class
     * @param array        $attr
     * @param bool         $from_plugin
     *
     * @return string
     */
    static public function link($url_params='', $text='', $class='', $attr=[], $from_plugin=false){
        if ($url_params instanceof \moodle_url){
            $m_url = $url_params;
        } else {
            if (is_string($url_params)){
                list($t_url, $params, $anchor) = [$url_params, null, null];
            } else {
                list($t_url, $params, $anchor) = $url_params + ['', null, null];
            }
            if (!empty($t_url) && is_string($t_url)){
                $t_url = static::url($t_url, null, null, $from_plugin);
            }
            $m_url = new \moodle_url($t_url, $params, $anchor);
        }
        $attr['class'] = static::arr2str($class, $attr['class'] ?? '');

        return \html_writer::link($m_url, static::str_check($text), $attr);
    }

    /**
     * Return external html link
     *
     * @param string|\moodle_url|array  $url_params - if it's array, that used [$url_text='', $params=null, $anchor=null]
     * @param string       $text
     * @param string|array $class
     * @param array        $attr
     * @param bool         $from_plugin
     *
     * @return string
     */
    static public function ext_link($url_params='', $text='', $class='', $attr=[], $from_plugin=false){
        $attr = array_merge($attr, ['target' => '_blank']);
        return static::link($url_params, $text, $class, $attr, $from_plugin);
    }

    /**
     * Return html link looks like button
     *
     * @param string|\moodle_url|array  $url_params - if it's array, that used [$url_text='', $params=null, $anchor=null]
     * @param string       $text
     * @param string|array $class
     * @param bool         $primary
     * @param array        $attr
     * @param bool         $from_plugin
     *
     * @return string
     */
    static public function button_link($url_params='', $text='', $class='', $primary=false, $attr=[], $from_plugin=false){
        $class = static::val2arr($class);
        $class[] = 'btn';
        $class[] = $primary ? 'btn-primary' : 'btn-secondary';

        return static::link($url_params, $text, $class, $attr, $from_plugin);
    }

    /**
     * Return html row
     *
     * @param array  $cells
     * @param array|string $class
     * @param array  $attr
     *
     * @return \html_table_row
     */
    static public function row($cells=null, $class='', $attr=null){
        if (!is_null($cells) && !is_array($cells)){
            $cells = [$cells];
        }
        $row = new \html_table_row($cells);
        $row->attributes['class'] = static::arr2str($class);
        if ($attr){
            $row->attributes = array_merge($row->attributes, $attr);
        }
        return $row;
    }

    /**
     * Return html cell
     *
     * @param string|array $text
     * @param string|array $class
     * @param array  $attr
     *
     * @return \html_table_cell
     */
    static public function cell($text=null, $class='', $attr=null){
        if (is_array($text)){
            static::arr2str($text, '', '');
        } elseif (!is_null($text) && !is_string($text)){
            $text = strval($text);
        }

        $cell = new \html_table_cell($text);
        $cell->attributes['class'] = static::arr2str($class);
        if ($attr){
            $cell->attributes = array_merge($cell->attributes, $attr);
        }

        return $cell;
    }

    /**
     * Return html_table table
     *
     * @param string|array  $class
     * @param string        $id
     * @param array         $head
     *
     * @return \html_table
     */
    static public function html_table($class='', $id=null, $head=[]){
        $table = new \html_table();
        if (!empty($class)){
            $table->attributes['class'] = static::arr2str($class);
        }
        if (!is_null($id)){
            $table->id = $id;
        }

        $table->head = $head ?: [];

        return $table;
    }

    /**
     * Render html_table table as html string
     *
     * @param \html_table   $table data to be rendered
     * @param string|array  $wrapper_class - if not empty, wrap table in element (div by default) with this class
     * @param bool|string   $add_wrapper - if true, wrap it in div; if string - wrap in it as html tag
     *
     * @return string HTML code
     */
    static public function render_table($table, $wrapper_class='', $add_wrapper=false){
        $t = \html_writer::table($table);
        if (!empty($wrapper_class) || $add_wrapper){
            if (is_string($add_wrapper)){
                return static::tag($add_wrapper, $t, $wrapper_class);
            } else {
                return static::div($t, $wrapper_class);
            }
        }

        return $t;
    }

    /**
     * Outputs a tag with class, attributes and contents
     * @see \html_writer::tag()
     *
     * @param string $tagname The name of tag ('a', 'img', 'span' etc.)
     * @param string|array $content What goes between the opening and closing tags
     * @param string|array $class
     * @param array $attributes The tag attributes (array('src' => $url, 'class' => 'class1') etc.)
     *
     * @return string HTML fragment
     */
    public static function tag($tagname, $content='', $class='', $attributes=null) {
        $content = static::arr2str($content, '', " ");
        $attributes = static::val2arr($attributes);
        $attributes['class'] = static::arr2str($class);
        return \html_writer::tag($tagname, $content, $attributes);
    }

    /**
     * Outputs <p> with attributes and contents
     * @see tag()
     *
     * @param string|array $content What goes between the opening and closing tags
     * @param string|array $class
     * @param array $attributes The tag attributes (array('src' => $url, 'class' => 'class1') etc.)
     *
     * @return string HTML fragment
     */
    public static function html_p($content, $class='', $attributes=null) {
        return static::tag('p', $content, $class, $attributes);
    }

    /**
     * Outputs <i> with attributes and contents
     * @see tag()
     *
     * @param string|array $content What goes between the opening and closing tags
     * @param string|array $class
     * @param array $attributes The tag attributes (array('src' => $url, 'class' => 'class1') etc.)
     *
     * @return string HTML fragment
     */
    public static function html_i($content, $class='', $attributes=null) {
        return static::tag('i', $content, $class, $attributes);
    }

    /**
     * Creates a <div> tag. (Shortcut function.)
     *
     * @param string|array  $content HTML content of tag
     * @param string|array  $class Optional CSS class (or classes as space-separated list)
     * @param array         $attributes Optional other attributes as array
     *
     * @return string HTML code for div
     */
    public static function div($content='', $class='', $attributes=null){
        return \html_writer::div(static::arr2str($content, '', "\n"), static::arr2str($class), $attributes);
    }

    /**
     * Creates a <div> tag (Shortcut function.) and echo it
     * Alias for @see \local_ned_controller\shared\output::div()
     *
     * @param string|array $content    HTML content of tag
     * @param string|array $class      Optional CSS class (or classes as space-separated list)
     * @param array        $attributes Optional other attributes as array
     *
     * @return void - echo string HTML code for div
     */
    public static function ediv($content='', $class='', $attributes=null){
        echo static::div($content, $class, $attributes);
    }

    /**
     * Creates a <br> tag. (Shortcut function.)
     *
     * @return string HTML code for br
     */
    public static function br(){
        return static::tag('br');
    }

    /**
     * Creates a <span> tag. (Shortcut function.)
     *
     * @param string|array  $content HTML content of tag
     * @param string|array  $class Optional CSS class (or classes as space-separated list)
     * @param array         $attributes Optional other attributes as array
     * @return string HTML code for span
     */
    public static function span($content, $class='', $attributes=null) {
        return \html_writer::span(static::arr2str($content, '', "\n"), static::arr2str($class), $attributes);
    }

    /**
     * @param string $filename
     * @param string|array $class
     * @param string $plugin
     * @param array  $attr
     * @param string|null $alt
     *
     * @return string
     */
    static public function img($filename, $class='icon', $plugin='moodle', $attr=[], $alt=null){
        $plugin = $plugin ?? static::$PLUGIN_NAME;
        if ($filename instanceof \moodle_url){
            $url = $filename;
        } elseif (is_string($filename) && static::str_starts_with($filename,['http://', 'https://'])) {
            $url = new \moodle_url($filename);
        } else {
            $url = static::$PLUGIN_URL.'/pix/'.$filename;
            if (!file_exists(static::$DIRROOT . $url)){
                if (file_exists(static::$DIRROOT . $filename)){
                    $url = $filename;
                } else {
                    $url = static::O()->image_url($filename, $plugin);
                }
            }
            $url = new \moodle_url($url);
        }

        $attr['class'] = static::arr2str($class, $attr['class'] ?? '');
        $alt = $alt ?? ($attr['alt'] ?? ($attr['title'] ?? ''));
        if ($alt && !isset($attr['title'])){
            $attr['title'] = $alt;
        }

        return \html_writer::img($url, $alt, $attr);
    }

    /**
     * @param \cm_info|\stdClass $activity
     * @param int                $icon_size
     * @param bool               $only_icon
     * @param string             $add_class
     * @param array              $add_params
     *
     * @return string
     */
    static public function mod_link($activity, $icon_size=20, $only_icon=false, $add_class='', $add_params=[]){
        $mod_icon = static::img('icon', 'mod-icon', $activity->modname, ['height' => $icon_size, 'width' => $icon_size]);
        $mod_text = $only_icon ? $mod_icon : $mod_icon .' '. $activity->name;
        $add_params['title'] = $activity->name;
        return static::link(['/mod/' . $activity->modname . '/view.php', ['id' => $activity->id]],
            $mod_text, 'mod-link ' . $add_class, $add_params);
    }

    /**
     * Output a notification (that is, a status message about something that has just happened).
     *
     * Note: static::notification_add() may be more suitable for your usage.
     * @see notification_add()
     *
     * @param string $message The message to print out, can be key to string translate
     * @param string $type    The type of notification. See constants as static::NOTIFY_*.
     *
     * @return string the HTML to output.
     */
    static public function notification($message, $type=C::NOTIFY_INFO){
        return static::O()->notification(static::str_check($message), $type);
    }

    /**
     * Add a message to the session notification stack.
     *
     * @param string $message The message to print out, can be key to string translate
     * @param string $type    The type of notification. See constants as static::NOTIFY_*.
     *
     * @return void
     */
    static public function notification_add($message, $type=C::NOTIFY_INFO){
        \core\notification::add(static::str_check($message), $type);
    }

    /**
     * Return html link for the stored_file
     *
     * @param \stored_file $file
     * @param bool         $include_itemid - should include itemid in the url or not
     * @param bool         $forcedownload - add force download param to the url params
     * @param int          $icon_size The size of the icon. Defaults to 16 can also be 24, 32, 64, 128, 256;
     *                          if false - do not include icon
     *
     * @return string - html link for the file
     */
    static public function file_get_link($file, $include_itemid=true, $forcedownload=false, $icon_size=16){
        $filename = $file->get_filename();
        $url = static::file_get_url($file, $include_itemid, $forcedownload);
        $icon = '';
        if ($icon_size){
            $icon = static::O()->pix_icon(file_file_icon($file, $icon_size), get_mimetype_description($file));
        }

        return \html_writer::link($url, $icon.$filename);
    }
}
