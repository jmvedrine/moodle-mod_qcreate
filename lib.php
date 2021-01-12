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
 * @package    mod_qcreate
 * @copyright  1999 onwards Martin Dougiamas  {@link http://moodle.com}
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Library of functions and constants for module qcreate
 * This file should have two well differenced parts:
 *   - All the core Moodle functions, neeeded to allow
 *     the module to work integrated in Moodle.
 *   - All the qcreate specific functions, needed
 *     to implement all the module logic. Please, note
 *     that, if the module become complex and this lib
 *     grows a lot, it's HIGHLY recommended to move all
 *     these module specific functions to a new php file,
 *     called "locallib.php" (see forum, quiz...). This will
 *     help to save some memory when Moodle is performing
 *     actions across all modules.
 */

/**
 * The options used when popping up a question preview window in Javascript.
 */
define('QCREATE_EDIT_POPUP_OPTIONS', 'scrollbars=yes,resizable=yes,width=800,height=540');

/** Set QCREATE_PER_PAGE to 0 if you wish to display all questions on the edit page */
define('QCREATE_PER_PAGE', 10);

define('QCREATE_MAX_PER_PAGE', 100);

/**
 * Event types.
 */
define('QCREATE_EVENT_TYPE_OPEN', 'open');
define('QCREATE_EVENT_TYPE_CLOSE', 'close');

/**
 * Returns the information on whether the module supports a feature
 *
 * @see plugin_supports() in lib/moodlelib.php
 * @param string $feature FEATURE_xx constant for requested feature
 * @return mixed true if the feature is supported, null if unknown
 */
function qcreate_supports($feature) {
    switch($feature) {
        case FEATURE_GROUPS:
            return true;
        case FEATURE_GROUPINGS:
            return true;
        case FEATURE_MOD_INTRO:
            return true;
        case FEATURE_COMPLETION_TRACKS_VIEWS:
            return true;
        case FEATURE_COMPLETION_HAS_RULES:
            return true;
        case FEATURE_GRADE_HAS_GRADE:
            return true;
        case FEATURE_BACKUP_MOODLE2:
            return true;
        case FEATURE_GRADE_OUTCOMES:
             return true;
        case FEATURE_SHOW_DESCRIPTION:
            return true;
        case FEATURE_USES_QUESTIONS:
            return true;

        default:
            return null;
    }
}

/**
 * Obtains the automatic completion state for this qcreate based on any conditions
 * in qcreate settings.
 *
 * @global object
 * @global object
 * @param object $course Course
 * @param object $cm Course-module
 * @param int $userid User ID
 * @param bool $type Type of comparison (or/and; can be used as return value if no conditions)
 * @return bool True if completed, false if not. (If no conditions, then return
 *   value depends on comparison type)
 */
function qcreate_get_completion_state($course, $cm, $userid, $type) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/mod/qcreate/locallib.php');

    $context = context_module::instance($cm->id);
    $qcreateobj = new qcreate($context, $cm, $course);

    $result = $type; // Default return value.

    if ($qcreateobj->get_instance()->completionquestions) {
        $value = $qcreateobj->get_instance()->completionquestions <= $qcreateobj->count_user_questions($userid);
        if ($type == COMPLETION_AND) {
            $result = $result && $value;
        } else {
            $result = $result || $value;
        }
    }
    return $result;
}

/**
 * Adds a qcreate instance
 *
 * This is done by calling the add_instance() method of the qcreate type class
 * @param stdClass $data
 * @param mod_qcreate_mod_form $form
 * @return int The instance id of the new qcreate
 */
function qcreate_add_instance(stdClass $data, $form = null) {
    global $CFG;
    require_once($CFG->dirroot . '/mod/qcreate/locallib.php');

    $qcreateobj = new qcreate(context_module::instance($data->coursemodule), null, null);
    return $qcreateobj->add_instance($data, true);
}

/**
 * Update an qcreate instance
 *
 * This is done by calling the update_instance() method of the qcreate type class
 * @param stdClass $data he data that came from the form
 * @param stdClass $form - unused
 * @return object
 */
function qcreate_update_instance(stdClass $data, $form) {
    global $CFG;
    require_once($CFG->dirroot . '/mod/qcreate/locallib.php');

    $qcreateobj = new qcreate(context_module::instance($data->coursemodule), null, null);
    return $qcreateobj->update_instance($data);
}

/**
 * Given an ID of an instance of this module,
 * this function will permanently delete the instance
 * and any data that depends on it.
 *
 * @param int $id Id of the module instance
 * @return boolean Success/Failure
 **/
function qcreate_delete_instance($id) {
    global $CFG;
    require_once($CFG->dirroot . '/mod/qcreate/locallib.php');

    $cm = get_coursemodule_from_instance('qcreate', $id, 0, false, MUST_EXIST);
    $context = context_module::instance($cm->id);

    $qcreateobj = new qcreate($context, null, null);
    return $qcreateobj->delete_instance();
}

/**
 * This function is used by the reset_course_userdata function in moodlelib.
 * This function will remove all grades from the specified qcreate
 * and clean up any related data.
 *
 * @param $data the data submitted from the reset course.
 * @return array status array
 */
function qcreate_reset_userdata($data) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/mod/qcreate/locallib.php');

    $status = array();
    $params = array('courseid' => $data->courseid);
    $sql = "SELECT a.id FROM {qcreate} a WHERE a.course=:courseid";
    $course = $DB->get_record('course', array('id' => $data->courseid), '*', MUST_EXIST);
    if ($qcreates = $DB->get_records_sql($sql, $params)) {
        foreach ($qcreates as $qcreate) {
            $cm = get_coursemodule_from_instance('qcreate',
                                                 $qcreate->id,
                                                 $data->courseid,
                                                 false,
                                                 MUST_EXIST);
            $context = context_module::instance($cm->id);
            $qcreateobj = new qcreate($context, $cm, $course);
            $status = array_merge($status, $qcreateobj->reset_userdata($data));
        }
    }
    return $status;
}

/**
 * This standard function will check all instances of this module
 * and make sure there are up-to-date events created for each of them.
 * If courseid = 0, then every qcreate event in the site is checked, else
 * only qcreate events belonging to the course specified are checked.
 * This function is used, in its new format, by restore_refresh_events()
 *
 * @param int $courseid
 * @param int|stdClass $instance qcreate module instance or ID.
 * @param int|stdClass $cm Course module object or ID (not used in this module).
 * @return bool
 */
function qcreate_refresh_events($courseid = 0, $instance = null, $cm = null) {
    global $DB;

    // If we have instance information then we can just update the one event instead of updating all events.
    if (isset($instance)) {
        if (!is_object($instance)) {
            $instance = $DB->get_record('qcreate', array('id' => $instance), '*', MUST_EXIST);
        }
        qcreate_update_events($instance);
        return true;
    }

    if ($courseid == 0) {
        if (!$qcreates = $DB->get_records('qcreate')) {
            return true;
        }
    } else {
        if (!$qcreates = $DB->get_records('qcreate', array('course' => $courseid))) {
            return true;
        }
    }

    foreach ($qcreates as $qcreate) {
        qcreate_update_events($qcreate);
    }

    return true;
}

/**
 * This actually updates the activity calendar events.
 *
 * @param  stdClass $qcreate qcreate object (from DB).
 * @param  stdClass $course Course object.
 * @param  stdClass $cm Course module object.
 */
function qcreate_update_events($qcreate, $course = null, $cm = null) {
    global $DB, $CFG;
    require_once($CFG->dirroot . '/mod/qcreate/locallib.php');

    if (!isset($course)) {
        // Get course and course module for the qcreate.
        list($course, $cm) = get_course_and_cm_from_instance($qcreate->id, 'qcreate', $qcreate->course);
    }
    // Refresh the qcreate's calendar events.
    $context = context_module::instance($cm->id);
    $qcreateobj = new qcreate($context, $cm, $course);
    $qcreateobj->update_calendar($cm->id);
}
/**
 * Prints qcreate summaries on MyMoodle Page
 * @param array $courses
 * @param array $htmlarray
 */
