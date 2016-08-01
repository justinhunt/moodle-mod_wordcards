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

    public function definitions_page(mod_flashcards_module $mod) {
        global $PAGE, $OUTPUT;

        $definitions = $mod->get_terms();
        if (empty($definitions)) {
            return $OUTPUT->notification(get_string('nodefinitions', 'mod_flashcards'));
        }

        // Get whe the student has seen.
        $seen = $mod->get_terms_seen();
        foreach ($seen as $s) {
            if (!isset($definitions[$s->termid])) {
                // Shouldn't happen.
                continue;
            }
            $definitions[$s->termid]->seen = true;
        }

        $data = [
            'canmanage' => $mod->can_manage(),
            'definitions' => array_values($definitions),
            'loading' => get_string('loading', 'mod_flashcards'),
            'loadingurl' => $this->pix_url('i/loading_small')->out(true),
            'markasseen' => get_string('markasseen', 'mod_flashcards'),
            'modid' => $mod->get_id(),
            'mustseealltocontinue' => get_string('mustseealltocontinue', 'mod_flashcards'),
            'nexturl' => (new moodle_url('/mod/flashcards/local.php', ['id' => $mod->get_cmid()]))->out(true),
            'noteaboutseenforteachers' => get_string('noteaboutseenforteachers', 'mod_flashcards'),
            'notseenurl' => $this->pix_url('not-seen', 'mod_flashcards')->out(true),
            'seenall' => count($definitions) == count($seen),
            'seenurl' => $this->pix_url('seen', 'mod_flashcards')->out(true),
            'termnotseen' => get_string('termnotseen', 'mod_flashcards'),
            'termseen' => get_string('termseen', 'mod_flashcards'),
        ];

        return $this->render_from_template('mod_flashcards/definitions_page', $data);
    }

    public function finish_page(mod_flashcards_module $mod, $globalscattertime) {
        $data = [
            'canmanage' => $mod->can_manage(),
            'finishtext' => "Congratulation! You finished the global scatter in " . $globalscattertime . " seconds. "
                . get_string('congratsitsover', 'mod_flashcards'),
            'modid' => $mod->get_id(),
        ];
        return $this->render_from_template('mod_flashcards/finish_page', $data);
    }

    public function local_page(mod_flashcards_module $mod) {
        $definitions = $mod->get_local_terms();

        $data = [
            'canmanage' => $mod->can_manage(),
            'continue' => get_string('continue'),
            'definitionsjson' => json_encode(array_values($definitions)),
            'modid' => $mod->get_id(),
            'hascontinue' => true,
            'nexturl' => (new moodle_url('/mod/flashcards/global.php', ['id' => $mod->get_cmid()]))->out(true),
        ];

        return $this->render_from_template('mod_flashcards/cards_page', $data);
    }

    public function global_page(mod_flashcards_module $mod) {
        list($state) = $mod->get_state();
        $definitions = $mod->get_global_terms();

        $data = [
            'canmanage' => $mod->can_manage(),
            'continue' => get_string('continue'),
            'definitionsjson' => json_encode(array_values($definitions)),
            'modid' => $mod->get_id(),
            'hascontinue' => $state != mod_flashcards_module::STATE_END,
            'nexturl' => (new moodle_url('/mod/flashcards/finish.php',
                ['id' => $mod->get_cmid(), 'sesskey' => sesskey()]))->out(true),
            'isglobalscatter' => true
        ];

        return $this->render_from_template('mod_flashcards/cards_page', $data);
    }

}
