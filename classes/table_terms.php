<?php
/**
 * Terms table.
 *
 * @package mod_wordcards
 * @author  Frédéric Massart - FMCorz.net
 */

defined('MOODLE_INTERNAL') || die();
require_once($CFG->libdir . '/tablelib.php');

use core\output\checkbox_toggleall;
use \mod_wordcards\utils;
use \mod_wordcards\constants;

/**
 * Terms table class.
 *
 * @package mod_wordcards
 * @author  Frédéric Massart - FMCorz.net
 */
class mod_wordcards_table_terms extends table_sql {

    /**
     *
     * @var mod_wordcards\output\renderer
     */
    protected $renderer;

    /**
     * Constructor.
     *
     * @param string $uniqueid Unique ID.
     * @param object $mod The module.
     */
    public function __construct($uniqueid, $mod) {
        global $PAGE;
        parent::__construct($uniqueid);
        $this->mod = $mod;
        //this prevents the user changing the ttslanguage eg en-US => en-GB .. the selected voices will not match
        //$this->voices = utils::get_tts_voices($mod->get_mod()->ttslanguage);
        $showall=true;
        $this->voices = utils::get_tts_voices($mod->get_mod()->ttslanguage,$showall);

        // Define columns.
        $this->define_columns(array(
            'bulkselect',
            'term',
            'definition',
            'audio',
            'image',
            'ttsvoice',
            'model_sentence',
            'actions'
        ));
        $this->renderer = $PAGE->get_renderer('mod_wordcards');
        $mastercheckbox = new checkbox_toggleall('delete-term', true, [
            'id' => 'select-all-term',
            'name' => 'select-all-term',
            'label' => get_string('selectall'),
            'selectall' => get_string('selectall'),
            'deselectall' => get_string('deselectall'),
        ]);
        $this->define_headers(array(
            $this->renderer->render($mastercheckbox),
            get_string('term', constants::M_COMPONENT),
            get_string('definition', constants::M_COMPONENT),
                get_string('audiofile', constants::M_COMPONENT),
                get_string('imagefile', constants::M_COMPONENT),
                get_string('ttsvoice', constants::M_COMPONENT),
                get_string('model_sentence', constants::M_COMPONENT),
            get_string('actions')
        ));

//t.model_sentence
        // Define SQL.
        $sqlfields = "t.id, t.term, CASE 
         WHEN CHAR_LENGTH(t.model_sentence) > 15 THEN CONCAT(SUBSTRING(t.model_sentence, 1, 15), '...')
         ELSE t.model_sentence
       END AS model_sentence,t.definition, CASE WHEN t.audio is null or t.audio = '' THEN 'no' ELSE 'yes' END as audio,";
        $sqlfields .= " CASE WHEN t.image is null or t.image = '' THEN 'no' ELSE 'yes' END as image,t.ttsvoice";
        $sqlfrom = " {wordcards_terms} t";

        $this->sql = new stdClass();
        $this->sql->fields = $sqlfields;
        $this->sql->from = $sqlfrom;
        $this->sql->where = 't.modid = :modid AND deleted = 0';
        $this->sql->params = ['modid' => $mod->get_id()];

        // Define various table settings.
        $this->sortable(true, 'term', SORT_ASC);
        $this->no_sorting('actions');
        $this->no_sorting('bulkselect');
        $this->collapsible(false);
    }

    /**
     * Formats the column.
     *
     * @param stdClass $row Table row.
     * @return string Output produced.
     */
    protected function col_ttsvoice($row)
    {
        global $OUTPUT;
        if(array_key_exists($row->ttsvoice,$this->voices)){
            return $this->voices[$row->ttsvoice];
        }else{
            return get_string('invalidvoice',constants::M_COMPONENT);
        }

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

        //non AJAX edit form - defunct
        /*
        $url = new moodle_url($this->baseurl);
        $url->params(['action' => 'edit', 'termid' => $row->id]);
        $actionlink = $OUTPUT->action_link($url, '', null, null, new pix_icon('t/edit',
            get_string('editterm', 'mod_wordcards', $row->term)));
        $actions[] = $actionlink;
        */

        //ajax action
        $ajaxeditlink = $OUTPUT->action_link('#', '', null, array('data-id'=>$row->id,'data-type'=>"edit",'class'=>"mod_wordcards_item_row_editlink"), new pix_icon('t/edit',
                get_string('editterm', 'mod_wordcards', $row->term)));
        $actions[] = $ajaxeditlink;


        $action = new confirm_action(get_string('reallydeleteterm', 'mod_wordcards', $row->term));
        $url = new moodle_url($this->baseurl);
        $url->params(['action' => 'delete', 'termid' => $row->id, 'sesskey' => sesskey()]);
        $actionlink = $OUTPUT->action_link($url, '', $action, null, new pix_icon('t/delete',
            get_string('deleteterm', 'mod_wordcards', $row->term)));
        $actions[] = $actionlink;

        return implode(' ', $actions);
    }

    protected function col_bulkselect($row) {
        $checkbox = new checkbox_toggleall('delete-term', false, [
            'value' => $row->id, 'class' => 'bulkselectcol', 'name' => 'termdeleteid[]'
        ]);
        return $this->renderer->render($checkbox);
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