function qcreate_print_overview($courses, &$htmlarray) {
    global $USER, $CFG;

    debugging('The function qcreate_print_overview() is now deprecated.', DEBUG_DEVELOPER);

    // These next 6 Lines are constant in all modules (just change module name).
    if (empty($courses) || !is_array($courses) || count($courses) == 0) {
        return array();
    }
    if (!$qcreates = get_all_instances_in_courses('qcreate', $courses)) {
        return;
    }

    // Fetch some language strings outside the main loop.
    $strqcreate = get_string('modulename', 'qcreate');

    // We want to list qcreates that are currently availables.
    // I know this is different from lesson and quiz. See MDL-10568.
    $now = time();
    $strnoquestions = get_string('noquestions', 'qcreate');
    foreach ($qcreates as $qcreate) {
        if (($qcreate->timeopen == 0 ||($qcreate->timeopen < $now)) &&
        ($qcreate->timeclose == 0 ||($qcreate->timeclose > $now))) {
            // Give a link to the qcreate, and the deadline.
            $str = '<div class="qcreate overview">' .
                    '<div class="name">' . $strqcreate . ': <a ' .
                    ($qcreate->visible ? '' : ' class="dimmed"') .
                    ' href="' . $CFG->wwwroot . '/mod/qcreate/view.php?id=' .
                    $qcreate->coursemodule . '">' .
                    $qcreate->name . '</a></div>';
            $str .= '<div class="info">' . qcreate_time_status($qcreate) . '</div>';

            // Now provide more information depending on the uers's role.
            $context = context_module::instance($qcreate->coursemodule);
            if (has_capability('mod/qcreate:grade', $context)) {
                // For teacher-like people, show a summary of the number questions created.
                // The $qcreate objects returned by get_all_instances_in_course have the necessary $cm
                // fields set to make the following call work.
                $str .= '<div class="info">' .
                        get_string('studentshavedone', 'qcreate', qcreate_get_qestions_number(0, $qcreate)) . '</div>';
            } else if (has_capability('mod/qcreate:view', $context)) { // Student
                // For student-like people, tell them how many questions they have created.
                if (isset($USER->id)) {
                    $str .= '<div class="info">' .
                            get_string('youhavedone', 'qcreate', qcreate_get_qestions_number($USER->id, $qcreate)) . '</div>';
                } else {
                    $str .= '<div class="info">' . $strnoquestions . '</div>';
                }
            } else {
                // For ayone else, there is no point listing this qcreate, so stop processing.
                continue;
            }

            // Add the output for this qcreate to the rest.
            $str .= '</div>';
            if (empty($htmlarray[$qcreate->course]['qcreate'])) {
                $htmlarray[$qcreate->course]['qcreate'] = $str;
            } else {
                $htmlarray[$qcreate->course]['qcreate'] .= $str;
            }
        }
    }
}

/**
 * Removes all grades from gradebook
 *
 * @param int $courseid The ID of the course to reset
 * @param string $type Optional type of qcreate (not used here)
 */
function qcreate_reset_gradebook($courseid, $type='') {
    global $CFG, $DB;

    $params = array('moduletype' => 'qcreate', 'courseid' => $courseid);
    $sql = 'SELECT q.*, cm.idnumber as cmidnumber, q.course as courseid
            FROM {qcreate} q, {course_modules} cm, {modules} m
            WHERE m.name=:moduletype AND m.id=cm.module AND cm.instance=q.id AND q.course=:courseid';

    if ($qcreates = $DB->get_records_sql($sql, $params)) {
        foreach ($qcreates as $qcreate) {
            qcreate_grade_item_update($qcreate, 'reset');
        }
    }
}

/**
 * Implementation of the function for printing the form elements that control
 * whether the course reset functionality affects the qcreate.
 *
 *
 * @param $mform form passed by reference
 */
function qcreate_reset_course_form_definition(&$mform) {
    $mform->addElement('header', 'qcreateheader', get_string('modulenameplural', 'qcreate'));
    $mform->addElement('advcheckbox', 'reset_qcreate', get_string('deletegrades', 'qcreate'));
}

/**
 * Course reset form defaults.
 *
 *
 * @param stdClass $course
 * @return array
 */
function qcreate_reset_course_form_defaults($course) {
    return array('reset_qcreate' => 1);
}

/**
 * Used by course/user.php to display this module's user activity outline.
 * @param object $course as this is a standard function this is required but not used here
 * @param object $user user object
 * @param object $mod not used here
 * @param object $qcreate qcreate object
 * @return object A standard object with 2 variables: info (grade for this user) and
 * time (last modified)
 */
function qcreate_user_outline($course, $user, $mod, $qcreate) {
    global $DB, $CFG;

    require_once($CFG->libdir . '/gradelib.php');
    $result = new stdClass();
    $result->info = get_string('questionscreated', 'qcreate', qcreate_get_qestions_number($user->id, $qcreate));
    $grades = grade_get_grades($course->id, 'mod', 'qcreate', $qcreate->id, $user->id);

    if (empty($grades->items[0]->grades)) {
        return null;
    } else {
        $grade = reset($grades->items[0]->grades);
        $result->info .= ', ' . get_string('grade') . ': ' . $grade->str_long_grade;
    }
    $result->time = $grade->dategraded;

    return $result;
}

/**
 * Print a detailed representation of what a  user has done with
 * a given particular instance of this module, for user activity reports.
 *
 * @param object $course
 * @param object $user
 * @param object $coursemodule
 * @param object $qcreate
 * @return bool
 */
function qcreate_user_complete($course, $user, $coursemodule, $qccreate) {
    global $CFG;
    require_once($CFG->dirroot . '/mod/qcreate/locallib.php');

    $context = context_module::instance($coursemodule->id);
    $qcreateobj = new qcreate($context, $coursemodule, $course);
    echo $qcreateobj->view_student_summary($user, false);
    return true;
}

/**
 * Print recent activity from all qcreates in a given course
 *
 * This is used by the recent activity block
 * @param mixed $course the course to print activity for
 * @param bool $viewfullnames boolean to determine whether to show full names or not
 * @param int $timestart the time the rendering started
 * @return bool true if activity was printed, false otherwise.
 */
