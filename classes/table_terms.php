<?php
/**
 * Terms table.
 *
 * @package mod_flashcards
 * @author  Frédéric Massart - FMCorz.net
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/tablelib.php');

/**
 * Terms table class.
 *
 * @package mod_flashcards
 * @author  Frédéric Massart - FMCorz.net
 */
class mod_flashcards_table_terms extends table_sql {

    /**
     * Constructor.
     *
     * @param string $uniqueid Unique ID.
     * @param int $modif The module ID.
     */
    public function __construct($uniqueid, $modid) {
        parent::__construct($uniqueid);
        $this->modid = $modid;

        // Define columns.
        $this->define_columns(array(
            'term',
            'definition',
            'actions'
        ));
        $this->define_headers(array(
            get_string('term', 'mod_flashcards'),
            get_string('definition', 'mod_flashcards'),
            get_string('actions')
        ));

        // Define SQL.
        $sqlfields = 't.id, t.term, t.definition';
        $sqlfrom = '{flashcards_terms} t';

        $this->sql = new stdClass();
        $this->sql->fields = $sqlfields;
        $this->sql->from = $sqlfrom;
        $this->sql->where = 't.modid = :modid AND deleted = 0';
        $this->sql->params = ['modid' => $modid];

        // Define various table settings.
        $this->sortable(true, 'term', SORT_ASC);
        $this->no_sorting('actions');
        $this->collapsible(false);
    }

    /**
     * Formats the column.
     *
     * @param stdClass $row Table row.
     * @return string Output produced.
     */
    protected function col_actions($row) {
        global $OUTPUT;

        $actions = [];

        $url = new moodle_url($this->baseurl);
        $url->params(['action' => 'edit', 'termid' => $row->id]);
        $actionlink = $OUTPUT->action_link($url, '', null, null, new pix_icon('t/edit',
            get_string('editterm', 'mod_flashcards', $row->term)));
        $actions[] = $actionlink;

        $action = new confirm_action(get_string('reallydeleteterm', 'mod_flashcards', $row->term));
        $url = new moodle_url($this->baseurl);
        $url->params(['action' => 'delete', 'termid' => $row->id, 'sesskey' => sesskey()]);
        $actionlink = $OUTPUT->action_link($url, '', $action, null, new pix_icon('t/delete',
            get_string('deleteterm', 'mod_flashcards', $row->term)));
        $actions[] = $actionlink;

        return implode(' ', $actions);
    }

    /**
     * Override the default implementation to set a decent heading level.
     */
    public function print_nothing_to_display() {
        global $OUTPUT;

        echo $this->render_reset_button();
        $this->print_initials_bar();
        echo $OUTPUT->heading(get_string('nothingtodisplay'), 4);
    }

}
