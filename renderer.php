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
 * Renderer for outputting the edtech course format.
 *
 * @package format_edtech
 * @copyright 2015 Jorge Villalon
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since Moodle 2.3
 */


defined('MOODLE_INTERNAL') || die();
require_once($CFG->dirroot.'/course/format/renderer.php');
require_once($CFG->dirroot.'/course/format/edtech/course_renderer.php');

/**
 * Basic renderer for edtech format.
 *
 * @copyright 2015 Jorge Villalon
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class format_edtech_renderer extends format_section_renderer_base {

    /**
     * Constructor method, calls the parent constructor
     *
     * @param moodle_page $page
     * @param string $target one of rendering target constants
     */
    public function __construct(moodle_page $page, $target) {
        parent::__construct($page, $target);

        $this->courserenderer = $this->page->get_renderer('format_edtech', 'course');
        
        // Since format_edtech_renderer::section_edit_controls() only displays the 'Set current section' control when editing mode is on
        // we need to be sure that the link 'Turn editing mode on' is available for a user who does not have any other managing capability.
        $page->set_other_editing_capability('moodle/course:setcurrentsection');
    }

    /**
     * Generate the starting container html for a list of sections
     * @return string HTML to output.
     */
    protected function start_section_list() {
        return html_writer::start_tag('div', array('class' => 'tab-content'));
    }

    /**
     * Generate the closing container html for a list of sections
     * @return string HTML to output.
     */
    protected function end_section_list() {
        return html_writer::end_tag('div');
    }

    /**
     * Generate the title for this section page
     * @return string the page title
     */
    protected function page_title() {
        return get_string('topicoutline');
    }

    /**
     * Generate the edit controls of a section
     *
     * @param stdClass $course The course entry from DB
     * @param stdClass $section The course_section entry from DB
     * @param bool $onsectionpage true if being printed on a section page
     * @return array of links with edit controls
     */
    protected function section_edit_controls($course, $section, $onsectionpage = false) {
        global $PAGE;

        if (!$PAGE->user_is_editing()) {
            return array();
        }

        $coursecontext = context_course::instance($course->id);

        if ($onsectionpage) {
            $url = course_get_url($course, $section->section);
        } else {
            $url = course_get_url($course);
        }
        $url->param('sesskey', sesskey());

        $isstealth = $section->section > $course->numsections;
        $controls = array();
        if (!$isstealth && has_capability('moodle/course:setcurrentsection', $coursecontext)) {
            if ($course->marker == $section->section) {  // Show the "light globe" on/off.
                $url->param('marker', 0);
                $controls[] = html_writer::link($url,
                                    html_writer::empty_tag('img', array('src' => $this->output->pix_url('i/marked'),
                                        'class' => 'icon ', 'alt' => get_string('markedthistopic'))),
                                    array('title' => get_string('markedthistopic'), 'class' => 'editing_highlight'));
            } else {
                $url->param('marker', $section->section);
                $controls[] = html_writer::link($url,
                                html_writer::empty_tag('img', array('src' => $this->output->pix_url('i/marker'),
                                    'class' => 'icon', 'alt' => get_string('markthistopic'))),
                                array('title' => get_string('markthistopic'), 'class' => 'editing_highlight'));
            }
        }

        return array_merge($controls, parent::section_edit_controls($course, $section, $onsectionpage));
    }
    
    /**
     * Generate the display of the header part of a section before
     * course modules are included
     *
     * @param stdClass $section The course_section entry from DB
     * @param stdClass $course The course entry from DB
     * @param bool $onsectionpage true if being printed on a single-section page
     * @param int $sectionreturn The section to return to after an action
     * @param bool $active If the section should be active
     * @return string HTML to output.
     */
    protected function section_header($section, $course, $onsectionpage, $sectionreturn=null, $active = false) {
        global $PAGE;

        $o = '';
        $currenttext = '';
        $sectionstyle = '';

        if ($section->section != 0) {
            // Only in the non-general sections.
            if (!$section->visible) {
                $sectionstyle = ' hidden';
            } else if (course_get_format($course)->is_section_current($section) || $active) {
                $sectionstyle = ' current in active';
            }
        } elseif ($active) {
            $sectionstyle = ' current in active';
        }

        $o.= html_writer::start_tag('div', array('id' => 'section-'.$section->section,
            'class' => 'tab-pane fade section main clearfix'.$sectionstyle, 'role'=>'tabpanel',
            'aria-label'=> get_section_name($course, $section)));

        $leftcontent = $this->section_left_content($section, $course, $onsectionpage);
        $o.= html_writer::tag('div', $leftcontent, array('class' => 'left side'));

        $rightcontent = $this->section_right_content($section, $course, $onsectionpage);
        $o.= html_writer::tag('div', $rightcontent, array('class' => 'right side'));
        $o.= html_writer::start_tag('div', array('class' => 'content'));

        // When not on a section page, we display the section titles except the general section if null
        $hasnamenotsecpg = (!$onsectionpage && ($section->section != 0 || !is_null($section->name)));

        // When on a section page, we only display the general section title, if title is not the default one
        $hasnamesecpg = ($onsectionpage && ($section->section == 0 && !is_null($section->name)));

        $classes = ' accesshide';
        if ($hasnamenotsecpg || $hasnamesecpg) {
            $classes = '';
        }
        $o.= $this->output->heading($this->section_title($section, $course), 3, 'sectionname' . $classes);

        // Open summary div
        $o.= html_writer::start_tag('div', array('class' => 'summary'));
        $o.= $this->format_summary_text($section);

        $context = context_course::instance($course->id);
        if ($PAGE->user_is_editing() && has_capability('moodle/course:update', $context)) {
            $url = new moodle_url('/course/editsection.php', array('id'=>$section->id, 'sr'=>$sectionreturn));
            $o.= html_writer::link($url,
                html_writer::empty_tag('img', array('src' => $this->output->pix_url('i/settings'),
                    'class' => 'iconsmall edit', 'alt' => get_string('edit'))),
                array('title' => get_string('editsummary')));
        }
        $o.= html_writer::end_tag('div');
        // Close summary div

        $o .= $this->section_availability_message($section,
                has_capability('moodle/course:viewhiddensections', $context));

        return $o;
    }
    
    /**
     * Generate the section title
     *
     * @param stdClass $section The course_section entry from DB
     * @param stdClass $course The course entry from DB
     * @return string HTML to output.
     */
    public function section_title($section, $course) {
        $title = get_section_name($course, $section);
        $url = course_get_url($course, $section->section, array('navigation' => true));
        if ($url) {
            $title = html_writer::link($url, $title);
        }
        return $title;
    }
    
    /**
     * Generate the display of the footer part of a section
     *
     * @return string HTML to output.
     */
    protected function section_footer() {
        $o = html_writer::end_tag('div');
        $o.= html_writer::end_tag('div');
    
        return $o;
    }
    
    /**
     * Generate the content to displayed on the right part of a section
     * before course modules are included
     *
     * @param stdClass $section The course_section entry from DB
     * @param stdClass $course The course entry from DB
     * @param bool $onsectionpage true if being printed on a section page
     * @return string HTML to output.
     */
    protected function section_right_content($section, $course, $onsectionpage) {
        $o = $this->output->spacer();
    
        if ($section->section != 0) {
            $controls = $this->section_edit_controls($course, $section, $onsectionpage);
            if (!empty($controls)) {
                $o = implode('<br />', $controls);
            }
        }
    
        return $o;
    }
    
    /**
     * Generate the content to displayed on the left part of a section
     * before course modules are included
     *
     * @param stdClass $section The course_section entry from DB
     * @param stdClass $course The course entry from DB
     * @param bool $onsectionpage true if being printed on a section page
     * @return string HTML to output.
     */
    protected function section_left_content($section, $course, $onsectionpage) {
        $o = $this->output->spacer();
    
        if ($section->section != 0) {
            // Only in the non-general sections.
            if (course_get_format($course)->is_section_current($section)) {
                $o = get_accesshide(get_string('currentsection', 'format_'.$course->format));
            }
        }
    
        return $o;
    }

    /**
     * Output the html for a multiple section page
     *
     * @param stdClass $course The course entry from DB
     * @param array $sections (argument not used)
     * @param array $mods (argument not used)
     * @param array $modnames (argument not used)
     * @param array $modnamesused (argument not used)
     */
    public function print_multiple_section_page($course, $sections, $mods, $modnames, $modnamesused) {
        global $PAGE;
    
        $modinfo = get_fast_modinfo($course);
        $course = course_get_format($course)->get_course();
    
        $context = context_course::instance($course->id);
        // Title with completion help icon.
        $completioninfo = new completion_info($course);
        echo $completioninfo->display_help_icon();
        echo $this->output->heading($this->page_title(), 2, 'accesshide');
    
        echo "<style>
            .left {display:none;} 
            h3 {line-height:none;} 
            .right {float:right;}
            .section-modchooser {
                width: 20px;
                height: 20px;
                background-color: red;
                border-radius: 50%;
                text-align: center;
                float: right;
                }
            .section-modchooser-link img.smallicon {
                padding-bottom: 3px;
            }
            .block, #page #page-content #region-main, #page #page-content div[role=\"main\"], .pagelayout-redirect #page-content #region-main, .pagelayout-redirect #page-content div[role=\"main\"] {
                border: none;
                border-radius: none;
                padding: 0px;
            }
            .coursetitle {text-transform: uppercase; padding-bottom: 5px;}
            .bor {display:none;}
            </style>";
        
        // Copy activity clipboard..
        echo $this->course_activity_clipboard($course, 0);

        echo html_writer::start_div("tabbable tabs-left");
        echo html_writer::start_tag("ul", array("class"=>"nav nav-tabs", "role"=>"tablist"));
        // Show navigation tabs (bootstrap like)
        $tabshtml = "";
        $section0html = "";
        $currentfound=false;
        foreach ($modinfo->get_section_info_all() as $section => $thissection) {
            $a = html_writer::tag("a", get_section_name($course, $thissection), array("href"=>"#section-".$section, "aria-controls"=>"profile", "role"=>"tab", "data-toggle"=>"tab"));
            $style = "";
            if(course_get_format($course)->is_section_current($section)) {
                $style = "active";
                $currentfound=true;
            }
            if($section == 0) {
                $section0html = $a;
            } else {
                $tabshtml .= html_writer::tag("li", $a, array("role"=>"presentation", "class"=>$style));
            }
        }
        $style = $currentfound ? "" : "active";
        echo html_writer::tag("li", $section0html, array("role"=>"presentation", "class"=>$style)) . $tabshtml;
        echo html_writer::end_tag("ul");
        
        // Now the list of sections..
        echo $this->start_section_list();
        
        $cmlist = "";
        $firstsection = "";
        $firstsectionhead = "";
        $firstsectionheadactive = "";
        $currentfound = false;
        
        foreach ($modinfo->get_section_info_all() as $section => $thissection) {
            if (course_get_format($course)->is_section_current($section)) {
                $currentfound = true;
            }
            if ($section == 0) {
                // 0-section is displayed a little different then the others
                if ($thissection->summary or !empty($modinfo->sections[0]) or $PAGE->user_is_editing()) {
                    $firstsectionhead = $this->section_header($thissection, $course, false, 0, false);
                    $firstsectionheadactive = $this->section_header($thissection, $course, false, 0, true);
                    $firstsection .= $this->courserenderer->course_section_cm_list($course, $thissection, 0);
                    $firstsection .= $this->courserenderer->course_section_add_cm_control($course, 0, 0);
                    $firstsection .= $this->section_footer();
                }
                continue;
            }
            if ($section > $course->numsections) {
                // activities inside this section are 'orphaned', this section will be printed as 'stealth' below
                continue;
            }
            // Show the section if the user is permitted to access it, OR if it's not available
            // but there is some available info text which explains the reason & should display.
            $showsection = $thissection->uservisible ||
            ($thissection->visible && !$thissection->available &&
                !empty($thissection->availableinfo));
            if (!$showsection) {
                // If the hiddensections option is set to 'show hidden sections in collapsed
                // form', then display the hidden section message - UNLESS the section is
                // hidden by the availability system, which is set to hide the reason.
                if (!$course->hiddensections && $thissection->available) {
                    $cmlist .= $this->section_hidden($section, $course->id);
                }
    
                continue;
            }
    
            if (!$PAGE->user_is_editing() && $course->coursedisplay == COURSE_DISPLAY_MULTIPAGE) {
                // Display section summary only.
                $cmlist .= $this->section_summary($thissection, $course, null);
            } else {
                $cmlist .= $this->section_header($thissection, $course, false, 0);
                if ($thissection->uservisible) {
                    $cmlist .= $this->courserenderer->course_section_cm_list($course, $thissection, 0);
                    $cmlist .= $this->courserenderer->course_section_add_cm_control($course, $section, 0);
                }
                $cmlist .= $this->section_footer();
            }
        }
        if(!$currentfound) {
            $firstsection = $firstsectionheadactive . $firstsection;
        } else {
            $firstsection = $firstsectionhead . $firstsection;
        }
        echo $firstsection . $cmlist;
    
        if ($PAGE->user_is_editing() and has_capability('moodle/course:update', $context)) {
            // Print stealth sections if present.
            foreach ($modinfo->get_section_info_all() as $section => $thissection) {
                if ($section <= $course->numsections or empty($modinfo->sections[$section])) {
                    // this is not stealth section or it is empty
                    continue;
                }
                echo $this->stealth_section_header($section);
                echo $this->courserenderer->course_section_cm_list($course, $thissection, 0);
                echo $this->stealth_section_footer();
            }
    
            echo $this->end_section_list();
            echo html_writer::end_div();
            
            echo html_writer::start_tag('div', array('id' => 'changenumsections', 'class' => 'mdl-right'));
    
            // Increase number of sections.
            $straddsection = get_string('increasesections', 'moodle');
            $url = new moodle_url('/course/changenumsections.php',
                array('courseid' => $course->id,
                    'increase' => true,
                    'sesskey' => sesskey()));
            $icon = $this->output->pix_icon('t/switch_plus', $straddsection);
            echo html_writer::link($url, $icon.get_accesshide($straddsection), array('class' => 'increase-sections'));
    
            if ($course->numsections > 0) {
                // Reduce number of sections sections.
                $strremovesection = get_string('reducesections', 'moodle');
                $url = new moodle_url('/course/changenumsections.php',
                    array('courseid' => $course->id,
                        'increase' => false,
                        'sesskey' => sesskey()));
                $icon = $this->output->pix_icon('t/switch_minus', $strremovesection);
                echo html_writer::link($url, $icon.get_accesshide($strremovesection), array('class' => 'reduce-sections'));
            }
    
            echo html_writer::end_tag('div');
        } else {
            echo $this->end_section_list();
        }
    
    }
    
}