function qcreate_print_recent_activity($course, $viewfullnames, $timestart) {
    global $CFG, $DB, $OUTPUT;
    if (!defined('QCREATE_RECENT_ACTIVITY_LIMIT')) {
        define('QCREATE_RECENT_ACTIVITY_LIMIT', 20);
    }
    $modinfo = get_fast_modinfo($course);
    $ids = array();
    $params = array();

    foreach ($modinfo->cms as $cm) {
        if ($cm->modname != 'qcreate') {
            continue;
        }
        if (!$cm->uservisible) {
            continue;
        }
        $modcontext = context_module::instance($cm->id);
        $ids[$cm->instance] = $modcontext->id;
    }

    if (!$ids) {
        return false;
    }

    // Generate list of question categories ids for all qcreates in the course.
    $qcatids = array();
    foreach ($ids as $qcreateinstanceid => $qcatid) {
        $qcatids[] = ' qc.contextid = :qccontid'.$qcreateinstanceid.' ';
        $params['qccontid'.$qcreateinstanceid] = $qcatid;
    }

    if (count($qcatids) > 0) {
        $qcatsql = 'AND ('. implode($qcatids, ' OR ') .') ';
    } else {
        $qcatsql = '';
    }

    $params['timestart'] = $timestart;

    // Generate list of created questions for all qcreate in the course.
    $userfields = user_picture::fields('u', null, 'userid');
    $sql = 'SELECT q.id, q.name AS qname, qc.id as qcat, q.timemodified, g.grade as rawgrade, a.name AS aname, a.id as aid, ' .
                                                     $userfields .
                                           '  FROM {question} q
                                              LEFT JOIN {user} u ON u.id = q.createdby
                                              LEFT JOIN {question_categories} qc ON qc.id = q.category
                                              LEFT JOIN {qcreate_grades} g ON g.questionid = q.id
                                              LEFT JOIN {context} c ON c.id = qc.contextid
                                              LEFT JOIN {course_modules} cm ON cm.id = c.instanceid
                                              LEFT JOIN {qcreate} a ON a.id = cm.instance
                                              WHERE q.timecreated > :timestart ' .
                                                      $qcatsql .
                                           '   ORDER BY q.timecreated ASC';

    if ($questions = $DB->get_records_sql($sql, $params)) {
        echo $OUTPUT->heading(get_string('newquestions', 'qcreate').':', 3);
        $strftimerecent = get_string('strftimerecent');
        $questioncount = 0;
        foreach ($questions as $question) {
            if ($questioncount < QCREATE_RECENT_ACTIVITY_LIMIT) {
                $urlparams = array('a' => $question->aid);
                $link = new moodle_url($CFG->wwwroot.'/mod/qcreate/view.php', $urlparams);
                print_recent_activity_note($question->timemodified,
                                   $question,
                                   $question->aname,
                                   $link,
                                   false,
                                   $viewfullnames);
                $questioncount += 1;
            } else {
                $numnewquestions = count($questions);
                echo '<div class="head"><div class="activityhead">' .
                        get_string('andmorenewquestions', 'qcreate', $numnewquestions - QCREATE_RECENT_ACTIVITY_LIMIT) .
                        '</div></div>';
                break;
            }
        }
        return true;
    }
    return false;  // True if anything was printed, otherwise false.
}

/**
 * Returns all questions created since a given time.
 *
 * @param array $activities The activity information is returned in this array
 * @param int $index The current index in the activities array
 * @param int $timestart The earliest activity to show
 * @param int $courseid Limit the search to this course
 * @param int $cmid The course module id
 * @param int $userid Optional user id
 * @param int $groupid Optional group id
 * @return void
 */
function qcreate_get_recent_mod_activity(&$activities,
                                        &$index,
                                        $timestart,
                                        $courseid,
                                        $cmid,
                                        $userid=0,
                                        $groupid=0) {
    global $CFG, $COURSE, $USER, $DB;

    if ($COURSE->id == $courseid) {
        $course = $COURSE;
    } else {
        $course = $DB->get_record('course', array('id' => $courseid));
    }

    $modinfo = get_fast_modinfo($course);

    $cm = $modinfo->get_cm($cmid);
    $modcontext = context_module::instance($cm->id);
    $params = array();
    if ($userid) {
        $userselect = 'AND u.id = :userid';
        $params['userid'] = $userid;
    } else {
        $userselect = '';
    }

    if ($groupid) {
        $groupselect = 'AND gm.groupid = :groupid';
        $groupjoin   = 'JOIN {groups_members} gm ON  gm.userid=u.id';
        $params['groupid'] = $groupid;
    } else {
        $groupselect = '';
        $groupjoin   = '';
    }

    $params['contextid'] = $modcontext->id;
    $params['timestart'] = $timestart;

    $userfields = user_picture::fields('u', null, 'userid');

    $sql = 'SELECT q.id, q.qtype AS qtype, q.name AS qname, qc.id as qcat, q.timecreated, g.grade as rawgrade, a.grade, ' .
                                                     $userfields .
                                           '  FROM {question} q
                                              LEFT JOIN {user} u ON u.id = q.createdby
                                              LEFT JOIN {question_categories} qc ON qc.id = q.category
                                              LEFT JOIN {qcreate_grades} g ON g.questionid = q.id
                                              LEFT JOIN {qcreate} a ON a.id = g.qcreateid
                                           ' .
                                                     $groupjoin .
                                            '  WHERE q.timecreated > :timestart AND
                                                     qc.contextid = :contextid
                                                     ' . $userselect . ' ' . $groupselect .
                                            '  ORDER BY q.timecreated ASC';

    if (!$questions = $DB->get_records_sql($sql, $params)) {
         return;
    }

    $groupmode       = groups_get_activity_groupmode($cm, $course);
    $cmcontext       = context_module::instance($cm->id);
    $grader          = has_capability('moodle/grade:viewall', $cmcontext);
    $accessallgroups = has_capability('moodle/site:accessallgroups', $cmcontext);
    $viewfullnames   = has_capability('moodle/site:viewfullnames', $cmcontext);

    $show = array();
    foreach ($questions as $question) {
        if ($question->userid == $USER->id) {
            $show[] = $question;
            continue;
        }
        // A graded question may be considered private -
        // only graders will see it if specified.
        if (!$grader) {
            continue;
        }

        if ($groupmode == SEPARATEGROUPS and !$accessallgroups) {
            if (isguestuser()) {
                // Shortcut - guest user does not belong into any group.
                continue;
            }

            // This will be slow - show only users that share group with me in this cm.
            if (!$modinfo->get_groups($cm->groupingid)) {
                continue;
            }
            $usersgroups = groups_get_all_groups($course->id, $question->userid, $cm->groupingid);
            if (is_array($usersgroups)) {
                $usersgroups = array_keys($usersgroups);
                $intersect = array_intersect($usersgroups, $modinfo->get_groups($cm->groupingid));
                if (empty($intersect)) {
                    continue;
                }
            }
        }
        $show[] = $question;
    }

    if (empty($show)) {
        return;
    }

    if ($grader) {
        require_once($CFG->libdir . '/gradelib.php');
        $userids = array();
        foreach ($show as $id => $question) {
            $userids[] = $question->userid;
        }
        $grades = grade_get_grades($courseid, 'mod', 'qcreate', $cm->instance, $userids);
    }

    $aname = format_string($cm->name, true);
    foreach ($show as $question) {
        $activity = new stdClass();

        $activity->type         = 'qcreate';
        $activity->cmid         = $cm->id;
        $activity->name         = $aname;
        $activity->sectionnum   = $cm->sectionnum;
        $activity->timestamp    = $question->timecreated;
        $activity->user         = new stdClass();
        if ($grader) {
            if ($question->rawgrade) {
                $activity->grade = get_string('grade').': '.$question->rawgrade . '/' . $question->grade;
            } else {
                $activity->grade = get_string('notgraded', 'qcreate');
            }
        }

        $userfields = explode(',', user_picture::fields());
        foreach ($userfields as $userfield) {
            if ($userfield == 'id') {
                // Aliased in SQL above.
                $activity->user->{$userfield} = $question->userid;
            } else {
                $activity->user->{$userfield} = $question->{$userfield};
            }
        }
        $activity->user->fullname = fullname($question, $viewfullnames);

        $activities[$index++] = $activity;
    }

    return;
}

/**
 * Print recent activity from all qcreates in a given course
 *
 * This is used by course/recent.php
 * @param stdClass $activity
 * @param int $courseid
 * @param bool $detail
 * @param array $modnames
 */
function qcreate_print_recent_mod_activity($activity, $courseid, $detail, $modnames) {
    global $CFG, $OUTPUT;

    echo '<table border="0" cellpadding="3" cellspacing="0" class="qcreatement-recent">';

    echo '<tr><td class="userpicture" valign="top">';
    echo $OUTPUT->user_picture($activity->user);
    echo '</td><td>';

    if ($detail) {
        $modname = $modnames[$activity->type];
        echo '<div class="title">';
        echo '<img src="' . $OUTPUT->pix_icon('icon', 'qcreate') . '">';
        echo '<a href="' . $CFG->wwwroot . '/mod/qcreate/view.php?id=' . $activity->cmid . '">';
        echo $activity->name;
        echo '</a>';
        echo '</div>';
    }

    if (isset($activity->grade)) {
        echo '<div class="grade">';
        echo $activity->grade;
        echo '</div>';
    }

    echo '<div class="user">';
    echo "<a href=\"$CFG->wwwroot/user/view.php?id={$activity->user->id}&amp;course=$courseid\">";
    echo "{$activity->user->fullname}</a>  - " . userdate($activity->timestamp);
    echo '</div>';

    echo '</td></tr></table>';
}

