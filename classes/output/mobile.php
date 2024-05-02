<?php
namespace mod_ouwiki\output;
 
defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot.'/mod/ouwiki/mobilelib.php');

use context_module;
use html_writer;
/**
 * The mod_ouwiki mobile app compatibility.
 *
 * @package	mod_ouwiki
 * @copyright  2018 GetSmarter {@link http://www.getsmarter.co.za}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class mobile {

    /**
     * Returns the hsuforum discussion view for a given forum.
     * Note use as much logic and functions from view.php as possible (view.php uses renderer.php and lib.php to build view)
     * @param  array $args Arguments from tool_mobile_get_content WS
     * @return array HTML, javascript and otherdata
     */
    public static function all_wikis_view($args) {
        global $OUTPUT, $USER, $DB, $PAGE, $CFG, $ouwiki_nologin;

        $args    = (object) $args;
        $course  = $DB->get_record('course', array('id' => $args->courseid), '*', MUST_EXIST);
        $modinfo = get_fast_modinfo($course);
        $cm      = get_coursemodule_from_id('ouwiki', $args->cmid);
        $context = context_module::instance($cm->id);

    /// Getting ouwiki for the module - logic from basicpage.php
        $ouwiki = false;
        try {
            $ouwiki = $DB->get_record('ouwiki', array('id' => $cm->instance));
        } catch (Exception $e) {
            // @TODO See how to redirect or throw friendly errors in app (popups)
            print_r('Handle moodle app errors here');
        }

    /// Basic Validation checks
        /** Checks for valid course module
         * @TODO
         * See how to redirect or throw friendly errors in app (popups) when below fails
         * Check for group id
         */
        /* Below some example code for checks
            $groupid = 0;
            if (empty($ouwiki_nologin)) {
            Make sure they're logged in and check they have permission to view
                require_course_login($course, true, $cm);
                require_capability('mod/ouwiki:view', $context);
            }
        */

    /// Handling basic no group visible for now
        // Groupid refers to grouping id, db refers to this as groupid - do not confuse with groupmode
        $groupid = null;
        $groupsections = false;
        $showgroupsections = false;
        $groupselection = false;

        if ((int) $cm->groupmode > 0) {
            $groupsections     = groups_get_all_groups($cm->course, 0, $cm->groupingid);
            $groupsections     = array_values($groupsections);
            $showgroupsections = true;
            $groupid           = !$args->groupid ? 1 : $args->groupid;
            $groupselection    = $groupid;
        }

    /// Get subwiki, creating it if necessary
        $subwiki = ouwiki_get_subwiki($course, $ouwiki, $cm, $context, $groupid, $args->userid, true);

    /// Handle annotations
        // Get annotations - only if using annotation system. Prevents unnecessary db access.
        if ($subwiki->annotation) {
            $annotations = ouwiki_get_annotations($pageversion);
        } else {
            $annotations = '';
        }
        // Setup annotations according to the page we are on.
        if ($page == 'view') {
            if ($subwiki->annotation && count($annotations)) {
                $pageversion->xhtml =
                        ouwiki_highlight_existing_annotations($pageversion->xhtml, $annotations, 'view');
            }
        }

    /// Setting up ouwiki variables - own logic composition from locallib.php and mobilelib.php
        $ouwikioutput    = $PAGE->get_renderer('mod_ouwiki');
        $pagename        = (!empty($args->pagename) && $args->pagename !== '') ? $args->pagename : '';
        $pageversion     = ouwiki_get_current_page($subwiki, $pagename);
        $locked          = ($pageversion) ? $pageversion->locked : false;
        $hideannotations = get_user_preferences(OUWIKI_PREF_HIDEANNOTATIONS, 0);
        $modcontext      = context_module::instance($cm->id);
        $pagetitle       = $pageversion->title === '' ? get_string('startpage', 'ouwiki') : htmlspecialchars($pageversion->title);
        $nowikipage      = false;

        // Must rewrite plugin urls AFTER doing annotations because they depend on byte position.
        $pageversion->xhtml = file_rewrite_pluginfile_urls($pageversion->xhtml, 'pluginfile.php',
                $modcontext->id, 'mod_ouwiki', 'content', $pageversion->versionid);
        $pageversion->xhtml = ouwiki_convert_content($pageversion->xhtml, $subwiki, $cm, null,
                $pageversion->xhtmlformat);

    /// Handle file uploads
        // @TODO properly test and speck out requirements for file uploads in mobile context
        require_once($CFG->libdir . '/filelib.php');
        $fs = get_file_storage();
        $files = $fs->get_area_files($modcontext->id, 'mod_ouwiki', 'attachment',
                $pageversion->versionid, "timemodified", false);


    /// Rendering HTML parts to be output on the mobile template
        // Get header html
        $headercontent  = '';
        $headercontent .= get_topheading_section($pagetitle);
        // Get recent edits html
        $recentchangescontent  = '';
        $recentchangescontent .= strip_single_tag(get_recentchanges_section($pagetitle, true, $pageversion, $cm), 'a');
        // Get page description html
        $pagedescription  = '';
        $pagedescription .= strip_single_tag(get_page_description($pageversion->xhtml), 'a');
        // Get wiki sections html
        $knownsections = false;
        $knownsectionscount = 0;
        $wikisections = [];
        $knownsections = ouwiki_find_sections($pageversion->xhtml);

        if ($knownsections) {
            $knownsectionscount = count($knownsections);
            $wikisections = get_wiki_sections($knownsections, $pageversion->xhtml, $ouwiki->id, $course->id, $cm->id);
        }


    /// Build data array to output in the template
        $data = array(
            'cmid'               => $cm->id,
            'pagetitle'          => $pagetitle,
            'pagelocked'         => $locked,
            'ouwikiid'           => $ouwiki->id,
            'courseid'           => $course->id,
            'pagename'           => $pagename,
            'showgroupsections'  => $showgroupsections,
            'groupid'            => $groupid,
            'knownsectionscount' => $knownsectionscount,
        );

        return array(
            'templates' => array(
                array(
                    'id' => 'main',
                    'html' => $OUTPUT->render_from_template('mod_ouwiki/mobile_all_wikis_view', $data),
                ),
            ),
            'javascript' => '',
            'otherdata' => array(
                'ouwiki'                => json_encode($ouwiki),
                'fullpagecontent'       => $pageversion->xhtml,
                'headercontent'         => $headercontent,
                'recentchangescontent'  => $recentchangescontent,
                'pagedescription'       => $pagedescription,
                'wikisections'          => json_encode($wikisections),
                'newwikisectionheading' => '',
                'nowikipages'           => true ? !strlen($pagetitle) : false,
                'groupsections'         => json_encode($groupsections),
                'groupselection'        => $groupselection,
            ),
            'files' => '',
        );
    }


    /**
     * Returns the edit wikipage view for a given wiki.
     * @param  array $args Arguments from tool_mobile_get_content WS
     * @return array HTML, javascript and otherdata
     * @TODO pass page name and if no page name then 'start page' for linked pages
     */
    public static function mobile_edit_wikipage($args) {
        global $OUTPUT, $USER, $DB, $PAGE, $CFG;

        $args = (object) $args;
        $cm = get_coursemodule_from_id('ouwiki', $args->cmid);

        // Build data array to output in the template
        $data = array(
            'cmid'     => $args->cmid,
            'pagename' => (!empty($args->pagename) && $args->pagename !== '') ? $args->pagename : '',
            'groupid'  => $args->groupid,
        );

        return array(
            'templates' => array(
                array(
                    'id' => 'main',
                    'html' => $OUTPUT->render_from_template('mod_ouwiki/mobile_edit_wikipage_view', $data),
                ),
            ),
            'javascript' => '',
            'otherdata' => array(
                'fullpagecontent' => $args->fullpagecontent ? $args->fullpagecontent : '<p></p>',
            ),
            'files' => '',
        );
    }


    /**
     * Handles edit/add wiki pages
     * @param  array $args Arguments from tool_mobile_get_content WS
     * @return array HTML, javascript and otherdata
     * @TODO finish below function - adding pages
     */
    public static function mobile_wikipage_submit($args) {
        global $OUTPUT, $USER, $DB, $PAGE, $CFG;
        $poststatus = 'pending';

        try {
            $args     = (object) $args;
            $cm       = get_coursemodule_from_id('ouwiki', $args->cmid);
            $course   = $DB->get_record('course', array('id' => $cm->course), '*', MUST_EXIST);
            $context  = context_module::instance($cm->id);
            $ouwiki   = $DB->get_record('ouwiki', array('id' => $cm->instance));
            $groupid  = (int) $args->groupid ? $args->groupid : null;
            $subwiki  = ouwiki_get_subwiki($course, $ouwiki, $cm, $context, $groupid, $USER->id, true);
            $pagename = (!empty($args->pagename) && $args->pagename !== '') ? $args->pagename : '';
            $content  = ouwiki_format_xhtml_a_bit($args->pagebody); // Tidy up HTML

            $newversion = ouwiki_save_new_version($course, $cm, $ouwiki, $subwiki, $pagename, $content, -1, -1, -1, null, null);

            if ($newversion) {
                $poststatus = 'success';
            }
        } catch(Exception $e) {
            $poststatus = 'failed';
        }

        // Build data array to output in the template
        $data = array(
            'cmid'       => $args->cmid,
            'poststatus' => $poststatus,
            'groupid'    => $args->groupid,
        );

        return array(
            'templates' => array(
                array(
                    'id'   => 'main',
                    'html' => $OUTPUT->render_from_template('mod_ouwiki/mobile_edit_wikipage_view', $data),
                ),
            ),
            'javascript' => '',
            'otherdata'  => array(
                'fullpagecontent' => $args->fullpagecontent,
            ),
            'files' => '',
        );
    }


    /**
     * Returns the edit section view for a given wiki.
     * @param  array $args Arguments from tool_mobile_get_content WS
     * @return array HTML, javascript and otherdata
     * @TODO finish below function
     */
    public static function mobile_edit_section($args) {
        global $OUTPUT, $USER, $DB, $PAGE, $CFG;

        // Check for incoming new section and create template body for edit
        if ($args['newwikisectionheading']) {
            $new       = new \StdClass;
            $new->name = ouwiki_display_user($USER, $course->id);
            $new->date = userdate(time());
            $args['sectioncontent'] = html_writer::tag('h3', s($args['newwikisectionheading'])) .
                    html_writer::tag('p', '(' . get_string('createdbyon', 'ouwiki', $new) . ')');
        }

        // Build data array to output in the template
        $data = array(
            'cmid'      => $args['cmid'],
            'sectionid' => $args['sectionid'],
            'courseid'  => $args['courseid'],
            'ouwikiid'  => $args['ouwikiid'],
            'groupid'   => $args['groupid'],
        );

        return array(
            'templates' => array(
                array(
                    'id' => 'main',
                    'html' => $OUTPUT->render_from_template('mod_ouwiki/mobile_edit_section_view', $data),
                ),
            ),
            'javascript' => '',
            'otherdata' => array(
                'sectioncontent' => $args['sectioncontent'],
            ),
            'files' => '',
        );
    }

    /**
     * Handles edit/add wiki sections
     * @param  array $args Arguments from tool_mobile_get_content WS
     * @return array HTML, javascript and otherdata
     * @TODO finish below function to add section
     */
    public static function mobile_section_submit($args) {
        global $OUTPUT, $USER, $DB, $PAGE, $CFG;

        $poststatus = 'pending';
        // Getting related object data to edit/create sections
        try {
            $cm             = get_coursemodule_from_instance('ouwiki', $args['ouwikiid']);
            $course         = $DB->get_record('course', array('id' => $args['courseid']), '*', MUST_EXIST);
            $context        = context_module::instance($cm->id);
            $ouwiki         = $DB->get_record('ouwiki', array('id' => $cm->instance));
            $subwiki        = ouwiki_get_subwiki($course, $ouwiki, $cm, $context, '', $USER->id, true);
            $pagename       = (!empty($args->pagename) && $args->pagename !== '') ? $args['pagename'] : '';
            $pageversion    = ouwiki_get_current_page($subwiki, $pagename);
            $contentbefore  = $pageversion->xhtml;
            $sectionbody    = $args['sectioncontent'];

            // Check if sectionbody changed and decode html special chars
            if (isset($args['sectionbody']) && strlen($args['sectionbody']) > 0) {
                $sectionbody = html_entity_decode($args['sectionbody']);
            }

        } catch (Exception $e) {
            // @TODO improve error handling
            print_r('Missing arguments in form data');
            $poststatus = 'failed';
        }

        // Check if editing a section
        if ($args && strlen($args['sectionid'])) {
            $sectiondetails = ouwiki_get_section_details($contentbefore, $args['sectionid']);
            $newcontent     = $sectionbody;

            try {
                ouwiki_save_new_version_section($course, $cm, $ouwiki, $subwiki, $pagename, $contentbefore, $newcontent, $sectiondetails);
                $poststatus = 'success';
            } catch (Exception $e) {
                print_r('Could not save ouwiki section');
                $poststatus = 'failed';
            }
        } else {
            // Create new wikisection
            if ($sectionbody && !strlen($args['sectionid'])) {
                try {
                    $sectiondetails = ouwiki_get_new_section_details($contentbefore, $sectionbody);
                    ouwiki_save_new_version_section($course, $cm, $ouwiki, $subwiki, $pagename, $contentbefore, $sectionbody, $sectiondetails);
                    $poststatus = 'success';
                } catch (Exception $e) {
                    print_r('Could not save new ouwiki section');
                    $poststatus = 'failed';
                }
            }
        }

        // Build data array to output in the template
        $data = array(
            'cmid'       => $args['cmid'],
            'sectionid'  => $args['sectionid'],
            'courseid'   => $args['courseid'],
            'ouwikiid'   => $args['ouwikiid'],
            'poststatus' => $poststatus,
        );

        return array(
            'templates' => array(
                array(
                    'id' => 'main',
                    'html' => $OUTPUT->render_from_template('mod_ouwiki/mobile_edit_section_view', $data),
                ),
            ),
            'javascript' => '',
            'otherdata' => array(
                'sectioncontent' => $sectionbody,
            ),
            'files' => '',
        );
    }
}
