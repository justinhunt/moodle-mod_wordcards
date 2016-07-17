<?php
/**
 * Renderer.
 *
 * @package mod_flashcards
 * @author  Frédéric Massart - FMCorz.net
 */

/**
 * Renderer class.
 *
 * @package mod_flashcards
 * @author  Frédéric Massart - FMCorz.net
 */
class mod_flashcards_renderer extends plugin_renderer_base {

    public function definitions_page() {
        global $PAGE;

        $cmid = $PAGE->cm->id;
        $modid = $PAGE->cm->instance;
        $definitions = mod_flashcards_helper::get_definitions($modid);
        if (empty($definitions)) {
            return $OUTPUT->notification(get_string('nodefinitions', 'mod_flashcards'));
        }

        // Get whe the student has seen.
        $seen = mod_flashcards_helper::get_definitions_seen($modid);
        foreach ($seen as $s) {
            $definitions[$s->termid]->seen = true;
        }

        $data = [
            'canmanage' => mod_flashcards_helper::can_manage($PAGE->context),
            'definitions' => array_values($definitions),
            'loading' => get_string('loading', 'mod_flashcards'),
            'loadingurl' => $this->pix_url('i/loading_small')->out(true),
            'markasseen' => get_string('markasseen', 'mod_flashcards'),
            'modid' => $modid,
            'mustseealltocontinue' => get_string('mustseealltocontinue', 'mod_flashcards'),
            'nexturl' => (new moodle_url('/mod/flashcards/local.php', ['id' => $cmid]))->out(true),
            'noteaboutseenforteachers' => get_string('noteaboutseenforteachers', 'mod_flashcards'),
            'notseenurl' => $this->pix_url('not-seen', 'mod_flashcards')->out(true),
            'seenall' => count($definitions) == count($seen),
            'seenurl' => $this->pix_url('seen', 'mod_flashcards')->out(true),
            'termnotseen' => get_string('termnotseen', 'mod_flashcards'),
            'termseen' => get_string('termseen', 'mod_flashcards'),
        ];

        return $this->render_from_template('mod_flashcards/definitions_page', $data);
    }

}