/**
 * Function to be run periodically according to the scheduled task manager
 * This function synchronize students access to questions according
 * to the instance settings and to the open/closed status of the instance.
 *
 **/
function qcreate_student_q_access_sync($cmcontext, $qcreate, $forcesync= false) {
    global $DB;

    // Check if a check is needed.
    $timenow = time();
    $activityopen = ($qcreate->timeopen == 0 ||($qcreate->timeopen < $timenow)) &&
        ($qcreate->timeclose == 0 ||($qcreate->timeclose > $timenow));
    $activitywasopen = ($qcreate->timeopen == 0 ||($qcreate->timeopen < $qcreate->timesync)) &&
        ($qcreate->timeclose == 0 ||($qcreate->timeclose > $qcreate->timesync));
    $needsync = (empty($qcreate->timesync) || // No sync has happened yet.
            ($activitywasopen != $activityopen));

    if ($forcesync || $needsync) {
        $studentrole = get_archetype_roles('student');
        $studentrole = reset($studentrole);

        if ($activityopen) {
            $capabilitiestoassign = array (
                0 => array('moodle/question:add' => CAP_ALLOW, 'moodle/question:usemine' => CAP_PREVENT,
                        'moodle/question:viewmine' => CAP_PREVENT, 'moodle/question:editmine' => CAP_PREVENT),
                1 => array('moodle/question:add' => CAP_ALLOW, 'moodle/question:usemine' => CAP_ALLOW,
                        'moodle/question:viewmine' => CAP_PREVENT, 'moodle/question:editmine' => CAP_PREVENT),
                2 => array('moodle/question:add' => CAP_ALLOW, 'moodle/question:usemine' => CAP_ALLOW,
                        'moodle/question:viewmine' => CAP_ALLOW, 'moodle/question:editmine' => CAP_PREVENT),
                3 => array('moodle/question:add' => CAP_ALLOW, 'moodle/question:usemine' => CAP_ALLOW,
                        'moodle/question:viewmine' => CAP_ALLOW, 'moodle/question:editmine' => CAP_ALLOW));
            foreach ($capabilitiestoassign[$qcreate->studentqaccess] as $capability => $permission) {
                    assign_capability($capability, $permission, $studentrole->id, $cmcontext->id, true);
            }
        } else {
            $capabilitiestounassign = array (
                'moodle/question:add', 'moodle/question:usemine', 'moodle/question:viewmine', 'moodle/question:editmine');
            foreach ($capabilitiestounassign as $capability) {
                    unassign_capability($capability, $studentrole->id, $cmcontext->id);
            }
        }
        $DB->set_field('qcreate', 'timesync', $timenow, array('id' => $qcreate->id));
    }
}

/**
 * Returns all other caps used in the module
 *
 * @example return array('moodle/site:accessallgroups');
 * @return array
 */
function qcreate_get_extra_capabilities() {
    global $CFG;
    require_once($CFG->libdir . '/questionlib.php');
    $caps = question_get_all_capabilities();
    $caps[] = 'moodle/site:accessallgroups';
    return $caps;
}

/**
 * This function returns if a scale is being used by one qcreate
 * if it has support for grading and scales. Commented code should be
 * modified if necessary. See forum, glossary or journal modules
 * as reference.
 *
 * @param int $qcreateid ID of an instance of this module
 * @return mixed
 * @todo Finish documenting this function
 **/
function qcreate_scale_used ($qcreateid, $scaleid) {
    global $DB;

    $return = false;
    $rec = $DB->get_record('qcreate', array('id' => $qcreateid, 'grade' => -$scaleid));

    if (!empty($rec) && !empty($scaleid)) {
        $return = true;
    }

    return $return;
}

/**
 * Checks if scale is being used by any instance of qcreate
 *
 * This is used to find out if scale used anywhere
 * @param int $scaleid
 * @return boolean True if the scale is used by any qcreate
 */
function qcreate_scale_used_anywhere($scaleid) {
    global $DB;

    if ($scaleid and $DB->record_exists('qcreate', array('grade' => -$scaleid))) {
        return true;
    } else {
        return false;
    }
}

/**
 * Create/update grade item for given qcreate
 *
 * @param stdClass $qcreate qcreate object with extra cmidnumber
 * @param mixed $grades Optional array/object of grade(s); 'reset' means reset grades in gradebook
 * @return int 0 if ok
 */
function qcreate_grade_item_update($qcreate, $grades=null) {
    global $CFG;
    require_once($CFG->libdir . '/gradelib.php');

    if (!isset($qcreate->courseid)) {
        $qcreate->courseid = $qcreate->course;
    }

    if (array_key_exists('cmidnumber', $qcreate)) { // It may not be always present.
        $params = array('itemname' => $qcreate->name, 'idnumber' => $qcreate->cmidnumber);
    } else {
        $params = array('itemname' => $qcreate->name);
    }

    if ($qcreate->grade > 0) {
        $params['gradetype'] = GRADE_TYPE_VALUE;
        $params['grademax']  = $qcreate->grade;
        $params['grademin']  = 0;

    } else if ($qcreate->grade < 0) {
        $params['gradetype'] = GRADE_TYPE_SCALE;
        $params['scaleid']   = -$qcreate->grade;
    } else {
        $params['gradetype'] = GRADE_TYPE_NONE;
    }

    if ($grades === 'reset') {
        $params['reset'] = true;
        $grades = null;
    }
    $params['itemnumber'] = 0;
    return grade_update('mod/qcreate', $qcreate->courseid, 'mod', 'qcreate', $qcreate->id, 0, $grades, $params);
}

/**
 * Process submitted grades.
 * @param opbject qcreate the qcreate object with cmidnumber set to $cm->id
 * @param object cm coursemodule object
 * @param array users array of ids of users who can take part in this activity.
 */
function qcreate_process_grades($qcreate, $cm, $users) {
    global $DB, $OUTPUT;

    // Do the fast grading stuff.
    $grading    = false;
    $commenting = false;
    $qids        = array();

    $submitcomments = optional_param_array('gradecomment', 0, PARAM_RAW);
    $submittedgrades = optional_param_array('menu', 0, PARAM_INT);
    if ($submitcomments) {
        $commenting = true;
        // Process array of submitted comments.
        $qids = array_keys($submitcomments);
    }
    if ($submittedgrades) {
        $grading = true;
        // Process array of submitted grades.
        $qids = array_unique(array_merge($qids, array_keys($submittedgrades)));
    }
    if (!$qids) {
        $message = $OUTPUT->notification(get_string('nothingtosave', 'qcreate'), 'notifyproblem');
        return $message;
    }
    // Get the cleaned keys which are the questions ids.
    $qids = clean_param_array($qids, PARAM_INT);
    if ($qids) {
        $toupdates = array();
        $questions = $DB->get_records_select('question', 'id IN ('.implode(',', $qids).') AND '.
                                        'createdby IN ('.implode(',', $users).')');
        foreach ($qids as $qid) {
            // Test that qid is a question created by one of the users we can grade.
            if (isset($questions[$qid])) {
                $question = $questions[$qid];
                // TODO fix outcomes,
                // and call qcreate_process_outcomes.
                if ($grading) {
                    $submittedgrade = $submittedgrades[$qid];
                } else {
                    $submittedgrade = -1; // Not graded.
                }
                if ($commenting) {
                    $submitcomment = $submitcomments[$qid];
                } else {
                    $submitcomment = ''; // No comment.
                }

                if (qcreate_process_local_grade($qcreate, $question, false, true, $submittedgrade, $submitcomment)) {
                    $toupdates[] = $question->createdby;
                }
            }

        }
        $toupdates = array_unique($toupdates);
        foreach ($toupdates as $toupdate) {
            qcreate_update_grades($qcreate, $toupdate);
        }
    }

    $message = $OUTPUT->notification(get_string('changessaved'), 'notifysuccess');

    return $message;
}

