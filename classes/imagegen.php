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

namespace mod_wordcards;

defined('MOODLE_INTERNAL') || die();

use mod_wordcards\constants;
use mod_wordcards\utils;

/**
 * Class imagegen
 *
 * @package    mod_wordcards
 * @copyright  2025 Justin Hunt <justin@poodll.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class imagegen {

    protected const DEFAULT_IMAGE_PROMPT = "An image suitable for a word learning flashcard, with no text that illustrates the following [[ttslanguage]] word/phrase: [[term]] ([[[deflanguage]]] translation: [[definition]])";
    protected $progressbar = false;
    protected $mod = false;
    protected $moduleinstance = false;
    protected $conf = false;
    protected $context = false;

    function __construct($mod)
    {
        global $DB;
        $this->mod = $mod;
        $this->moduleinstance = $mod->get_mod();
        $this->cm = get_coursemodule_from_instance('wordcards', $this->moduleinstance->id, 0, false, MUST_EXIST);
        $this->conf = get_config(constants::M_COMPONENT);
        $this->context = $mod->get_context();
    }

    public function make_image_prompt($term) {
        $baseprompt = self::DEFAULT_IMAGE_PROMPT;
        $baseprompt = str_replace('[[ttslanguage]]', $this->moduleinstance->ttslanguage, $baseprompt);
        $baseprompt = str_replace('[[term]]', $term->term, $baseprompt);
        $baseprompt = str_replace('[[definition]]', $term->definition, $baseprompt);
        $baseprompt = str_replace('[[deflanguage]]', $this->moduleinstance->deflanguage, $baseprompt);
        return $baseprompt;
    }

    public function generate_images($termids, $imageprompts, $overallimagecontext)
    {
        global $CFG, $DB;
        $requests = [];
        $requestterms = [];
        $imageurls = [];
        $url = utils::get_cloud_poodll_server() . "/webservice/rest/server.php";
        $token = utils::fetch_token($this->conf->apiuser, $this->conf->apisecret);

        if (empty($token)) {
            return [];
        }

        foreach ($termids as $termid) {
            if (!is_array($imageprompts)) {
                $prompt = $imageprompts;
            } else if (array_key_exists($termid, $imageprompts)) {
                $prompt = $imageprompts[$termid];
            } else {
                // this is a problem, we have no context data for this image.
                continue;
            }

            // Add the style and greate context.
            if ($overallimagecontext && !empty($overallimagecontext) && $overallimagecontext !== "--") {
                $prompt .= PHP_EOL . " in the context of the following topic: " . $overallimagecontext;
            }

            // Do the image generation.
            $payload = $this->prepare_generate_image_payload($prompt, $token);
            if (is_array($payload)) {
                $requests[] = [
                    'url' => $url,
                    'postfields' => format_postdata_for_curlcall($payload),
                ];
                $requestterms[utils::array_key_last($requests)] = $termid;
            }
        }
        if (empty($requests)) {
            return [];
        }

        $curl = new curl();
        $curlopts = [];
        $curlopts['CURLOPT_TIMEOUT'] = 120;
 
        // Update the progress bar.
        if ($this->progressbar) {
          //  $this->progressbar->start_progress("Generate images: {".count($requests)."} ");
        }
        $responses = $curl->multirequest($requests, $curlopts);
        $secondattempt_requests = [];
        $secondattempt_termids = [];
        $cachebuster = '?cb=' . \html_writer::random_id();
        foreach ($responses as $i => $resp) {
            $termid = $requestterms[$i];
            $base64data = $this->process_generate_image_response($resp);
            if ($base64data) {
                // Make file from base64 data.
                $filerecord = $this->base64ToFile($base64data, $termid, false);
                if ($filerecord) {
                    $fileurl = "$CFG->wwwroot/pluginfile.php/" . $this->context->id . "/mod_wordcards/image/" . $termid . $cachebuster;
                    $imageurls[] = ['termid' => $termid, 'url' => $fileurl];
                   // Update the database to indicate that this term has an image.
                    $DB->update_record(constants::M_TERMSTABLE, ['id' => $termid, 'image' => 1, 'imageversion' => time()]);
                }
            } else {
                $secondattempt_requests[] =  $requests[$i];
                $secondattempt_termids[] = $i;
            }
        }

        // Second attempt responses
        if(count($secondattempt_requests) > 0) {
            $responses = $curl->multirequest($secondattempt_requests);
            foreach ($responses as $i => $resp) {
                $termid = $secondattempt_termids[$i];
                $base64data = $this->process_generate_image_response($resp);
                if ($base64data) {
                    // Make file from base64 data.
                    $filerecord = $this->base64ToFile($base64data, $termid, false);
                    if ($filerecord) {
                        $fileurl = "$CFG->wwwroot/pluginfile.php/" . $this->context->id . "/mod_wordcards/image/" . $termid . $cachebuster;
                        $imageurls[] = ['termid' => $termid, 'url' => $fileurl];
                        // Update the database to indicate that this term has an image.
                        $DB->update_record(constants::M_TERMSTABLE, ['id' => $termid, 'image' => 1, 'imageversion' => time()]);
                    }
                }
            }
        }

        // Update the progress bar.
        if ($this->progressbar) {
           // $this->progressbar->end_progress();
        }
        return $imageurls;
    }

  public function make_image_smaller($imagedata) {
        global $CFG;
        require_once($CFG->libdir . '/gdlib.php');

        if (empty($imagedata)) {
            return $imagedata;
        }

        // Create temporary files for resizing
        $randomid = uniqid();
        $temporiginal = $CFG->tempdir . '/aigen_orig_' . $randomid;
        file_put_contents($temporiginal, $imagedata);

        // Resize to reasonable dimensions
        $resizedimagedata = \resize_image($temporiginal,  500, 500, true);

        if (!$resizedimagedata) {
            // If resizing fails, use the original image data
            $resizedimagedata = $imagedata;
        }

        // Clean up temporary file
        if (file_exists($temporiginal)) {
            unlink($temporiginal);
        }

        return $resizedimagedata;
    }

    /**
     * Generates structured data using the CloudPoodll service.
     *
     * @param string $prompt The prompt to generate data for.
     * @return array|false Returns an array with draft file URL, draft item ID, term ID, and base64 data, or false on failure.
     */
    public function generate_image($termid, $prompt)
    {
        global $USER, $DB;
        $params = $this->prepare_generate_image_payload(($prompt));
        if ($params) {
            $url = utils::get_cloud_poodll_server() . "/webservice/rest/server.php";
            $resp = utils::curl_fetch($url, $params);
            $base64data = $this->process_generate_image_response($resp);
            if ($base64data) {
                // Generate draft file
                $filerecord = $this->base64ToFile($base64data, $termid, true);
                if ($filerecord) {
                    $draftid = $filerecord['itemid'];
                    $draftfileurl = \moodle_url::make_draftfile_url(
                        $draftid,
                         $filerecord['filepath'],
                        $filerecord['filename'],
                        false,
                    );
                    return [
                        'drafturl' => $draftfileurl->out(false),
                        'draftitemid' => $draftid,
                        'termid' => $termid,
                        'error' => false,
                    ];
                }
            } else {
                return false;
            }

        } else {
            return false;
        }
    }

    public function base64ToFile($base64data, $termid, $draft = false) {
        global $USER;

        if (empty($base64data)) {
            return false;
        }

        $filename = $termid . '.png';
        $fs = get_file_storage();
        if ($draft) {
            $draftid = file_get_unused_draft_itemid();
            $filerecord = [
                'contextid' => \context_user::instance($USER->id)->id,
                'component' => 'user',
                'filearea'  => 'draft',
                'itemid'    => $draftid,
                'filepath'  => '/',
                'filename'  => $filename,
            ];
        } else {
            $filerecord = [
                        'contextid' => $this->context->id,
                        'component' => constants::M_COMPONENT,
                        'filearea' => 'image',
                        'itemid' => $termid,
                        'filepath' => '/',
                        'filename' => $filename
            ];
        }
        // Create file content
        $filecontent = base64_decode($base64data);
        try {
            // Check if the file already exists
            $existingfile = $fs->get_file_by_hash(sha1($filecontent));
            if ($existingfile) {
                return  $filerecord;
            } else {
                $thefile = $fs->create_file_from_string($filerecord, $filecontent);
                if ($thefile) {
                    return  $filerecord;
                } else {
                    return false;
                }
            }
        } catch (\moodle_exception $e) {
            return false; // Handle error gracefully
        }
    }

    public function prepare_generate_image_payload($prompt, $token = null) {
        global $USER;

        if (!empty($this->conf->apiuser) && !empty($this->conf->apisecret)) {
            if (is_null($token)) {
                $token = utils::fetch_token($this->conf->apiuser, $this->conf->apisecret);
            }
            if (empty($token)) {
                return false;
            }

            $params["wstoken"] = $token;
            $params["wsfunction"] = 'local_cpapi_call_ai';
            $params["moodlewsrestformat"] = 'json';
            $params['appid'] = 'mod_wordcards';
            $params['action'] = 'generate_images';
            $params["subject"] = '1';
            $params["prompt"] = $prompt;
            $params["language"] = $this->moduleinstance->ttslanguage;
            $params["region"] = $this->conf->awsregion;
            $params['owner'] = hash('md5', $USER->username);

            return $params;

        } else {
            return false;
        }
    }

    public function process_generate_image_response($resp) {
        $respobj = json_decode($resp);
        $ret = new \stdClass();
        if (isset($respobj->returnCode)) {
            $ret->success = $respobj->returnCode == '0' ? true : false;
            $ret->payload = json_decode($respobj->returnMessage);
        } else {
            $ret->success = false;
            $ret->payload = "unknown problem occurred";
        }
        if ($ret && $ret->success) {
            if (isset($ret->payload[0]->url)) {
                $url = $ret->payload[0]->url;
                $rawdata = file_get_contents($url);
                if ($rawdata !== false) {
                    $smallerdata = $this->make_image_smaller($rawdata);
                    $base64data = base64_encode($smallerdata);
                    return $base64data;
                }
            } else if (isset($ret->payload[0]->b64_json)) {
                // If the payload has a base64 encoded image, use that.
                $rawbase64data = $ret->payload[0]->b64_json;
                $rawdata = base64_decode($rawbase64data);
                $smallerdata = $this->make_image_smaller($rawdata);
                $base64data = base64_encode($smallerdata);
                return $base64data;
            }
        }
        return null;
    }

}
