<?php
/**
 * Local mobile library file for ouwiki.  These are non-standard functions that are used
 * only by the mobile app for ouwiki.
 *
 * @package	mod_ouwiki
 * @copyright  2018 GetSmarter {@link http://www.getsmarter.co.za}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * 
 **/

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/mod/ouwiki/locallib.php');
require_once($CFG->dirroot . '/mod/ouwiki/renderer.php');

use mod_ouwiki_renderer;

/**
 * Returns html for a replaceable topheading section.
 *
 * @param string $title
 * @param bool $gewgaws A decorator indicator.
 * @param object $pageversion
 * @param object $annotations
 * @param array $files
 * @param array $cm
 * @param array $subwiki
 * @param array $output
 * @return string
 */
function get_topheading_section($title) {
    $output .= html_writer::start_tag('div', array('class' => 'ouw_heading'));
    $output .= html_writer::tag('h2', format_string($title),
            array('class' => 'ouw_topheading'));
    $output .= html_writer::end_tag('div');

    return $output;
}

/**
 * Returns page description if found.
 * @param string $html
 * @return string
 */
function get_page_description($html) {
    $description = '';

    if(preg_match('/class="ouwiki_content">(.*?)<h3/', $html, $matches)) {
        $description = $matches[1];
    }

    return $description;
}

/**
 * Returns html for a replaceable recent changes section.
 *
 * @param string $title
 * @param bool $gewgaws A decorator indicator.
 * @param object $pageversion
 * @param array $cm
 * @param array $subwiki
 * @param array $output
 * @return string
 */
function get_recentchanges_section($title, $gewgaws, $pageversion, $cm, $output='') {
    if ($gewgaws && $pageversion->recentversions) {
        $output .= html_writer::start_tag('p', array('class' => 'ouw_recentchanges'));
        $output .= get_string('recentchanges', 'ouwiki').': ';
        $output .= html_writer::start_tag('span', array('class' => 'ouw_recentchanges_list'));

        $first = true;
        foreach ($pageversion->recentversions as $recentversion) {
            if ($first) {
                $first = false;
            } else {
                $output .= '; ';
            }

            $output .= ouwiki_recent_span($recentversion->timecreated);
            $output .= ouwiki_nice_date($recentversion->timecreated);
            $output .= html_writer::end_tag('span');
            $output .= ' (';
            $recentversion->id = $recentversion->userid; // So it looks like a user object.
            $output .= ouwiki_display_user($recentversion, $cm->course, false);
            $output .= ')';
        }

        $output .= '; ';
        $pagestr = '';
        if (strtolower(trim($title)) !== strtolower(get_string('startpage', 'ouwiki'))) {
            $pagestr = '&page='.$title;
        }
        $output .= html_writer::end_tag('span');
        $output .= html_writer::end_tag('p');
    }

    return $output;
}

/**
 * Returns array of cleaned wikisections
 *
 * @param array $knownsections ouwiki sections
 * @param string $pageversionhtml ouwiki html
 * @param int $ouwikiid ouwiki id
 * @param int $courseid course id
 * @return array
 */
function get_wiki_sections($knownsections, $pageversionhtml, $ouwikiid, $courseid) {
    $wikisections = [];
    if ($knownsections) {
        foreach ($knownsections as $key => $knownsection) {
            $section = ouwiki_get_section_details($pageversionhtml, $key);
            $section->id = $key;
            $section->ouwikiid = $ouwikiid;
            $section->courseid = $courseid;
            $section->content = strip_single_tag($section->content, 'a');
            $wikisections[] = $section; 
        }
    }

    return $wikisections;
}

/**
 * Helper function to strip a certian tag
 *
 * @param string $str HTML string
 * @param string $tag HTML tag to be removed
 */
function strip_single_tag($str, $tag) {

    $str=preg_replace('/<'.$tag.'[^>]*>/i', '', $str);
    $str=preg_replace('/<\/'.$tag.'>/i', '', $str);

    return $str;
}