/**
 * Process submitted grades.
 * @param opbject qcreate the qcreate object with cmidnumber set to $cm->id
 * @param object question with id and createdby
 * @param array users array of ids of users who can take part in this activity.
 */
function qcreate_process_local_grade($qcreate, $question,
        $forcenewgrade = false, $notifystudent = false, $submittedgrade = -1, $submittedcomment = '') {
    global $CFG, $USER, $DB;
    require_once($CFG->dirroot . '/mod/qcreate/locallib.php');

    $context = context_module::instance($qcreate->cmidnumber);
    $qcreateobj = new qcreate($context, null, null);
    if ($forcenewgrade || !$grade = qcreate_get_local_grade($qcreate, $question->id)) {
        $grade = qcreate_prepare_new_local_grade($qcreate, $question);
        $newgrade = true;
    } else {
        $newgrade = false;
    }

    // For fast grade, we need to check if any changes take place.
    $updatedb = false;

    $updatedb = $updatedb || ($grade->grade != $submittedgrade);
    $grade->grade = $submittedgrade;

    $submittedcomment = trim($submittedcomment);
    $updatedb = $updatedb || ($grade->gradecomment != stripslashes($submittedcomment));
    $grade->gradecomment = $submittedcomment;

    $grade->userid    = $question->createdby;
    $grade->teacher    = $USER->id;
    if ($grade->grade != -1) {
        $grade->timemarked = time();
    } else {
        $grade->timemarked = 0;
    }

    // If it is not an update, we don't change the last modified time etc.
    // This will also not write into database if no gradecomment and grade is entered.

    if ($forcenewgrade || $updatedb) {
        if ($newgrade) {
            if (!$sid = $DB->insert_record('qcreate_grades', $grade)) {
                return false;
            }
            $grade->id = $sid;

            $params = array(
                'context' => context_module::instance($qcreate->cmidnumber),
                'objectid' => $grade->id,
                'relateduserid' => $grade->userid,
                'other' => array(
                    'qcreateid' => $qcreate->id,
                    'questionid' => $question->id,
                )
            );
            $event = \mod_qcreate\event\question_graded::create($params);
            $event->add_record_snapshot('qcreate_grades', $grade);
            $event->trigger();

        } else {
            if (!$DB->update_record('qcreate_grades', $grade)) {
                return false;
            }

            $params = array(
                'context' => context_module::instance($qcreate->cmidnumber),
                'objectid' => $grade->id,
                'relateduserid' => $grade->userid,
                'other' => array(
                    'qcreateid' => $qcreate->id,
                    'questionid' => $question->id,
                )
            );
            $event = \mod_qcreate\event\question_regraded::create($params);
            $event->add_record_snapshot('qcreate_grades', $grade);
            $event->trigger();

        }
        if ($notifystudent) {
            $qcreateobj->notify_student_question_graded($question);
        }
    }
    return $updatedb;

}

/**
 * Return the number of question created by a particular user for a qceate activity
 *
 * @param $integer userid id of the user, 0 means all users
 * @param object $qcreate object
 * @return integer
 */
function qcreate_get_qestions_number($userid, $qcreate) {
    global $DB;

    $cm = get_coursemodule_from_instance('qcreate', $qcreate->id);
    $modcontext = context_module::instance($cm->id);

    $params = array();
    $whereqtype = '';
    $whereuser = '';
    if ($qcreate->allowed != 'ALL') {
            $allowedparts = explode(',', $qcreate->allowed);
            $allowedlist = "'".join("','", $allowedparts)."'";
            $whereqtype = 'q.qtype IN ('.$allowedlist.') AND ';
    }

    if ($userid) {
        $params['userid'] = $userid;
        $whereuser = 'q.createdby = :userid AND ';
    }

    $params['contextid'] = $modcontext->id;
    $countsql = 'SELECT COUNT(q.id) FROM {question} q,{question_categories} c '.
               'WHERE ' . $whereqtype . $whereuser .
                'q.hidden=\'0\' AND q.parent=\'0\' AND q.category = c.id and c.contextid = :contextid';

    return $DB->count_records_sql($countsql, $params);
}
/**
 * Load the local grade object for a particular user
 *
 * @param $userid int The id of the user whose grade we want or 0 in which case USER->id is used
 * @param $qid int The id of the question whose grade we want
 * @param $createnew boolean optional Defaults to false. If set to true a new grade object will be created in the database
 * @return object The grade
 */
function qcreate_get_local_grade($qcreate, $qid, $createnew = false) {
    global $DB;

    $grade = $DB->get_record_sql(
            'SELECT * FROM {qcreate_grades} WHERE qcreateid=? AND questionid=? ORDER BY timemarked DESC LIMIT 1',
            array($qcreate->id, $qid));

    if ($grade || !$createnew) {
        return $grade;
    }
    $newgrade = qcreate_prepare_new_local_grade($qcreate, $qid);
    if (!$DB->insert_record('qcreate_grades', $newgrade)) {
        print_error('Could not insert a new empty grade');
    }

    return $DB->get_record('qcreate_grades', array('qcreateid' => $qcreate->id, 'questionid' => $qid));
}

/**
 * Instantiates a new grade object for a given user
 *
 * Sets the qcreate, userid and times, everything else is set to default values.
 * @param $userid int The userid for which we want a grade object
 * @return object The grade
 */
function qcreate_prepare_new_local_grade($qcreate, $question) {
    $grade = new stdClass();
    $grade->qcreateid   = $qcreate->id;
    $grade->questionid  = $question->id;
    $grade->grade        = -1;
    $grade->gradecomment      = '';
    $grade->teacher      = 0;
    $grade->timemarked   = 0;
    return $grade;
}

/**
 * Update grades in the gradebook.
 *
 * @param object $qcreate null means all qcreates
 * @param int $userid specific user only, 0 mean all
 * @param bool $nullifnone If true and the user has no grade then a grade item with rawgrade == null will be inserted
 */
function qcreate_update_grades($qcreate=null, $userid=0, $nullifnone = true) {
    global $CFG, $DB;
    require_once($CFG->libdir . '/gradelib.php');

    if ($qcreate != null) {
        if ($qcreate->grade == 0) {
            qcreate_grade_item_update($qcreate);
        } else if ($grades = qcreate_get_user_grades($qcreate, $userid)) {
            qcreate_grade_item_update($qcreate, $grades);

        } else if ($userid && $nullifnone) {
            $grade = new stdClass();
            $grade->userid = $userid;
            $grade->rawgrade = null;
            qcreate_grade_item_update($qcreate, $grade);

        } else {
            qcreate_grade_item_update($qcreate);
        }
    } else {
        $sql = "SELECT q.*, cm.id as cmidnumber, q.course as courseid
                  FROM {qcreate} q, {course_modules} cm, {modules} m
                 WHERE m.name='qcreate' AND m.id=cm.module AND cm.instance=q.id";
        $rs = $DB->get_recordset_sql($sql);
        foreach ($rs as $qcreate) {
            qcreate_grade_item_update($qcreate);
            if ($qcreate->grade != 0) {
                qcreate_update_grades($qcreate, $userid);
            }
        }
        $rs->close();
    }
}

/**
 * Return local grades for given user or all users.
 *
 * @param object $qcreate
 * @param mixed $userid optional user id or array of userids, 0 means all users
 * @return array array of grades, false if none
 */
function qcreate_get_user_grades($qcreate, $userid=0) {
    global $DB;

    $cm = get_coursemodule_from_instance('qcreate', $qcreate->id, 0, false, MUST_EXIST);
    $context = context_module::instance($cm->id);
    if (is_array($userid)) {
        $user = "u.id IN (".implode(',', $userid).") AND";
    } else if ($userid) {
        $user = "u.id = $userid AND";
    } else {
        $user = '';
    }

    $sql = "SELECT q.id, u.id AS userid, g.grade AS rawgrade, g.gradecomment AS feedback,
            g.teacher AS usermodified, q.qtype AS qtype
            FROM {user} u, {question_categories} qc, {question} q
            LEFT JOIN {qcreate_grades} g ON g.questionid = q.id
            WHERE $user u.id = q.createdby AND qc.id = q. category AND qc.contextid={$context->id}
            ORDER BY rawgrade DESC";
    $localgrades = $DB->get_records_sql($sql);

    $gradesbyuserids = array();
    foreach ($localgrades as $k => $v) {
        if (!isset($gradesbyuserids[$v->userid])) {
            $gradesbyuserids[$v->userid] = array();
        }
        if ($v->rawgrade == -1) {
            $v->rawgrade = null;
        }
        $gradesbyuserids[$v->userid][$k] = $v;
    }
    $aggregategradebyuserids  = array();
    foreach ($gradesbyuserids as $userid => $gradesbyuserid) {
        $aggregategradebyuserids[$userid] = qcreate_grade_aggregate($gradesbyuserid, $qcreate);
    }
    return $aggregategradebyuserids;
}
/**
 * @param array gradesforuser an array of objects from local grades tables
 * @return aggregated grade
 */
function qcreate_grade_aggregate($gradesforuser, $qcreate) {
    $aggregated = new stdClass();
    $aggregated->rawgrade = 0;
    $aggregated->usermodified = 0;
    $requireds = qcreate_required_qtypes($qcreate);

    // Need to make sure that we grade required questions and then any extra.
    // Grades are sorted for descending raw grade.
    $counttotalrequired = $qcreate->totalrequired;
    if ($requireds) {
        foreach ($requireds as $required) {
            foreach ($gradesforuser as $key => $gradeforuser) {
                if ($gradeforuser->qtype == $required->qtype) {
                    $aggregated->rawgrade += ($gradeforuser->rawgrade / $qcreate->totalrequired);
                    $aggregated->userid = $gradeforuser->userid;
                    unset($gradesforuser[$key]);
                    $required->no--;
                    $counttotalrequired--;
                    if ($required->no == 0) {
                        // Go on to the next required type.
                        break;
                    }
                }
            }
        }
    }

    if ($counttotalrequired != 0) {
        // Now grade the remainder of the questions.
        if ($qcreate->allowed != 'ALL') {
            $allowall = false;
            $allowed = explode(',', $qcreate->allowed);
        } else {
            $allowall = true;
        }

        foreach ($gradesforuser as $key => $gradeforuser) {
            if ($allowall || in_array($gradeforuser->qtype, $allowed)) {
                $aggregated->rawgrade += ($gradeforuser->rawgrade / $qcreate->totalrequired);
                $aggregated->userid = $gradeforuser->userid;
                $counttotalrequired--;
                if ($counttotalrequired == 0) {
                    break;
                }
            }
        }
    }

    $totalrequireddone = $qcreate->totalrequired - $counttotalrequired;

    $aggregated->rawgrade = $aggregated->rawgrade * ((100 - $qcreate->graderatio) / 100) + (
            ($totalrequireddone * $qcreate->grade / $qcreate->totalrequired) * ($qcreate->graderatio / 100));

    return $aggregated;
}

/**
 * Get required qtypes for this qcreate activity.
 *
 * @param object qcreate the qcreate object
 * @return array an array of objects
 */
function qcreate_required_qtypes($qcreate) {
    global $DB;

    static $requiredcache = array();
    if (!isset($requiredcache[$qcreate->id])) {
        $requiredcache[$qcreate->id] = $DB->get_records('qcreate_required',
                array('qcreateid' => $qcreate->id), 'qtype', 'qtype, no, id');
    }
    return $requiredcache[$qcreate->id];
}

/**
 * Extends the settings navigation with the qcreate settings
 *
 * This function is called when the context for the page is a qcreate module. This is not called by AJAX
 * so it is safe to rely on the $PAGE.
 *
 * @param settings_navigation $settingsnav {@link settings_navigation}
 * @param navigation_node $qcreatenode {@link navigation_node}
 */
function qcreate_extend_settings_navigation(settings_navigation $settingsnav, navigation_node $qcreatenode=null) {
    global $PAGE, $CFG;
    require_once($CFG->libdir . '/questionlib.php');

    $keys = $qcreatenode->get_children_key_list();
    $beforekey = null;
    $i = array_search('modedit', $keys);
    if ($i === false and array_key_exists(0, $keys)) {
        $beforekey = $keys[0];
    } else if (array_key_exists($i + 1, $keys)) {
        $beforekey = $keys[$i + 1];
    }
    if (has_capability('mod/qcreate:grade', $PAGE->cm->context)) {
        $node = navigation_node::create(get_string('grading', 'qcreate'),
                new moodle_url('/mod/qcreate/edit.php', array('cmid' => $PAGE->cm->id)),
                navigation_node::TYPE_SETTING, null, 'mod_qcreate_edit');
        $qcreatenode->add_node($node, $beforekey);
        $node = navigation_node::create(get_string('exportgood', 'qcreate'),
                new moodle_url('/mod/qcreate/exportgood.php', array('cmid' => $PAGE->cm->id)),
                navigation_node::TYPE_SETTING, null, 'mod_qcreate_exportgood');
        $qcreatenode->add_node($node, $beforekey);
        question_extend_settings_navigation($settingsnav, $PAGE->cm->context)->trim_if_empty();
    }
}

/**
 * Add a get_coursemodule_info function in case any qcreate type wants to add 'extra' information
 * for the course (see resource).
 *
 * Given a course_module object, this function returns any "extra" information that may be needed
 * when printing this activity in a course listing.  See get_array_of_activities() in course/lib.php.
 *
 * @param stdClass $coursemodule The coursemodule object (record).
 * @return cached_cm_info An object on information that the courses
 *                        will know about (most noticeably, an icon).
 */
function qcreate_get_coursemodule_info($coursemodule) {
    global $DB;

    $dbparams = ['id' => $coursemodule->instance];
    $fields = 'id, name, intro, introformat, completionquestions';
    if (!$qcreate = $DB->get_record('qcreate', $dbparams, $fields)) {
        return false;
    }

    $result = new cached_cm_info();
    $result->name = $qcreate->name;

    if ($coursemodule->showdescription) {
        // Convert intro to html. Do not filter cached version, filters run at display time.
        $result->content = format_module_intro('qcreate', $qcreate, $coursemodule->id, false);
    }

    // Populate the custom completion rules as key => value pairs, but only if the completion mode is 'automatic'.
    if ($coursemodule->completion == COMPLETION_TRACKING_AUTOMATIC) {
        $result->customdata['customcompletionrules']['completionquestions'] = $qcreate->completionquestions;
    }

    return $result;
}
/**
 * Callback which returns human-readable strings describing the active completion custom rules for the module instance.
 *
 * @param cm_info|stdClass $cm object with fields ->completion and ->customdata['customcompletionrules']
 * @return array $descriptions the array of descriptions for the custom rules.
 */
function mod_qcreate_get_completion_active_rule_descriptions($cm) {
    // Values will be present in cm_info, and we assume these are up to date.
    if (empty($cm->customdata['customcompletionrules'])
        || $cm->completion != COMPLETION_TRACKING_AUTOMATIC) {
        return [];
    }

    $descriptions = [];
    foreach ($cm->customdata['customcompletionrules'] as $key => $val) {
        switch ($key) {
            case 'completionquestions':
                if (empty($val)) {
                    continue 2;
                }
                $descriptions[] = get_string('completionquestionsdesc', 'qcreate', $val);
                break;
            default:
                break;
        }
    }
    return $descriptions;
}
/**
 * Serves the files from the qcreate file areas
 *
 * @package mod_qcreate
 * @category files
 *
 * @param stdClass $course the course object
 * @param stdClass $cm the course module object
 * @param stdClass $context the qcreate's context
 * @param string $filearea the name of the file area
 * @param array $args extra arguments (itemid, path)
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 */
function qcreate_pluginfile($course, $cm, $context, $filearea, array $args, $forcedownload, array $options=array()) {
    global $DB, $CFG;
    // Special case, sending a question bank export.
    if ($filearea === 'export') {
        list($context, $course, $cm) = get_context_info_array($context->id);
        require_login($course, false, $cm);

        require_once($CFG->dirroot . '/question/editlib.php');
        $contexts = new question_edit_contexts($context);
        // Check export capability.
        $contexts->require_one_edit_tab_cap('export');
        $categoryid = (int)array_shift($args);
        $format      = array_shift($args);
        $cattofile   = array_shift($args);
        $contexttofile = array_shift($args);
        $betterthangrade = (int)array_shift($args);
        $namingother = (bool)array_shift($args);
        $namingtext = array_shift($args);
        $namingfirstname = (bool)array_shift($args);
        $naminglastname = (bool)array_shift($args);
        $namingusername = (bool)array_shift($args);
        $namingactivityname = (bool)array_shift($args);
        $namingtimecreated = (bool)array_shift($args);
        $filename    = array_shift($args);

        if ($namingactivityname) {
            $qcreate = $DB->get_record('qcreate', array('id' => $cm->instance));
        }

        // Load parent class for import/export.
        require_once($CFG->dirroot . '/question/format.php');
        require_once($CFG->dirroot . '/question/editlib.php');
        require_once($CFG->dirroot . '/question/format/' . $format . '/format.php');

        $classname = 'qformat_' . $format;
        if (!class_exists($classname)) {
            send_file_not_found();
        }
        $qformat = new $classname();

        if (!$category = $DB->get_record('question_categories', array('id' => $categoryid))) {
            send_file_not_found();
        }

        $qformat->setContexts($contexts->having_one_edit_tab_cap('export'));
        $qformat->setCourse($course);

        $questions = get_questions_category($category, true );
        if ($betterthangrade > 0) {
            // Filter questions by grade.
            $qkeys = array();
            foreach ($questions as $question) {
                $qkeys[] = $question->id;
            }
            $questionlist = join($qkeys, ',');
            $sql = 'SELECT questionid, grade FROM {qcreate_grades} '.
                    'WHERE questionid IN ('.$questionlist.') AND grade >= '.$betterthangrade;
            if ($goodquestions = $DB->get_records_sql($sql)) {
                foreach ($questions as $zbkey => $question) {
                    if (!array_key_exists($question->id, $goodquestions)) {
                        unset($questions[$zbkey]);
                    }
                }
            } else {
                send_file_not_found();
                print_error('noquestionsabove', 'qcreate', $thispageurl->out());
            }
        }

        if ($namingfirstname||$naminglastname||$namingusername
                ||$namingother||$namingactivityname||$namingtimecreated) {
            if ($namingfirstname||$naminglastname||$namingusername) {
                $useridkeys = array();
                foreach ($questions as $question) {
                    $useridkeys[] = $question->createdby;
                }
                $useridlist = join($useridkeys, ',');
                if (!$users = $DB->get_records_select('user', "id IN ($useridlist)")) {
                    $users = array();
                }
            }
            foreach ($questions as $question) {
                $prefixes = array();
                if ($namingother && !empty($namingtext)) {
                    $prefixes[] = $namingtext;
                }
                if ($namingfirstname) {
                    $prefixes[] = isset($users[$question->createdby]) ? $users[$question->createdby]->firstname : '';
                }
                if ($naminglastname) {
                    $prefixes[] = isset($users[$question->createdby]) ? $users[$question->createdby]->lastname : '';
                }
                if ($namingusername) {
                    $prefixes[] = isset($users[$question->createdby]) ? $users[$question->createdby]->username : '';
                }
                if ($namingactivityname) {
                    $prefixes[] = $qcreate->name;
                }
                if ($namingtimecreated) {
                    $prefixes[] = userdate($question->timecreated, get_string('strftimedatetimeshort'));
                }
                $prefixes[] = $question->name;
                $question->name = join($prefixes, '-');
            }
        }
        $qformat->setQuestions($questions);

        if ($cattofile == 'withcategories') {
            $qformat->setCattofile(true);
        } else {
            $qformat->setCattofile(false);
        }

        if ($contexttofile == 'withcontexts') {
            $qformat->setContexttofile(true);
        } else {
            $qformat->setContexttofile(false);
        }

        if (!$qformat->exportpreprocess()) {
            send_file_not_found();
            print_error('exporterror', 'question', $thispageurl->out());
        }

        // Export data to moodle file pool.
        if (!$content = $qformat->exportprocess()) {
            send_file_not_found();
        }

        send_file($content, $filename, 0, 0, true, true, $qformat->mime_type());
    }

    if ($context->contextlevel != CONTEXT_MODULE) {
        send_file_not_found();
    }

    require_login($course, true, $cm);

    send_file_not_found();
}

function qcreate_question_pluginfile($course, $context, $component,
        $filearea, $qubaid, $slot, $args, $forcedownload, array $options=array()) {
    $fs = get_file_storage();
    $relativepath = implode('/', $args);
    $fullpath = "/$context->id/$component/$filearea/$relativepath";
    if (!$file = $fs->get_file_by_hash(sha1($fullpath)) or $file->is_directory()) {
        send_file_not_found();
    }

    send_stored_file($file, 0, 0, $forcedownload, $options);
}

function qcreate_time_status($qcreate) {
    $timenow = time();
    $available = ($qcreate->timeopen < $timenow &&
         ($timenow < $qcreate->timeclose || !$qcreate->timeclose));
    if ($available) {
        $string = get_string("activityopen", "qcreate");
    } else {
        $string = get_string("activityclosed", "qcreate");
    }
    $string = "<strong>$string</strong>";
    if (!$qcreate->timeopen && !$qcreate->timeclose) {
        return $string.' '.get_string('timenolimit', 'qcreate');
    }
    if ($qcreate->timeopen) {
        if ($timenow < $qcreate->timeopen) {
            $string .= ' '.get_string("timewillopen", "qcreate", userdate($qcreate->timeopen));
        } else {
            $string .= ' '.get_string("timeopened", "qcreate", userdate($qcreate->timeopen));
        }
    }
    if ($qcreate->timeclose) {
        if ($timenow < $qcreate->timeclose) {
            $string .= ' '.get_string("timewillclose", "qcreate", userdate($qcreate->timeclose));
        } else {
            $string .= ' '.get_string("timeclosed", "qcreate", userdate($qcreate->timeclose));
        }
    }
    return $string;
}

/**
 * Check if the module has any update that affects the current user since a given time.
 *
 * @param  cm_info $cm course module data
 * @param  int $from the time to check updates from
 * @param  array $filter  if we need to check only specific updates
 * @return stdClass an object with the different type of areas indicating if they were updated or not
 * @since Moodle 3.2
 */
function qcreate_check_updates_since(cm_info $cm, $from, $filter = array()) {
    global $DB, $USER, $CFG;
    require_once($CFG->dirroot . '/mod/qcreate/locallib.php');

    $updates = course_check_module_updates_since($cm, $from, array(), $filter);

    // Check if questions have been modified or added.
    $context = $cm->context;
    $updates->questions = (object) array('updated' => false);
    $qcreateobj = new qcreate($context, null, null);
    $instance = $qcreateobj->get_instance();

    list($whereqtype, $params) = $qcreateobj->get_allowed_qtypes_where();
    $params['time1'] = $from;
    $params['time2'] = $from;
    if (has_capability('mod/qcreate:grade', $qcreateobj->get_context())) {
        // Teacher should see questions from all users.
        $whereuser = '(q.timemodified > :time1 OR q.timecreated > :time2) AND ';
    } else {
        $whereuser = 'q.createdby = :userid AND (q.timemodified > :time1 OR q.timecreated > :time2) AND ';
        $params['userid'] = $USER->id;
    }
    $params['contextid'] = $qcreateobj->get_context()->id;

    $questionsql = "SELECT q.*, c.id as cid, c.name as cname, g.grade, g.gradecomment, g.id as gid
            FROM {question_categories} c, {question} q
            LEFT JOIN {qcreate_grades} g ON q.id = g.questionid
                                                          AND g.qcreateid = {$instance->id}
            WHERE $whereqtype $whereuser c.contextid = :contextid AND c.id = q.category AND q.hidden='0' AND q.parent='0'";
    $questions = $DB->get_records_sql($questionsql, $params);
    if (!empty($questions)) {
        $updates->questions->updated = true;
        $updates->questions->itemids = array_keys($questions);
    }

    // Check for new grades.
    // TODO : what should see teachers here ?
    $updates->grades = (object) array('updated' => false);
    $params['userid'] = $USER->id;
    $params['time1'] = $from;
    $params['contextid'] = $context->id;
    $sql = "SELECT g.id, g.grade AS rawgrade, g.gradecomment AS feedback,
            g.teacher AS usermodified, q.qtype AS qtype
            FROM {user} u, {question_categories} qc, {question} q
            LEFT JOIN {qcreate_grades} g ON g.questionid = q.id
            WHERE u.id = :userid AND u.id = q.createdby AND qc.id = q.category AND qc.contextid= :contextid
            AND timemarked > :time1
            ORDER BY rawgrade DESC";
    $grades = $DB->get_records_sql($sql, $params);
    if (!empty($grades)) {
        $updates->grades->updated = true;
        $updates->grades->itemids = array_keys($grades);
    }

    return $updates;
}

/**
 * This function receives a calendar event and returns the action associated with it, or null if there is none.
 *
 * This is used by block_myoverview in order to display the event appropriately. If null is returned then the event
 * is not displayed on the block.
 *
 * @param calendar_event $event
 * @param \core_calendar\action_factory $factory
 * @param int $userid User id to use for all capability checks, etc. Set to 0 for current user (default).
 * @return \core_calendar\local\event\entities\action_interface|null
 */
function mod_qcreate_core_calendar_provide_event_action(calendar_event $event,
                                                       \core_calendar\action_factory $factory,
                                                       $userid = 0) {

    global $CFG, $USER;

    require_once($CFG->dirroot . '/mod/qcreate/locallib.php');

    if (empty($userid)) {
        $userid = $USER->id;
    }

    $cm = get_fast_modinfo($event->courseid, $userid)->instances['qcreate'][$event->instance];
    $context = context_module::instance($cm->id);
    $qcreateobj = new qcreate($context, $cm, null);
    $instance = $qcreateobj->get_instance();

    // Check they have capabilities allowing them to view the qcreate.
    if (!has_any_capability(array('mod/qcreate:view', 'mod/qcreate:submit'),
            $qcreateobj->get_context(), $userid)) {
        return null;
    }

    // Check if qcreate is closed, if so don't display it.
    if (!empty($instance->timeclose) && $instance->timeclose <= time()) {
        return null;
    }

    $name = get_string('attemptqcreatenow', 'qcreate');
    $url = new \moodle_url('/mod/qcreate/view.php', [
        'id' => $cm->id
    ]);
    $itemcount = 1;
    $actionable = true;

    // Check if the qcreate is not currently actionable.
    if (!empty($instance->timeopen) && $instance->timeopen > time()) {
        $actionable = false;
    }

    return $factory->create_instance(
        $name,
        $url,
        $itemcount,
        $actionable
    );
}

/**
 * This function calculates the minimum and maximum cutoff values for the timestart of
 * the given event.
 *
 * It will return an array with two values, the first being the minimum cutoff value and
 * the second being the maximum cutoff value. Either or both values can be null, which
 * indicates there is no minimum or maximum, respectively.
 *
 * If a cutoff is required then the function must return an array containing the cutoff
 * timestamp and error string to display to the user if the cutoff value is violated.
 *
 * A minimum and maximum cutoff return value will look like:
 * [
 *     [1505704373, 'The due date must be after the sbumission start date'],
 *     [1506741172, 'The due date must be before the cutoff date']
 * ]
 *
 * If the event does not have a valid timestart range then [false, false] will
 * be returned.
 *
 * @param calendar_event $event The calendar event to get the time range for
 * @param stdClass $instance The module instance to get the range from
 * @return array
 */
function mod_qcreate_core_calendar_get_valid_event_timestart_range(\calendar_event $event, \stdClass $qcreate) {
    global $CFG, $DB;
    require_once($CFG->dirroot . '/mod/qcreate/locallib.php');

    $mindate = null;
    $maxdate = null;

    if ($event->eventtype == QCREATE_EVENT_TYPE_OPEN) {
        if (!empty($qcreate->timeclose)) {
            $maxdate = [
                $qcreate->timeclose,
                get_string('openafterclose', 'qcreate')
            ];
        }
    } else if ($event->eventtype == QCREATE_EVENT_TYPE_CLOSE) {
        if (!empty($qcreate->timeopen)) {
            $mindate = [
                $qcreate->timeopen,
                get_string('closebeforeopen', 'qcreate')
            ];
        }
    }

    return [$mindate, $maxdate];
}
/**
 * This function will update the qcreate module according to the
 * event that has been modified.
 *
 * @throws \moodle_exception
 * @param \calendar_event $event
 * @param stdClass $instance The module instance to get the range from
 */
function mod_qcreate_core_calendar_event_timestart_updated(\calendar_event $event, \stdClass $qcreate) {
    global $CFG, $DB;

    require_once($CFG->dirroot . '/mod/qcreate/locallib.php');
    if (!in_array($event->eventtype, [QCREATE_EVENT_TYPE_OPEN, QCREATE_EVENT_TYPE_CLOSE])) {
        // This isn't an event that we care about so we can ignore it.
        return;
    }

    $courseid = $event->courseid;
    $modulename = $event->modulename;
    $instanceid = $event->instance;
    $modified = false;
    $closedatechanged = false;

    // Something weird going on. The event is for a different module so
    // we should ignore it.
    if (empty($event->instance) || $event->modulename != 'qcreate') {
        return;
    }

    if ($qcreate->id != $instanceid) {
        // The provided qcreate instance doesn't match the event so
        // there is nothing to do here.
        return;
    }

    $coursemodule = get_fast_modinfo($courseid)->instances[$modulename][$instanceid];
    $context = context_module::instance($coursemodule->id);

    // The user does not have the capability to modify this activity.
    if (!has_capability('moodle/course:manageactivities', $context)) {
        return;
    }

    if ($event->eventtype == QCREATE_EVENT_TYPE_OPEN) {
        // If the event is for the qcreate activity opening then we should
        // set the start time of the qcreate activity to be the new start
        // time of the event.
        if ($qcreate->timeopen != $event->timestart) {
            $qcreate->timeopen = $event->timestart;
            $modified = true;
        }
    } else if ($event->eventtype == QCREATE_EVENT_TYPE_CLOSE) {
        // If the event is for the qcreate activity closing then we should
        // set the end time of the qcreate activity to be the new start
        // time of the event.
        if ($qcreate->timeclose != $event->timestart) {
            $qcreate->timeclose = $event->timestart;
            $modified = true;
            $closedatechanged = true;
        }
    }

    if ($modified) {
        $qcreate->timemodified = time();
        $DB->update_record('qcreate', $qcreate);

        qcreate_update_events($qcreate);
        $event = \core\event\course_module_updated::create_from_cm($coursemodule, $context);
        $event->trigger();
    }
}
