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
 * Poodll Wordcards
 *
 * @package    mod_wordcards
 * @copyright  2015 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 namespace mod_wordcards;
defined('MOODLE_INTERNAL') || die();

use \mod_wordcards\constants;


/**
 * Functions used generally across this mod
 *
 * @package    mod_wordcards
 * @copyright  2015 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class utils{

    //const CLOUDPOODLL = 'http://localhost/moodle';
   // const CLOUDPOODLL = 'https://vbox.poodll.com/cphost';
    const CLOUDPOODLL = 'https://cloud.poodll.com';

    //are we willing and able to transcribe submissions?
    public static function can_transcribe($instance)
    {
        //we default to true
        //but it only takes one no ....
        $ret = true;

        //The regions that can transcribe
        switch($instance->region){
            default:
                $ret = true;
        }

        //if user disables ai, we do not transcribe
        if(!$instance->enableai){
            $ret =false;
        }

        return $ret;
    }

    //convert a phrase or word to a series of phonetic characters that we can use to compare text/spoken
    public static function convert_to_phonetic($phrase,$language){

        switch($language){
            case 'en':
                $phonetic = metaphone($phrase);
                break;
            case 'ja':
                //gettting phonetics for JP requires php-mecab library doc'd here
                //https://github.com/nihongodera/php-mecab-documentation
                if(extension_loaded('mecab')){
                    $mecab = new \MeCab\Tagger();
                    $nodes=$mecab->parseToNode($phrase);
                    $katakanaarray=[];
                    foreach ($nodes as $n) {
                        $f =  $n->getFeature();
                        $reading = explode(',',$f)[8];
                        if($reading!='*'){
                            $katakanaarray[] =$reading;
                        }
                    }
                    $phonetic=implode($katakanaarray,'');
                    break;
                }
            default:
                $phonetic = $phrase;
        }
        return $phonetic;
    }

    public static function update_stepgrade($modid,$correct){
        global $DB,$USER;
        $mod = \mod_wordcards_module::get_by_modid($modid);
        $records = $DB->get_records(constants::M_ATTEMPTSTABLE, ['modid' => $modid, 'userid' => $USER->id],'timecreated DESC');

        if (!$records) {return false;}
        $record = array_shift($records);
        if (!$record) {return false;}

        $field=false;
        $termcount=0;
        switch($record->state){
            case \mod_wordcards_module::STATE_STEP1:
                $termcount=$mod->get_mod()->step1termcount;
                $field = 'grade1';
                break;
            case \mod_wordcards_module::STATE_STEP2:
                $termcount=$mod->get_mod()->step2termcount;
                $field = 'grade2';
                break;
            case \mod_wordcards_module::STATE_STEP3:
                $termcount=$mod->get_mod()->step3termcount;
                $field = 'grade3';
                break;
            case \mod_wordcards_module::STATE_STEP4:
                $termcount=$mod->get_mod()->step4termcount;
                $field = 'grade4';
                break;
            case \mod_wordcards_module::STATE_STEP5:
                $termcount=$mod->get_mod()->step5termcount;
                $field = 'grade5';
                break;
            case \mod_wordcards_module::STATE_END:
            case \mod_wordcards_module::STATE_TERMS:
            default:
                //do nothing
                break;
        }
        if($field && $termcount && ($termcount>=$correct)){
            $grade = ROUND(($correct / $termcount) * 100, 0);
            $DB->set_field(constants::M_ATTEMPTSTABLE,$field,$grade,array('id'=>$record->id));
        }

        return true;
    }

    //recalculate all final grades
    public static function recalculate_final_grades($moduleinstance){
        global $DB;

        $records = $DB->get_records(constants::M_ATTEMPTSTABLE, ['modid' => $moduleinstance->id]);
        foreach($records as $record){
            self::update_finalgrade($moduleinstance->id,$record->userid);
        }
    }

    //calc and update final grade of a single user
    public static function update_finalgrade($modid, $userid=0){
        global $DB,$USER;

        //if we arrive off the finished page, we are just grading, not regrading..
        if($userid == 0){
            $userid = $USER->id;
            $regrading = false;
        }else{
            $regrading = true;
        }

        $mod = \mod_wordcards_module::get_by_modid($modid);
        $moduleinstance = $mod->get_mod();
        $updateusergradebook = false; //post new grades to gradebook, set to true if find something gradeable

        $states = array(\mod_wordcards_module::STATE_STEP1, \mod_wordcards_module::STATE_STEP2, \mod_wordcards_module::STATE_STEP3,
            \mod_wordcards_module::STATE_STEP4, \mod_wordcards_module::STATE_STEP5);

        $records = $DB->get_records(constants::M_ATTEMPTSTABLE,
            ['modid' => $modid, 'userid' => $userid, 'state'=>\mod_wordcards_module::STATE_END]);

        if (!$records) {return false;}
        foreach($records as $record) {

            ///dont redo grading unless that is what we are ding (ie from recalculate final grades)
            if ($record->totalgrade > 0 && $regrading == false) {
                continue;
            }

            $totalgrade = 0;
            $totalsteps = 0;
            foreach ($states as $state) {
                //if we have a practice type for the step and it has terms, then tally the grade
                if ($moduleinstance->{$state} != \mod_wordcards_module::PRACTICETYPE_NONE) {
                    switch ($state) {
                        case \mod_wordcards_module::STATE_STEP1:
                            $termcount = $moduleinstance->step1termcount;
                            $grade = $record->grade1;
                            break;
                        case \mod_wordcards_module::STATE_STEP2:
                            $termcount = $moduleinstance->step2termcount;
                            $grade = $record->grade2;
                            break;
                        case \mod_wordcards_module::STATE_STEP3:
                            $termcount = $moduleinstance->step3termcount;
                            $grade = $record->grade3;
                            break;
                        case \mod_wordcards_module::STATE_STEP4:
                            $termcount = $moduleinstance->step4termcount;
                            $grade = $record->grade4;
                            break;
                        case \mod_wordcards_module::STATE_STEP5:
                            $termcount = $moduleinstance->step5termcount;
                            $grade = $record->grade5;
                            break;
                        case \mod_wordcards_module::STATE_END:
                        case \mod_wordcards_module::STATE_TERMS:
                        default:
                            $grade = 0;
                            $termcount = 0;
                            break;
                    }
                    if ($termcount > 0) {
                        $totalsteps++;
                        $totalgrade += $grade;
                    }
                }
            }
            if ($totalsteps > 0) {
                $grade = ROUND(($totalgrade / $totalsteps), 0);
                $DB->set_field(constants::M_ATTEMPTSTABLE, 'totalgrade', $grade, array('id' => $record->id));
                $updateusergradebook= true;
            }
        }
        //if we have something to update, do the re-grade
        if( $updateusergradebook) {
            wordcards_update_grades($moduleinstance, $userid, false);
        }
        return true;
    }


    //we use curl to fetch transcripts from AWS and Tokens from cloudpoodll
    //this is our helper
    //we use curl to fetch transcripts from AWS and Tokens from cloudpoodll
    //this is our helper
    public static function curl_fetch($url,$postdata=false)
    {
        global $CFG;

        require_once($CFG->libdir.'/filelib.php');
        $curl = new \curl();

        $result = $curl->get($url, $postdata);
        return $result;
    }

    //This is called from the settings page and we do not want to make calls out to cloud.poodll.com on settings
    //page load, for performance and stability issues. So if the cache is empty and/or no token, we just show a
    //"refresh token" links
    public static function fetch_token_for_display($apiuser,$apisecret){
       global $CFG;

       //First check that we have an API id and secret
        //refresh token
        $refresh = \html_writer::link($CFG->wwwroot . '/mod/wordcards/refreshtoken.php',
                get_string('refreshtoken',constants::M_COMPONENT)) . '<br>';


        $message = '';
        $apiuser = trim($apiuser);
        $apisecret = trim($apisecret);
        if(empty($apiuser)){
           $message .= get_string('noapiuser',constants::M_COMPONENT) . '<br>';
       }
        if(empty($apisecret)){
            $message .= get_string('noapisecret',constants::M_COMPONENT);
        }

        if(!empty($message)){
            return $refresh . $message;
        }

        //Fetch from cache and process the results and display
        $cache = \cache::make_from_params(\cache_store::MODE_APPLICATION, constants::M_COMPONENT, 'token');
        $tokenobject = $cache->get('recentpoodlltoken');

        //if we have no token object the creds were wrong ... or something
        if(!($tokenobject)){
            $message = get_string('notokenincache',constants::M_COMPONENT);
            //if we have an object but its no good, creds werer wrong ..or something
        }elseif(!property_exists($tokenobject,'token') || empty($tokenobject->token)){
            $message = get_string('credentialsinvalid',constants::M_COMPONENT);
        //if we do not have subs, then we are on a very old token or something is wrong, just get out of here.
        }elseif(!property_exists($tokenobject,'subs')){
            $message = 'No subscriptions found at all';
        }
        if(!empty($message)){
            return $refresh . $message;
        }

        //we have enough info to display a report. Lets go.
        foreach ($tokenobject->subs as $sub){
            $sub->expiredate = date('d/m/Y',$sub->expiredate);
            $message .= get_string('displaysubs',constants::M_COMPONENT, $sub) . '<br>';
        }

        //Is app authorised
        if(in_array(constants::M_COMPONENT,$tokenobject->apps) &&
         self::is_site_registered($tokenobject->sites,true)){
            $message .= get_string('appauthorised',constants::M_COMPONENT) . '<br>';
        }else{
            $message .= get_string('appnotauthorised',constants::M_COMPONENT) . '<br>';
        }

        return $refresh . $message;

    }

    //We need a Poodll token to make all this recording and transcripts happen
    public static function fetch_token($apiuser, $apisecret, $force=false)
    {

        $cache = \cache::make_from_params(\cache_store::MODE_APPLICATION, constants::M_COMPONENT, 'token');
        $tokenobject = $cache->get('recentpoodlltoken');
        $tokenuser = $cache->get('recentpoodlluser');
        $apiuser = trim($apiuser);
        $apisecret = trim($apisecret);

        //if we got a token and its less than expiry time
        // use the cached one
        if($tokenobject && $tokenuser && $tokenuser==$apiuser && !$force){
            if($tokenobject->validuntil == 0 || $tokenobject->validuntil > time()){
                return $tokenobject->token;
            }
        }

        // Send the request & save response to $resp
        $token_url =  self::CLOUDPOODLL . "/local/cpapi/poodlltoken.php";
        $postdata = array(
            'username' => $apiuser,
            'password' => $apisecret,
            'service'=>'cloud_poodll'
        );
        $token_response = self::curl_fetch($token_url,$postdata);
        if ($token_response) {
            $resp_object = json_decode($token_response);
            if($resp_object && property_exists($resp_object,'token')) {
                $token = $resp_object->token;
                //store the expiry timestamp and adjust it for diffs between our server times
                if($resp_object->validuntil) {
                    $validuntil = $resp_object->validuntil - ($resp_object->poodlltime - time());
                    //we refresh one hour out, to prevent any overlap
                    $validuntil = $validuntil - (1 * HOURSECS);
                }else{
                    $validuntil = 0;
                }

                //cache the token
                $tokenobject = new \stdClass();
                $tokenobject->token = $token;
                $tokenobject->validuntil = $validuntil;
                $tokenobject->subs=false;
                $tokenobject->apps=false;
                $tokenobject->sites=false;
                if(property_exists($resp_object,'subs')){
                    $tokenobject->subs = $resp_object->subs;
                }
                if(property_exists($resp_object,'apps')){
                    $tokenobject->apps = $resp_object->apps;
                }
                if(property_exists($resp_object,'sites')){
                    $tokenobject->sites = $resp_object->sites;
                }
                if(property_exists($resp_object,'awsaccesssecret')){
                    $tokenobject->awsaccesssecret = $resp_object->awsaccesssecret;
                }
                if(property_exists($resp_object,'awsaccessid')){
                    $tokenobject->awsaccessid = $resp_object->awsaccessid;
                }

                $cache->set('recentpoodlltoken', $tokenobject);
                $cache->set('recentpoodlluser', $apiuser);

            }else{
                $token = '';
                if($resp_object && property_exists($resp_object,'error')) {
                    //ERROR = $resp_object->error
                }
            }
        }else{
            $token='';
        }
        return $token;
    }

    //check site URL is actually registered
    static function is_site_registered($sites, $wildcardok = true) {
        global $CFG;

        foreach($sites as $site) {

            //get arrays of the wwwroot and registered url
            //just in case, lowercase'ify them
            $thewwwroot = strtolower($CFG->wwwroot);
            $theregisteredurl = strtolower($site);
            $theregisteredurl = trim($theregisteredurl);

            //add http:// or https:// to URLs that do not have it
            if (strpos($theregisteredurl, 'https://') !== 0 &&
                    strpos($theregisteredurl, 'http://') !== 0) {
                $theregisteredurl = 'https://' . $theregisteredurl;
            }

            //if neither parsed successfully, that a no straight up
            $wwwroot_bits = parse_url($thewwwroot);
            $registered_bits = parse_url($theregisteredurl);
            if (!$wwwroot_bits || !$registered_bits) {
                //this is not a match
                continue;
            }

            //get the subdomain widlcard address, ie *.a.b.c.d.com
            $wildcard_subdomain_wwwroot = '';
            if (array_key_exists('host', $wwwroot_bits)) {
                $wildcardparts = explode('.', $wwwroot_bits['host']);
                $wildcardparts[0] = '*';
                $wildcard_subdomain_wwwroot = implode('.', $wildcardparts);
            } else {
                //this is not a match
                continue;
            }

            //match either the exact domain or the wildcard domain or fail
            if (array_key_exists('host', $registered_bits)) {
                //this will cover exact matches and path matches
                if ($registered_bits['host'] === $wwwroot_bits['host']) {
                    //this is a match
                    return true;
                    //this will cover subdomain matches
                } else if (($registered_bits['host'] === $wildcard_subdomain_wwwroot) && $wildcardok) {
                    //yay we are registered!!!!
                    return true;
                } else {
                    //not a match
                    continue;
                }
            } else {
                //not a match
                return false;
            }
        }
        return false;
    }

    //check token and tokenobject(from cache)
    //return error message or blank if its all ok
    public static function fetch_token_error($token){
        global $CFG;

        //check token authenticated
        if(empty($token)) {
            $message = get_string('novalidcredentials', constants::M_COMPONENT,
                    $CFG->wwwroot . constants::M_PLUGINSETTINGS);
            return $message;
        }

        // Fetch from cache and process the results and display.
        $cache = \cache::make_from_params(\cache_store::MODE_APPLICATION, constants::M_COMPONENT, 'token');
        $tokenobject = $cache->get('recentpoodlltoken');

        //we should not get here if there is no token, but lets gracefully die, [v unlikely]
        if (!($tokenobject)) {
            $message = get_string('notokenincache', constants::M_COMPONENT);
            return $message;
        }

        //We have an object but its no good, creds were wrong ..or something. [v unlikely]
        if (!property_exists($tokenobject, 'token') || empty($tokenobject->token)) {
            $message = get_string('credentialsinvalid', constants::M_COMPONENT);
            return $message;
        }
        // if we do not have subs.
        if (!property_exists($tokenobject, 'subs')) {
            $message = get_string('nosubscriptions', constants::M_COMPONENT);
            return $message;
        }
        // Is app authorised?
        if (!property_exists($tokenobject, 'apps') || !in_array(constants::M_COMPONENT, $tokenobject->apps)) {
            $message = get_string('appnotauthorised', constants::M_COMPONENT);
            return $message;
        }

        //just return empty if there is no error.
        return '';
    }

    public static function get_journeymode_options() {
        $options = array( constants::MODE_STEPSTHENFREE => get_string("mode_freeaftersteps", constants::M_COMPONENT),
            constants::MODE_STEPS => get_string("mode_steps", constants::M_COMPONENT),
            constants::MODE_FREE => get_string("mode_free", constants::M_COMPONENT));
        return $options;
    }

  public static function get_region_options(){
      return array(
        "useast1" => get_string("useast1",constants::M_COMPONENT),
          "tokyo" => get_string("tokyo",constants::M_COMPONENT),
          "sydney" => get_string("sydney",constants::M_COMPONENT),
          "dublin" => get_string("dublin",constants::M_COMPONENT),
          "ottawa" => get_string("ottawa",constants::M_COMPONENT),
          "frankfurt" => get_string("frankfurt",constants::M_COMPONENT),
          "london" => get_string("london",constants::M_COMPONENT),
          "saopaulo" => get_string("saopaulo",constants::M_COMPONENT),
          "singapore" => get_string("singapore",constants::M_COMPONENT),
          "mumbai" => get_string("mumbai",constants::M_COMPONENT),
          "capetown" => get_string("capetown",constants::M_COMPONENT),
          "bahrain" => get_string("bahrain",constants::M_COMPONENT)
      );
  }

    public static function translate_region($key){
        switch($key){
            case "useast1": return "us-east-1";
            case "tokyo": return "ap-northeast-1";
            case "sydney": return "ap-southeast-2";
            case "dublin": return "eu-west-1";
            case "ottawa": return "ca-central-1";
            case "frankfurt": return "eu-central-1";
            case "london": return "eu-west-2";
            case "saopaulo": return "sa-east-1";
            case "singapore": return "ap-southeast-1";
            case "mumbai": return "ap-south-1";
            case "capetown": return "af-south-1";
            case "bahrain": return "me-south-1";
        }
    }

    public static function get_timelimit_options(){
        return array(
            0 => get_string("notimelimit",constants::M_COMPONENT),
            15 => get_string("xsecs",constants::M_COMPONENT,'15'),
            30 => get_string("xsecs",constants::M_COMPONENT,'30'),
            45 => get_string("xsecs",constants::M_COMPONENT,'45'),
            60 => get_string("onemin",constants::M_COMPONENT),
            90 => get_string("oneminxsecs",constants::M_COMPONENT,'30'),
            120 => get_string("xmins",constants::M_COMPONENT,'2'),
            150 => get_string("xminsecs",constants::M_COMPONENT,array('minutes'=>2,'seconds'=>30)),
            180 => get_string("xmins",constants::M_COMPONENT,'3')
        );
    }

  public static function get_expiredays_options(){
      return array(
          "1"=>"1",
          "3"=>"3",
          "7"=>"7",
          "30"=>"30",
          "90"=>"90",
          "180"=>"180",
          "365"=>"365",
          "730"=>"730",
          "9999"=>get_string('forever',constants::M_COMPONENT)
      );
  }

    public static function fetch_options_transcribers() {
        $options = array(constants::TRANSCRIBER_AUTO => get_string("transcriber_auto", constants::M_COMPONENT),
                constants::TRANSCRIBER_POODLL => get_string("transcriber_poodll", constants::M_COMPONENT));
        return $options;
    }

    public static function fetch_options_reportstable() {
        $options = array(constants::M_USE_DATATABLES => get_string("reporttableajax", constants::M_COMPONENT),
            constants::M_USE_PAGEDTABLES => get_string("reporttablepaged", constants::M_COMPONENT));
        return $options;
    }

    public static function fetch_filemanager_opts($mediatype){
      global $CFG;
        $file_external = 1;
        $file_internal = 2;
        return array('subdirs' => 0, 'maxbytes' => $CFG->maxbytes, 'areamaxbytes' => 10485760, 'maxfiles' => 1,
                'accepted_types' => array($mediatype), 'return_types'=> $file_internal | $file_external);
    }

    //see if this is truly json or some error
    public static function is_json($string) {
        if (!$string) {
            return false;
        }
        if (empty($string)) {
            return false;
        }
        json_decode($string);
        return (json_last_error() == JSON_ERROR_NONE);
    }

    //fetch the MP3 URL of the text we want transcribed
    public static function fetch_polly_url($token,$region,$speaktext,$texttype, $voice) {
        global $USER;

        //If this is the "notts" voice, then we just return false
        if($voice=constants::M_NO_TTS){return false;}

        //The REST API we are calling
        $functionname = 'local_cpapi_fetch_polly_url';

        //log.debug(params);
        $params = array();
        $params['wstoken'] = $token;
        $params['wsfunction'] = $functionname;
        $params['moodlewsrestformat'] = 'json';
        $params['text'] = urlencode($speaktext);
        $params['texttype'] = $texttype;
        $params['voice'] = $voice;
        $params['appid'] = constants::M_COMPONENT;
        $params['owner'] = hash('md5',$USER->username);
        $params['region'] = $region;
        $params['engine'] = self::can_speak_neural($voice, $region)?'neural' : 'standard';
        $serverurl = self::CLOUDPOODLL . '/webservice/rest/server.php';
        $response = self::curl_fetch($serverurl, $params);
        if (!self::is_json($response)) {
            return false;
        }
        $payloadobject = json_decode($response);

        //returnCode > 0  indicates an error
        if ($payloadobject->returnCode > 0) {
            return false;
            //if all good, then lets do the embed
        } else if ($payloadobject->returnCode === 0) {
            $pollyurl = $payloadobject->returnMessage;
            return $pollyurl;
        } else {
            return false;
        }
    }

    public static function export_terms_to_csv($modid){
        global $DB;
        $terms = $DB->get_records(constants::M_TERMSTABLE,array('modid'=>$modid));

        //echo header row
        $name = 'wordcards_terms';
        $quote = '"';
        $delim = ",";//"\t";
        $newline = "\r\n";

        header("Content-Disposition: attachment; filename=$name.csv");
        header("Content-Type: text/comma-separated-values");

        //echo header
        $head = array('term','ar','es','fr','th','vi','ja','ko','pt','ru','zh','zh_tw','id');
        $heading = "";
        foreach ($head as $headfield) {
            $heading .= $quote . $headfield . $quote . $delim;
        }
        echo $heading . $newline;


        //echo data rows
        $quote = '"';
        $delim = ",";//"\t";
        $newline = "\r\n";
        $handle = fopen('php://output', 'w+');

        foreach($terms as $term){
            $translations = json_decode($term->translations);
            $rowarray=[];
            $rowarray[]=$term->term;
            $rowarray[]=isset($translations->ar)?$translations->ar:'';
            $rowarray[]=isset($translations->es)?$translations->es:'';
            $rowarray[]=isset($translations->fr)?$translations->fr:'';
            $rowarray[]=isset($translations->th)?$translations->th:'';
            $rowarray[]=isset($translations->vi)?$translations->vi:'';
            $rowarray[]=isset($translations->ja)?$translations->ja:'';
            $rowarray[]=isset($translations->ko)?$translations->ko:'';
            $rowarray[]=isset($translations->pt)?$translations->pt:'';
            $rowarray[]=isset($translations->rus)?$translations->rus:'';
            $rowarray[]=isset($translations->zh)?$translations->zh:'';
            $rowarray[]=isset($translations->zh_tw)?$translations->zh_tw:'';
            $rowarray[]=isset($translations->id)?$translations->id:'';
            fputcsv($handle, $rowarray,$delim,$quote);
        }
        fclose($handle);
        //After file is created, die
        die();
    }

    //fetch the dictionary entries from cloud poodll
    public static function fetch_youglish_token() {
        global $USER;

        //if we already have a token just use that
        $cache = \cache::make_from_params(\cache_store::MODE_APPLICATION, constants::M_COMPONENT, 'token');
        $youglishtoken = $cache->get('youglishtoken');
        if($youglishtoken){
            return $youglishtoken;
        }

        //If we dont have a youglish token we need a poodlltoken to make the API call to get one
        $poodlltoken=false;
        $conf= get_config(constants::M_COMPONENT);
        if (!empty($conf->apiuser) && !empty($conf->apisecret)) {
            $poodlltoken = self::fetch_token($conf->apiuser, $conf->apisecret);
        }
        if(!$poodlltoken || empty($poodlltoken)){
            return false;
        }


        //The REST API we are calling
        $functionname = 'local_cpapi_fetch_youglish_token';

        //log.debug(params);
        $params = array();
        $params['wstoken'] = $poodlltoken;
        $params['wsfunction'] = $functionname;
        $params['moodlewsrestformat'] = 'json';
        $params['appid'] = constants::M_COMPONENT;
        $serverurl = self::CLOUDPOODLL . '/webservice/rest/server.php';
        $response = self::curl_fetch($serverurl, $params);
        if (!self::is_json($response)) {
            return false;
        }
        $payloadobject = json_decode($response);

        //returnCode > 0  indicates an error
        if ($payloadobject->returnCode > 0) {
            return false;
            //if all good, then lets do the embed
        } else if ($payloadobject->returnCode === 0) {
            $youglishtoken = $payloadobject->returnMessage;
            $cache->set('youglishtoken', $youglishtoken);
            return $youglishtoken;
        } else {
            return false;
        }
    }

    //stage remote processing job ..just logging really
    public static function stage_remote_process_job($language,$cmid) {

        global $CFG, $USER;

        $token=false;
        $conf= get_config(constants::M_COMPONENT);
        if (!empty($conf->apiuser) && !empty($conf->apisecret)) {
            $token = self::fetch_token($conf->apiuser, $conf->apisecret);
        }
        if(!$token || empty($token)){
            return false;
        }

        $host = parse_url($CFG->wwwroot, PHP_URL_HOST);
        if (!$host) {
            $host = "unknown";
        }
        //owner
        $owner = hash('md5',$USER->username);
        $ownercomphash = hash('md5',$USER->username . constants::M_COMPONENT . $cmid . date("Y-m-d"));

        //The REST API we are calling
        $functionname = 'local_cpapi_stage_remoteprocess_job';

        //log.debug(params);
        $params = array();
        $params['wstoken'] = $token;
        $params['wsfunction'] = $functionname;
        $params['moodlewsrestformat'] = 'json';
        $params['appid'] = constants::M_COMPONENT;
        $params['region'] = $conf->awsregion;
        $params['host'] = $host;
        $params['s3outfilename'] = $ownercomphash; //we just want a unique value per session here
        $params['owner'] = $owner;
        $params['transcode'] =  '0';
        $params['transcoder'] = 'default';
        $params['transcribe'] =  '0';
        $params['subtitle'] = '0';
        $params['language'] = $language;
        $params['vocab'] = 'none';
        $params['s3path'] ='/';
        $params['mediatype'] = 'other';
        $params['notificationurl'] = 'none';
        $params['sourcemimetype'] = 'unknown';

        $serverurl = self::CLOUDPOODLL . '/webservice/rest/server.php';
        $response = self::curl_fetch($serverurl, $params);
        if (!self::is_json($response)) {
            return false;
        }
        $payloadobject = json_decode($response);

        //returnCode > 0  indicates an error
        if ($payloadobject->returnCode > 0) {
            return false;
            //if all good, then lets just return true
        } else if ($payloadobject->returnCode === 0) {
            return true;
        } else {
            return false;
        }
    }


    //fetch the dictionary entries from cloud poodll
    public static function fetch_dictionary_entries($terms,$sourcelang, $targetlangs) {
        global $USER;

        $token=false;
        $conf= get_config(constants::M_COMPONENT);
        if (!empty($conf->apiuser) && !empty($conf->apisecret)) {
            $token = self::fetch_token($conf->apiuser, $conf->apisecret);
        }
        if(!$token || empty($token)){
            return false;
        }

        //The REST API we are calling
        $functionname = 'local_cpapi_fetch_words';

        //log.debug(params);
        $params = array();
        $params['wstoken'] = $token;
        $params['wsfunction'] = $functionname;
        $params['moodlewsrestformat'] = 'json';
        $params['terms'] = $terms;
        $params['sourcelang'] =$sourcelang;
        $params['targetlangs'] =urlencode($targetlangs);;
        $params['appid'] = constants::M_COMPONENT;
        $serverurl = self::CLOUDPOODLL . '/webservice/rest/server.php';
        $response = self::curl_fetch($serverurl, $params);
        if (!self::is_json($response)) {
            return false;
        }
        $payloadobject = json_decode($response);

        //returnCode > 0  indicates an error
        if ($payloadobject->returnCode > 0) {
            return false;
            //if all good, then lets do the embed
        } else if ($payloadobject->returnCode === 0) {
            $pollyurl = $payloadobject->returnMessage;
            return $pollyurl;
        } else {
            return false;
        }
    }

  public static function fetch_auto_voice($langcode){
        $voices = self::get_tts_voices($langcode);
        $autoindex = array_rand($voices);
        return $autoindex;
  }

  public static function get_tts_voices($langcode, $showall=false){
      $alllang= array(
              constants::M_LANG_ARAE => ['Zeina'=>'Zeina'],
          //constants::M_LANG_ARSA => [],
                constants::M_LANG_DADK => ["Naja"=>"Naja","Mads"=>"Mads"],
              constants::M_LANG_DEDE => ['Hans'=>'Hans','Marlene'=>'Marlene', 'Vicki'=>'Vicki','Daniel'=>'Daniel'],
              constants::M_LANG_DEAT => ['Hannah'=>'Hannah'],
          //constants::M_LANG_DECH => [],
              constants::M_LANG_ENUS => ['Joey'=>'Joey','Justin'=>'Justin','Kevin'=>'Kevin','Matthew'=>'Matthew','Ivy'=>'Ivy',
                      'Joanna'=>'Joanna','Kendra'=>'Kendra','Kimberly'=>'Kimberly','Salli'=>'Salli',
                  'en-US-Whisper-alloy'=>'Ricky','en-US-Whisper-onyx'=>'Ed','en-US-Whisper-nova'=>'Tiffany','en-US-Whisper-shimmer'=>'Tammy'],
              constants::M_LANG_ENGB => ['Brian'=>'Brian','Amy'=>'Amy', 'Emma'=>'Emma'],
              constants::M_LANG_ENAU => ['Russell'=>'Russell','Nicole'=>'Nicole','Olivia'=>'Olivia'],
              constants::M_LANG_ENNZ => ['Aria'=>'Aria'],
              constants::M_LANG_ENZA => ['Ayanda'=>'Ayanda'],
              constants::M_LANG_ENIN => ['Aditi'=>'Aditi', 'Raveena'=>'Raveena', 'Kajal'=>'Kajal'],
          // constants::M_LANG_ENIE => [],
              constants::M_LANG_ENWL => ["Geraint"=>"Geraint"],
          // constants::M_LANG_ENAB => [],
              constants::M_LANG_ESUS => ['Miguel'=>'Miguel','Penelope'=>'Penelope','Lupe'=>'Lupe'],
              constants::M_LANG_ESES => [ 'Enrique'=>'Enrique', 'Conchita'=>'Conchita', 'Lucia'=>'Lucia'],
          //constants::M_LANG_FAIR => [],
              constants::M_LANG_FRCA => ['Chantal'=>'Chantal', 'Gabrielle'=>'Gabrielle','Liam'=>'Liam'],
              constants::M_LANG_FRFR => ['Mathieu'=>'Mathieu','Celine'=>'Celine', 'Lea'=>'Lea'],
              constants::M_LANG_HIIN => ["Aditi"=>"Aditi"],
          //constants::M_LANG_HEIL => [],
          //constants::M_LANG_IDID => [],
              constants::M_LANG_ITIT => ['Carla'=>'Carla',  'Bianca'=>'Bianca', 'Giorgio'=>'Giorgio'],
              constants::M_LANG_JAJP => ['Takumi'=>'Takumi','Mizuki'=>'Mizuki'],
              constants::M_LANG_KOKR => ['Seoyeon'=>'Seoyeon'],
          //constants::M_LANG_MSMY => [],
              constants::M_LANG_NLNL => ["Ruben"=>"Ruben","Lotte"=>"Lotte","Laura"=>"Laura"],
              constants::M_LANG_NLBE => ["nl-BE-Standard-B"=>"Marc_g","nl-BE-Wavenet-A"=>"Marie_g"],
              constants::M_LANG_PTBR => ['Ricardo'=>'Ricardo', 'Vitoria'=>'Vitoria','Camila'=>'Camila'],
              constants::M_LANG_PTPT => ["Ines"=>"Ines",'Cristiano'=>'Cristiano'],
              constants::M_LANG_RURU => ["Tatyana"=>"Tatyana","Maxim"=>"Maxim"],
          //constants::M_LANG_TAIN => [],
          //constants::M_LANG_TEIN => [],
              constants::M_LANG_TRTR => ['Filiz'=>'Filiz'],
              constants::M_LANG_ZHCN => ['Zhiyu'=>'Zhiyu'],

          constants::M_LANG_NONO => ['Liv'=>'Liv','nb-NO-Wavenet-B'=>'Lars_g','nb-NO-Wavenet-A'=>'Hedda_g','nb-NO-Wavenet-D'=>'Anders_g'],
          constants::M_LANG_PLPL => ['Ewa'=>'Ewa','Maja'=>'Maja','Jacek'=>'Jacek','Jan'=>'Jan'],
          constants::M_LANG_RORO => ['Carmen'=>'Carmen','ro-RO-Wavenet-A'=>'Sorina_g'],
          constants::M_LANG_SVSE => ['Astrid'=>'Astrid'],
          constants::M_LANG_UKUA => ['uk-UA-Wavenet-A'=>'Katya_g'],
          constants::M_LANG_FILPH => ['fil-PH-Wavenet-A'=>'Darna_g','fil-PH-Wavenet-B'=>'Reyna_g','fil-PH-Wavenet-C'=>'Bayani_g','fil-PH-Wavenet-D'=>'Ernesto_g'],
          constants::M_LANG_FIFI => ['Suvi'=>'Suvi','fi-FI-Wavenet-A'=>'Kaarina_g'],
          constants::M_LANG_HUHU => ['hu-HU-Wavenet-A'=>'Eszter_g'],

          constants::M_LANG_BGBG => array('bg-BG-Standard-A' => 'Mila_g'),//nikolai
          constants::M_LANG_CSCZ => array('cs-CZ-Wavenet-A' => 'Zuzana_g', 'cs-CZ-Standard-A' => 'Karolina_g'),
          constants::M_LANG_ELGR => array('el-GR-Wavenet-A' => 'Sophia_g', 'el-GR-Standard-A' => 'Isabella_g'),
          // constants::M_LANG_HRHR => array('A' => 'Ivana', 'B' => 'Marko'),
          constants::M_LANG_LVLV => array('lv-LV-Standard-A' => 'Janis_g'),
          constants::M_LANG_LTLT => array('lt-LT-Standard-A' => 'Matas_g'),
          constants::M_LANG_SKSK => array('sk-SK-Wavenet-A' => 'Laura_g', 'sk-SK-Standard-A' => 'Natalia_g'),
          //   constants::M_LANG_SLSI => array('A' => 'Pia', 'B' => 'Vid'),
          constants::M_LANG_ISIS => array('Dora' => 'Dora', 'Karl' => 'Karl'),
          //    constants::M_LANG_MKMK => array('A' => 'Marija', 'B' => 'Trajko'),
          constants::M_LANG_SRRS => array('sr-RS-Standard-A' => 'Milena_g'),
          constants::M_LANG_OTHER=>array(constants::M_NO_TTS=>get_string('notts', constants::M_COMPONENT)),

      );
      if(array_key_exists($langcode,$alllang)&& !$showall) {
          return $alllang[$langcode];
      }elseif($showall && array_key_exists($langcode,$alllang)) {
          $usearray = [];

          //add current language first
          foreach ($alllang[$langcode] as $v => $thevoice) {
              $neuraltag = in_array($v, constants::M_NEURALVOICES) ? ' (+)' : '';
              $usearray[$v] = get_string(strtolower($langcode), constants::M_COMPONENT) . ': ' . $thevoice . $neuraltag;
          }
          //then all the rest
          foreach ($alllang as $lang => $voices) {
              if ($lang == $langcode) {
                  continue;
              }
              foreach ($voices as $v => $thevoice) {
                  $neuraltag = in_array($v, constants::M_NEURALVOICES) ? ' (+)' : '';
                  $usearray[$v] = get_string(strtolower($lang), constants::M_COMPONENT) . ': ' . $thevoice . $neuraltag;
              }
          }
          return $usearray;
      }elseif(!array_key_exists($langcode,$alllang)){
          return [constants::M_NO_TTS=>get_string('notts', constants::M_COMPONENT) ];
      }else{
          //what could this be?
          return $alllang[constants::M_LANG_ENUS];
      }
    /*
      {"lang": "English(US)", "voices":  [{name: 'Joey', mf: 'm'},{name: 'Justin', mf: 'm'},{name: 'Matthew', mf: 'm'},{name: 'Ivy', mf: 'f'},{name: 'Joanna', mf: 'f'},{name: 'Kendra', mf: 'f'},{name: 'Kimberly', mf: 'f'},{name: 'Salli', mf: 'f'}]},
      {"lang": "English(GB)", "voices":  [{name: 'Brian', mf: 'm'},{name: 'Amy', mf: 'f'},{name: 'Emma', mf: 'f'}]},
      {"lang": "English(AU)", "voices": [{name: 'Russell', mf: 'm'},{name: 'Nicole', mf: 'f'}]},
      {"lang": "English(IN)", "voices":  [{name: 'Aditi', mf: 'm'},{name: 'Raveena', mf: 'f'}]},
      {"lang": "English(WELSH)", "voices":  [{name: 'Geraint', mf: 'm'}]},
      {"lang": "Danish", "voices":  [{name: 'Mads', mf: 'm'},{name: 'Naja', mf: 'f'}]},
      {"lang": "Dutch", "voices":  [{name: 'Ruben', mf: 'm'},{name: 'Lotte', mf: 'f'}]},
      {"lang": "French(FR)", "voices":  [{name: 'Mathieu', mf: 'm'},{name: 'Celine', mf: 'f'},{name: 'Léa', mf: 'f'}]},
      {"lang": "French(CA)", "voices":  [{name: 'Chantal', mf: 'm'}]},
      {"lang": "German", "voices":  [{name: 'Hans', mf: 'm'},{name: 'Marlene', mf: 'f'},{name: 'Vicki', mf: 'f'}]},
      {"lang": "Icelandic", "voices":  [{name: 'Karl', mf: 'm'},{name: 'Dora', mf: 'f'}]},
      {"lang": "Italian", "voices":  [{name: 'Carla', mf: 'f'},{name: 'Bianca', mf: 'f'},{name: 'Giorgio', mf: 'm'}]},
      {"lang": "Japanese", "voices":  [{name: 'Takumi', mf: 'm'},{name: 'Mizuki', mf: 'f'}]},
      {"lang": "Korean", "voices":  [{name: 'Seoyeon', mf: 'f'}]},
      {"lang": "Norwegian", "voices":  [{name: 'Liv', mf: 'f'}]},
      {"lang": "Polish", "voices":  [{name: 'Jacek', mf: 'm'},{name: 'Jan', mf: 'm'},{name: 'Maja', mf: 'f'},{name: 'Ewa', mf: 'f'}]},
      {"lang": "Portugese(BR)", "voices":  [{name: 'Ricardo', mf: 'm'},{name: 'Vitoria', mf: 'f'}]},
      {"lang": "Portugese(PT)", "voices":  [{name: 'Cristiano', mf: 'm'},{name: 'Ines', mf: 'f'}]},
      {"lang": "Romanian", "voices":  [{name: 'Carmen', mf: 'f'}]},
      {"lang": "Russian", "voices":  [{name: 'Maxim', mf: 'm'},{name: 'Tatyana', mf: 'f'}]},
      {"lang": "Spanish(ES)", "voices":  [{name: 'Enrique', mf: 'm'},{name: 'Conchita', mf: 'f'},{name: 'Lucia', mf: 'f'}]},
      {"lang": "Spanish(US)", "voices":  [{name: 'Miguel', mf: 'm'},{name: 'Penelope', mf: 'f'}]},
      {"lang": "Swedish", "voices":  [{name: 'Astrid', mf: 'f'}]},
      {"lang": "Turkish", "voices":  [{name: 'Filiz', mf: 'f'}]},
      {"lang": "Welsh", "voices":  [{name: 'Gwyneth', mf: 'f'}]},
    */

  }

  /* An activity typoe will be eith practice or review */
    public static function fetch_activity_tablabel($activitytype){
      switch($activitytype){
          case \mod_wordcards_module::PRACTICETYPE_MATCHSELECT:
          case \mod_wordcards_module::PRACTICETYPE_MATCHTYPE:
          case \mod_wordcards_module::PRACTICETYPE_DICTATION:
          case \mod_wordcards_module::PRACTICETYPE_SPEECHCARDS:
          case \mod_wordcards_module::PRACTICETYPE_LISTENCHOOSE:
          case \mod_wordcards_module::PRACTICETYPE_SPACEGAME:
              return get_string('practice',constants::M_COMPONENT) ;
          case \mod_wordcards_module::PRACTICETYPE_MATCHSELECT_REV:
          case \mod_wordcards_module::PRACTICETYPE_MATCHTYPE_REV:
          case \mod_wordcards_module::PRACTICETYPE_DICTATION_REV:
          case \mod_wordcards_module::PRACTICETYPE_SPEECHCARDS_REV:
          case \mod_wordcards_module::PRACTICETYPE_LISTENCHOOSE_REV:
          case \mod_wordcards_module::PRACTICETYPE_SPACEGAME_REV:
              return get_string('review',constants::M_COMPONENT);

      }
    }

    /* An activity typoe will be eith practice or review */
    public static function is_review_activity($activitytype){
        switch($activitytype){
            case \mod_wordcards_module::PRACTICETYPE_MATCHSELECT:
            case \mod_wordcards_module::PRACTICETYPE_MATCHTYPE:
            case \mod_wordcards_module::PRACTICETYPE_DICTATION:
            case \mod_wordcards_module::PRACTICETYPE_SPEECHCARDS:
                return false;
            case \mod_wordcards_module::PRACTICETYPE_MATCHSELECT_REV:
            case \mod_wordcards_module::PRACTICETYPE_MATCHTYPE_REV:
            case \mod_wordcards_module::PRACTICETYPE_DICTATION_REV:
            case \mod_wordcards_module::PRACTICETYPE_SPEECHCARDS_REV:
                return true;

        }
    }

    /* Each activity shows an icon on the tab tree */
    public static function fetch_activity_tabicon($activitytype){
        switch($activitytype){
            case \mod_wordcards_module::PRACTICETYPE_MATCHSELECT:
            case \mod_wordcards_module::PRACTICETYPE_MATCHSELECT_REV:
                return 'fa-bars';

            case \mod_wordcards_module::PRACTICETYPE_MATCHTYPE:
            case \mod_wordcards_module::PRACTICETYPE_MATCHTYPE_REV:
                return 'fa-keyboard-o';

            case \mod_wordcards_module::PRACTICETYPE_DICTATION:
            case \mod_wordcards_module::PRACTICETYPE_DICTATION_REV:
                return 'fa-headphones';

            case \mod_wordcards_module::PRACTICETYPE_LISTENCHOOSE:
            case \mod_wordcards_module::PRACTICETYPE_LISTENCHOOSE_REV:
                return 'fa-headphones';

            case \mod_wordcards_module::PRACTICETYPE_SPACEGAME:
            case \mod_wordcards_module::PRACTICETYPE_SPACEGAME_REV:
                return 'fa-rocket';

            case \mod_wordcards_module::PRACTICETYPE_SPEECHCARDS:
            case \mod_wordcards_module::PRACTICETYPE_SPEECHCARDS_REV:
                return 'fa-comment-o';

            default:
                return 'fa-dot-circle-o';
        }
    }

    public static function get_stars($grade){
        // Every item stars.
        if($grade==0){
            $ystar_cnt=0;
        }else if($grade<19) {
            $ystar_cnt=1;
        }else if($grade<39) {
            $ystar_cnt=2;
        }else if($grade<59) {
            $ystar_cnt=3;
        }else if($grade<79) {
            $ystar_cnt=4;
        }else{
            $ystar_cnt=5;
        }
        $yellowstars = array_fill(0, $ystar_cnt, true);
        $gstar_cnt= 5 - $ystar_cnt;
        $graystars = array_fill(0, $gstar_cnt, true);
        return[$yellowstars,$graystars];
    }

    public static function get_practicetype_label($practicetype){
        switch($practicetype) {
            case \mod_wordcards_module::PRACTICETYPE_NONE:
                return get_string('title_noactivity', constants::M_COMPONENT);
            case \mod_wordcards_module::PRACTICETYPE_MATCHSELECT:
                return get_string('title_matchselect', constants::M_COMPONENT);
            case \mod_wordcards_module::PRACTICETYPE_MATCHTYPE:
                return get_string('title_matchtype', constants::M_COMPONENT);
            case \mod_wordcards_module::PRACTICETYPE_DICTATION:
                return get_string('title_dictation', constants::M_COMPONENT);
            case \mod_wordcards_module::PRACTICETYPE_SPEECHCARDS:
                return get_string('title_speechcards', constants::M_COMPONENT);
            case \mod_wordcards_module::PRACTICETYPE_LISTENCHOOSE:
                return get_string('title_listenchoose', constants::M_COMPONENT);
            case \mod_wordcards_module::PRACTICETYPE_SPACEGAME:
                return get_string('title_spacegame', constants::M_COMPONENT);
            case \mod_wordcards_module::PRACTICETYPE_MATCHSELECT_REV:
                return get_string('title_matchselect_rev', constants::M_COMPONENT);
            case \mod_wordcards_module::PRACTICETYPE_MATCHTYPE_REV:
                return get_string('title_matchtype_rev', constants::M_COMPONENT);
            case \mod_wordcards_module::PRACTICETYPE_DICTATION_REV:
                return get_string('title_dictation_rev', constants::M_COMPONENT);
            case \mod_wordcards_module::PRACTICETYPE_SPEECHCARDS_REV:
                return get_string('title_speechcards_rev', constants::M_COMPONENT);
            case \mod_wordcards_module::PRACTICETYPE_LISTENCHOOSE_REV:
                return get_string('title_listenchoose_rev', constants::M_COMPONENT);
            case \mod_wordcards_module::PRACTICETYPE_SPACEGAME_REV:
                return get_string('title_spacegame_rev', constants::M_COMPONENT);
        }
    }

  public static function get_practicetype_options($wordpool=false){
      $none =  array(\mod_wordcards_module::PRACTICETYPE_NONE => get_string('title_noactivity', constants::M_COMPONENT));
      $learnoptions = [
              \mod_wordcards_module::PRACTICETYPE_MATCHSELECT => get_string('title_matchselect', constants::M_COMPONENT),
              \mod_wordcards_module::PRACTICETYPE_MATCHTYPE => get_string('title_matchtype', constants::M_COMPONENT),
              \mod_wordcards_module::PRACTICETYPE_DICTATION => get_string('title_dictation', constants::M_COMPONENT),
              \mod_wordcards_module::PRACTICETYPE_SPEECHCARDS => get_string('title_speechcards', constants::M_COMPONENT),
              \mod_wordcards_module::PRACTICETYPE_LISTENCHOOSE => get_string('title_listenchoose', constants::M_COMPONENT),
                \mod_wordcards_module::PRACTICETYPE_SPACEGAME => get_string('title_spacegame', constants::M_COMPONENT)
      ];

        $reviewoptions = [
            \mod_wordcards_module::PRACTICETYPE_MATCHSELECT_REV => get_string('title_matchselect_rev', constants::M_COMPONENT),
            \mod_wordcards_module::PRACTICETYPE_MATCHTYPE_REV => get_string('title_matchtype_rev', constants::M_COMPONENT),
            \mod_wordcards_module::PRACTICETYPE_DICTATION_REV => get_string('title_dictation_rev', constants::M_COMPONENT),
            \mod_wordcards_module::PRACTICETYPE_SPEECHCARDS_REV => get_string('title_speechcards_rev', constants::M_COMPONENT),
            \mod_wordcards_module::PRACTICETYPE_LISTENCHOOSE_REV => get_string('title_listenchoose_rev', constants::M_COMPONENT),
              \mod_wordcards_module::PRACTICETYPE_SPACEGAME_REV => get_string('title_spacegame_rev', constants::M_COMPONENT)
            ];

      if($wordpool===\mod_wordcards_module::WORDPOOL_LEARN){
          $options=$learnoptions;
      }else{
          //We need to merge arrays this way, not with array_merge, in order to preserve keys
          $options = $none + $learnoptions + $reviewoptions;
      }
      return $options;
  }

    public static function fetch_options_listenchoose(){
        return array(
            constants::M_LC_TERMTERM=> get_string('lc_termterm', constants::M_COMPONENT),
            constants::M_LC_TERMDEF => get_string('lc_termdef', constants::M_COMPONENT));
    }
  public static function fetch_options_fontfaceflip(){
      return array(
              constants::M_FRONTFACEFLIP_TERM=> get_string('term', constants::M_COMPONENT),
              constants::M_FRONTFACEFLIP_DEF => get_string('definition', constants::M_COMPONENT));
  }

    public static function fetch_options_animations(){
        return array(
            constants::M_ANIM_FANCY=> get_string('anim_fancy', constants::M_COMPONENT),
            constants::M_ANIM_PLAIN => get_string('anim_plain', constants::M_COMPONENT));
    }

   public static function get_lang_options() {
       return array(
               constants::M_LANG_ARAE => get_string('ar-ae', constants::M_COMPONENT),
               constants::M_LANG_ARSA => get_string('ar-sa', constants::M_COMPONENT),
               constants::M_LANG_DEDE => get_string('de-de', constants::M_COMPONENT),
               constants::M_LANG_DECH => get_string('de-ch', constants::M_COMPONENT),
               constants::M_LANG_DEAT => get_string('de-at', constants::M_COMPONENT),
               constants::M_LANG_ENUS => get_string('en-us', constants::M_COMPONENT),
               constants::M_LANG_ENGB => get_string('en-gb', constants::M_COMPONENT),
               constants::M_LANG_ENAU => get_string('en-au', constants::M_COMPONENT),
               constants::M_LANG_ENIN => get_string('en-in', constants::M_COMPONENT),
               constants::M_LANG_ENIE => get_string('en-ie', constants::M_COMPONENT),
               constants::M_LANG_ENWL => get_string('en-wl', constants::M_COMPONENT),
               constants::M_LANG_ENAB => get_string('en-ab', constants::M_COMPONENT),
               constants::M_LANG_ESUS => get_string('es-us', constants::M_COMPONENT),
               constants::M_LANG_ESES => get_string('es-es', constants::M_COMPONENT),
               constants::M_LANG_FAIR => get_string('fa-ir', constants::M_COMPONENT),
               constants::M_LANG_FILPH => get_string('fil-ph', constants::M_COMPONENT),
               constants::M_LANG_FRCA => get_string('fr-ca', constants::M_COMPONENT),
               constants::M_LANG_FRFR => get_string('fr-fr', constants::M_COMPONENT),
               constants::M_LANG_HIIN => get_string('hi-in', constants::M_COMPONENT),
               constants::M_LANG_HEIL => get_string('he-il', constants::M_COMPONENT),
               constants::M_LANG_IDID => get_string('id-id', constants::M_COMPONENT),
               constants::M_LANG_ITIT => get_string('it-it', constants::M_COMPONENT),
               constants::M_LANG_JAJP => get_string('ja-jp', constants::M_COMPONENT),
               constants::M_LANG_KOKR => get_string('ko-kr', constants::M_COMPONENT),
               constants::M_LANG_MINZ => get_string('mi-nz', constants::M_COMPONENT),
               constants::M_LANG_MSMY => get_string('ms-my', constants::M_COMPONENT),
               constants::M_LANG_NLNL => get_string('nl-nl', constants::M_COMPONENT),
               constants::M_LANG_NLBE => get_string('nl-be', constants::M_COMPONENT),
               constants::M_LANG_PTBR => get_string('pt-br', constants::M_COMPONENT),
               constants::M_LANG_PTPT => get_string('pt-pt', constants::M_COMPONENT),
               constants::M_LANG_RURU => get_string('ru-ru', constants::M_COMPONENT),
               constants::M_LANG_TAIN => get_string('ta-in', constants::M_COMPONENT),
               constants::M_LANG_TEIN => get_string('te-in', constants::M_COMPONENT),
               constants::M_LANG_TRTR => get_string('tr-tr', constants::M_COMPONENT),
               constants::M_LANG_ZHCN => get_string('zh-cn', constants::M_COMPONENT),
               constants::M_LANG_NONO => get_string('no-no', constants::M_COMPONENT),
               //constants::M_LANG_NBNO => get_string('nb-no', constants::M_COMPONENT),
               constants::M_LANG_PLPL => get_string('pl-pl', constants::M_COMPONENT),
               constants::M_LANG_RORO => get_string('ro-ro', constants::M_COMPONENT),
               constants::M_LANG_SVSE => get_string('sv-se', constants::M_COMPONENT),
               constants::M_LANG_UKUA => get_string('uk-ua',constants::M_COMPONENT),
               constants::M_LANG_EUES => get_string('eu-es',constants::M_COMPONENT),
               constants::M_LANG_FIFI => get_string('fi-fi',constants::M_COMPONENT),
               constants::M_LANG_HUHU => get_string('hu-hu',constants::M_COMPONENT),

               constants::M_LANG_BGBG => get_string('bg-bg', constants::M_COMPONENT),
               constants::M_LANG_CSCZ => get_string('cs-cz', constants::M_COMPONENT),
               constants::M_LANG_ELGR => get_string('el-gr', constants::M_COMPONENT),
               constants::M_LANG_HRHR => get_string('hr-hr', constants::M_COMPONENT),
               constants::M_LANG_LTLT => get_string('lt-lt', constants::M_COMPONENT),
               constants::M_LANG_LVLV => get_string('lv-lv', constants::M_COMPONENT),
               constants::M_LANG_SKSK => get_string('sk-sk', constants::M_COMPONENT),
               constants::M_LANG_SLSI => get_string('sl-si', constants::M_COMPONENT),
               constants::M_LANG_ISIS => get_string('is-is', constants::M_COMPONENT),
               constants::M_LANG_MKMK => get_string('mk-mk', constants::M_COMPONENT),
               constants::M_LANG_SRRS => get_string('sr-rs', constants::M_COMPONENT),
               constants::M_LANG_OTHER => get_string('xx-xx', constants::M_COMPONENT),
       );
   }

    public static function fetch_short_lang($longlang){
        if(\core_text::strlen($longlang)<=2){return $longlang;}
        if($longlang=="fil-PH"){return "fil";}
        $shortlang = substr($longlang,0,2);
        return $shortlang;
    }

    /*
     * Do we need to build a language model for this passage?
     *
     */
    public static function needs_lang_model($mod) {
        $region = get_config(constants::M_COMPONENT,'awsregion');
        switch($region){
            case 'tokyo':
            case 'useast1':
            case 'dublin':
            case 'sydney':
            default:
                $shortlang = self::fetch_short_lang($mod->get_mod()->ttslanguage);
            return ($shortlang=='en' ||
                    $shortlang=='de' ||
                    $shortlang=='fr' ||
                    $shortlang=='ru' ||
                    $shortlang=='eu' ||
                    $shortlang=='pl' ||
                    $shortlang=='fi' ||
                    $shortlang=='it' ||
                    $shortlang=='pt' ||
                    $shortlang=='uk' ||
                    $shortlang=='ro' ||
                    $shortlang=='hu' ||
                            $shortlang=='es') && $mod->get_terms();
        }
    }

    /*
     * Hash the passage and compare
     *
     */
    public static function fetch_passagehash($mod) {
        $cleantext = self::fetch_activity_text($mod);
        if(!empty($cleantext)) {
            return sha1($cleantext);
        }else{
            return false;
        }
    }


    /*
     * Build a language model for this passage
     *
     */
    public static function fetch_lang_model($mod){
        $conf= get_config(constants::M_COMPONENT);
        if (!empty($conf->apiuser) && !empty($conf->apisecret)) {;
            $token = self::fetch_token($conf->apiuser, $conf->apisecret);

            if(empty($token)){
                return false;
            }
            $url = constants::CLOUDPOODLL . "/webservice/rest/server.php";
            $params["wstoken"]=$token;
            $params["wsfunction"]='local_cpapi_generate_lang_model';
            $params["moodlewsrestformat"]='json';
            $params["passage"]= self::fetch_activity_text($mod);
            $params["language"]=$mod->get_mod()->ttslanguage;
            $params["region"]=$conf->awsregion;

            $resp = self::curl_fetch($url,$params);
            $respObj = json_decode($resp);
            $ret = new \stdClass();
            if(isset($respObj->returnCode)){
                $ret->success = $respObj->returnCode =='0' ? true : false;
                $ret->payload = $respObj->returnMessage;
            }else{
                $ret->success=false;
                $ret->payload = "unknown problem occurred";
            }
            return $ret;
        }else{
            return false;
        }
    }

    /*
    * Return all the cleaned and connected text for the activity
    * Borrowed from read aloud
    *
    */
    public static function fetch_activity_text($mod) {

        $terms = $mod->get_terms();
        if(!$terms){return "";}
        $thetext = "";
        foreach ($terms as $term){
            $thetext .= $term->term . " ";
            if(!empty($term->model_sentence)){
                $thetext .= $term->model_sentence . " ";
            }
        }

        //f we think its unicodemb4, first test and then get on with it
        $unicodemb4=self::isUnicodemb4($thetext);

        //lowercaseify
        $thetext = strtolower($thetext);

        //remove any html
        $thetext = strip_tags($thetext);

        //replace all line ends with spaces
        if($unicodemb4) {
            $thetext = preg_replace('/#\R+#/u', ' ', $thetext);
            $thetext = preg_replace('/\r/u', ' ', $thetext);
            $thetext = preg_replace('/\n/u', ' ', $thetext);
        }else{
            $thetext = preg_replace('/#\R+#/', ' ', $thetext);
            $thetext = preg_replace('/\r/', ' ', $thetext);
            $thetext = preg_replace('/\n/', ' ', $thetext);
        }

        //remove punctuation. This is where we needed the unicode flag
        //see https://stackoverflow.com/questions/5233734/how-to-strip-punctuation-in-php
        // $thetext = preg_replace("#[[:punct:]]#", "", $thetext);
        //https://stackoverflow.com/questions/5689918/php-strip-punctuation
        if($unicodemb4) {
            $thetext = preg_replace("/[[:punct:]]+/u", "", $thetext);
        }else{
            $thetext = preg_replace("/[[:punct:]]+/", "", $thetext);
        }

        //remove bad chars
        $b_open = "“";
        $b_close = "”";
        $b_sopen = '‘';
        $b_sclose = '’';
        $bads = array($b_open, $b_close, $b_sopen, $b_sclose);
        foreach ($bads as $bad) {
            $thetext = str_replace($bad, '', $thetext);
        }

        //remove double spaces
        //split on spaces into words
        $textbits = explode(' ', $thetext);
        //remove any empty elements
        $textbits = array_filter($textbits, function($value) {
            return $value !== '';
        });
        $thetext = implode(' ', $textbits);
        return $thetext;
    }

    /*
    * Regexp replace with /u will return empty text if not unicodemb4
    * some DB collations and char sets may do that to us. So we test for that here
    */
    public static function isUnicodemb4($thetext) {
        //$testtext = "test text: " . "\xf8\xa1\xa1\xa1\xa1"; //this will fail for sure

        $thetext = strtolower($thetext);
        $thetext = strip_tags($thetext);
        $testtext = "test text: " . $thetext;
        $test1 = preg_replace('/#\R+#/u', ' ', $testtext);
        if(empty($test1)){return false;}
        $test2 = preg_replace('/\r/u', ' ', $testtext);
        if(empty($test2)){return false;}
        $test3 = preg_replace('/\n/u', ' ', $testtext);
        if(empty($test3)){return false;}
        $test4 = preg_replace("/[[:punct:]]+/u", "", $testtext);
        if(empty($test4)){
            return false;
        }else{
            return true;
        }
    }

    public static function add_mform_elements($mform, $context,$setuptab=false) {
        global $CFG;
        $config = get_config(constants::M_COMPONENT);

        //if this is setup tab we need to add a field to tell it the id of the activity
        if($setuptab) {
            $mform->addElement('hidden', 'n');
            $mform->setType('n', PARAM_INT);
        }

        //-------------------------------------------------------------------------------
        // Adding the "general" fieldset, where all the common settings are showed
        $mform->addElement('header', 'general', get_string('general', 'form'));

        // Adding the standard "name" field
        $mform->addElement('text', 'name', get_string('modulename', constants::M_COMPONENT), array('size'=>'64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEAN);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        $mform->addRule('name', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        $mform->addHelpButton('name', 'modulename', constants::M_COMPONENT);

        // Adding the standard "intro" and "introformat" fields
        //we do not support this in tabs
        if(!$setuptab) {
            $label = get_string('moduleintro');
            $mform->addElement('editor', 'introeditor', $label, array('rows' => 10), array('maxfiles' => EDITOR_UNLIMITED_FILES,
                    'noclean' => true, 'context' => $context, 'subdirs' => true));
            $mform->setType('introeditor', PARAM_RAW); // no XSS prevention here, users must be trusted
            $mform->addElement('advcheckbox', 'showdescription', get_string('showdescription'));
            $mform->addHelpButton('showdescription', 'showdescription');
        }

        $options = utils::get_journeymode_options();
        $mform->addElement('select', 'journeymode', get_string('journeymode', constants::M_COMPONENT),
            $options);
        $mform->setDefault('journeymode',$config->journeymode);

        $options = utils::get_lang_options();
        $mform->addElement('select', 'ttslanguage', get_string('ttslanguage', constants::M_COMPONENT),
                $options);
        $mform->setDefault('ttslanguage',$config->ttslanguage);

        $deflangs =  utils::get_rcdic_langs();// utils::get_lexicala_langs();
        $options=[];
        foreach($deflangs as $deflang){
            $options[$deflang['code']]=$deflang['name'];
        }

        $mform->addElement('select', 'deflanguage', get_string('deflanguage', constants::M_COMPONENT),
            $options);
        $mform->setDefault('deflanguage',$config->deflanguage);
        $mform->addHelpButton('deflanguage', 'deflanguage', constants::M_COMPONENT);

        $videooptions =  [0=>get_string('no'),1=>get_string('yes')];
        $mform->addElement('select', 'videoexamples', get_string('videoexamples', constants::M_COMPONENT),
            $videooptions, $config->videoexamples);
        $mform->addHelpButton('videoexamples', 'videoexamples', constants::M_COMPONENT);

        $mform->addElement('text', 'learnpoint', get_string('learnpoint', constants::M_COMPONENT), array('size'=>'4'));
        $mform->setDefault('learnpoint',$config->learnpoint);
        $mform->setType('learnpoint', PARAM_INT);
        $mform->addHelpButton('learnpoint', 'learnpoint', constants::M_COMPONENT);

        $mform->addElement('header', 'hdrappearance', get_string('appearance'));
        $mform->setExpanded('hdrappearance');

        //options for practicetype and term count
        $ptype_options_learn = utils::get_practicetype_options(\mod_wordcards_module::WORDPOOL_LEARN);
        $ptype_options_all = utils::get_practicetype_options();
        $termcount_options = [4 => 4, 5 => 5, 6 => 6, 7 => 7,8 => 8,9 => 9,10 => 10,11 => 11,12 => 12,13 => 13,14 => 14,15 => 15];

        $mform->addElement('select', 'step1practicetype', get_string('step1practicetype', constants::M_COMPONENT),
                $ptype_options_learn, \mod_wordcards_module::PRACTICETYPE_MATCHSELECT);
        $mform->addElement('select', 'step1termcount', get_string('step1termcount', constants::M_COMPONENT), $termcount_options, 4);

        $mform->addElement('select', 'step2practicetype', get_string('step2practicetype', constants::M_COMPONENT),
                $ptype_options_all,\mod_wordcards_module::PRACTICETYPE_MATCHSELECT_REV);
        $mform->addElement('select', 'step2termcount', get_string('step2termcount', constants::M_COMPONENT), $termcount_options, 4);
        $mform->disabledIf('step2termcount', 'step2practicetype', 'eq',\mod_wordcards_module::PRACTICETYPE_NONE);

        $mform->addElement('select', 'step3practicetype', get_string('step3practicetype', constants::M_COMPONENT),
                $ptype_options_all,\mod_wordcards_module::PRACTICETYPE_MATCHSELECT_REV);
        $mform->addElement('select', 'step3termcount', get_string('step3termcount', constants::M_COMPONENT), $termcount_options, 4);
        $mform->disabledIf('step3termcount', 'step3practicetype', 'eq',\mod_wordcards_module::PRACTICETYPE_NONE);

        $mform->addElement('select', 'step4practicetype', get_string('step4practicetype', constants::M_COMPONENT),
                $ptype_options_all,\mod_wordcards_module::PRACTICETYPE_MATCHSELECT_REV);
        $mform->addElement('select', 'step4termcount', get_string('step4termcount', constants::M_COMPONENT), $termcount_options, 4);
        $mform->disabledIf('step4termcount', 'step4practicetype', 'eq',\mod_wordcards_module::PRACTICETYPE_NONE);

        $mform->addElement('select', 'step5practicetype', get_string('step5practicetype', constants::M_COMPONENT),
                $ptype_options_all,\mod_wordcards_module::PRACTICETYPE_MATCHSELECT_REV);
        $mform->addElement('select', 'step5termcount', get_string('step5termcount', constants::M_COMPONENT), $termcount_options, 4);
        $mform->disabledIf('step5termcount', 'step5practicetype', 'eq',\mod_wordcards_module::PRACTICETYPE_NONE);

        //Attempts
        $attemptoptions = array(0 => get_string('unlimited', constants::M_COMPONENT),
                1 => '1', 2 => '2', 3 => '3', 4 => '4', 5 => '5',);
        $mform->addElement('select', 'maxattempts', get_string('maxattempts', constants::M_COMPONENT), $attemptoptions);

        $t_options = utils::fetch_options_transcribers();
        $mform->addElement('select', 'transcriber', get_string('transcriber', constants::M_COMPONENT),
                $t_options,$config->transcriber);

        $mform->addElement('hidden', 'skipreview',0);
        $mform->setType('skipreview',PARAM_INT);

        $mform->addElement('hidden', 'finishedstepmsg','');
        $mform->addElement('hidden', 'completedstepmsg','');
        $mform->setType('finishedstepmsg',PARAM_TEXT);
        $mform->setType('completedstepmsg',PARAM_TEXT);

        //Show images on task flip screen
        $mform->addElement('selectyesno', 'showimageflip', get_string('showimagesonflipscreen', constants::M_COMPONENT));
        $mform->setDefault('showimageflip', $config->showimageflip);

        $frontfaceoptions = self::fetch_options_fontfaceflip();
        $mform->addElement('select', 'frontfaceflip', get_string('frontfaceflip', constants::M_COMPONENT),
                $frontfaceoptions, $config->frontfaceflip);

        $lcoptions = self::fetch_options_listenchoose();
        $mform->addElement('select', 'lcoptions', get_string('lcoptions', constants::M_COMPONENT),
            $lcoptions, $config->lcoptions);


  // show activity open closes
   $dateoptions = array('optional' => true);
        $name = 'activityopenscloses';
        $label = get_string($name, 'wordcards');
        $mform->addElement('header', $name, $label);
        $mform->setExpanded($name, false);
        //-----------------------------------------------------------------------------

        $name = 'viewstart';
        $label = get_string($name, "wordcards");
        $mform->addElement('date_time_selector', $name, $label, $dateoptions);
        $mform->addHelpButton($name, $name, constants::M_COMPONENT);
        

        $name = 'viewend';
        $label = get_string($name, "wordcards");
        $mform->addElement('date_time_selector', $name, $label, $dateoptions);
        $mform->addHelpButton($name, $name, constants::M_COMPONENT);
    } //end of add_mform_elements


    //What multi-attempt grading approach
    public static function get_grade_options() {
        return array(
            constants::M_GRADELATEST => get_string("gradelatest", constants::M_COMPONENT),
            constants::M_GRADEHIGHEST => get_string("gradehighest", constants::M_COMPONENT)
        );
    }

    public static function save_newterm($modid,$term, $definition,$translations,$sourcedef,$modelsentence){

        global $DB;
        $mod = \mod_wordcards_module::get_by_modid($modid);

        $insertdata = new \stdClass();
        $insertdata->modid = $modid;
        $insertdata->term = trim($term);
        $insertdata->definition = trim($definition);
        $insertdata->translations = trim($translations);
        $insertdata->sourcedef = trim($sourcedef);
        $insertdata->model_sentence = trim($modelsentence);
        $insertdata->ttsvoice = utils::fetch_auto_voice($mod->get_mod()->ttslanguage);
        $ret = $DB->insert_record(constants::M_TERMSTABLE, $insertdata);
        if($ret && !empty($insertdata->model_sentence)){
            $DB->update_record('wordcards', array('id' => $modid, 'hashisold' => 1));
        }
        return $ret;
    }

    public static function get_rcdic_langs($selected='en', $other=true)
    {

        $langdefs = [];
        $langdefs[] = ['code' => 'ar', 'name' => self::get_lang_name('ar')];
        //  $langdefs[] = ['code'=>'de','name'=>self::get_lang_name('de')];
        $langdefs[] = ['code' => 'en', 'name' => self::get_lang_name('en')];
        $langdefs[] = ['code' => 'es', 'name' => self::get_lang_name('es')];
        $langdefs[] = ['code' => 'fr', 'name' => self::get_lang_name('fr')];
        $langdefs[] = ['code' => 'id', 'name' => self::get_lang_name('id')];
        $langdefs[] = ['code' => 'ja', 'name' => self::get_lang_name('ja')];
        $langdefs[] = ['code' => 'ko', 'name' => self::get_lang_name('ko')];
        $langdefs[] = ['code' => 'pt', 'name' => self::get_lang_name('pt')];
        $langdefs[] = ['code' => 'rus', 'name' => self::get_lang_name('ru')];
        $langdefs[] = ['code' => 'th', 'name' => self::get_lang_name('th')];
        $langdefs[] = ['code' => 'vi', 'name' => self::get_lang_name('vi')];
        $langdefs[] = ['code' => 'zh', 'name' => self::get_lang_name('zh')];
        $langdefs[] = ['code' => 'zh_tw', 'name' => self::get_lang_name('zh_tw')];

        //add ms dictionary languages
        $langdefs = self::get_msdic_langs($langdefs);

        if ($other) {
            $langdefs[] = ['code' => constants::M_DEFLANG_OTHER, 'name' => self::get_lang_name(constants::M_DEFLANG_OTHER)];
        }
        $default_set = false;
        for($i=0;$i<count($langdefs);$i++){
            if($langdefs[$i]['code']==$selected){
                $langdefs[$i]['selected']=true;
                $default_set = true;
                break;
            }
        }
        if(!$default_set){$langdefs[1]['selected']=true;}
        return $langdefs;
    }

    //in most cases the rcdic lang code is two letters, but for russian it is rus and for Chinese there is a zh_tw
    //but we dont have an aws zh_tw so we use zh
    public static function fetch_rcdic_lang($ttslang)
    {
       $langcode = utils::fetch_short_lang($ttslang);
       if($langcode=='ru'){
           $langcode = 'rus';
       }
       return $langcode;
    }

    public static function get_msdic_langs($langdefs){
       // $langdefs = [];
        $langdefs[] = ['code' => 'af', 'name' =>'Afrikaans'];
        $langdefs[] = ['code' => 'bn', 'name' =>'Bangla'];
        $langdefs[] = ['code' => 'bs', 'name' =>'Bosnian (Latin)'];
        $langdefs[] = ['code' => 'bg', 'name' =>'Bulgarian'];
        $langdefs[] = ['code' => 'ca', 'name' =>'Catalan'];
        $langdefs[] = ['code' => 'hr', 'name' =>'Croatian'];
        $langdefs[] = ['code' => 'cs', 'name' =>'Czech'];
        $langdefs[] = ['code' => 'da', 'name' =>'Danish'];
        $langdefs[] = ['code' => 'nl', 'name' =>'Dutch'];
        $langdefs[] = ['code' => 'fi', 'name' =>'Finnish'];
        $langdefs[] = ['code' => 'de', 'name' =>'German'];
        $langdefs[] = ['code' => 'el', 'name' =>'Greek'];
        $langdefs[] = ['code' => 'ht', 'name' =>'Haitian Creole'];
        $langdefs[] = ['code' => 'he', 'name' =>'Hebrew'];
        $langdefs[] = ['code' => 'hi', 'name' =>'Hindi'];
        $langdefs[] = ['code' => 'hu', 'name' =>'Hungarian'];
        $langdefs[] = ['code' => 'is', 'name' =>'Icelandic'];
        $langdefs[] = ['code' => 'it', 'name' =>'Italian'];
        $langdefs[] = ['code' => 'lv', 'name' =>'Latvian'];
        $langdefs[] = ['code' => 'lt', 'name' =>'Lithuanian'];
        $langdefs[] = ['code' => 'ms', 'name' =>'Malay (Latin)'];
        $langdefs[] = ['code' => 'mt', 'name' =>'Maltese'];
        $langdefs[] = ['code' => 'nb', 'name' =>'Norwegian'];
        $langdefs[] = ['code' => 'fa', 'name' =>'Persian'];
        $langdefs[] = ['code' => 'pl', 'name' =>'Polish'];
        $langdefs[] = ['code' => 'ro', 'name' =>'Romanian'];
        $langdefs[] = ['code' => 'sr', 'name' =>'Serbian (Latin)'];
        $langdefs[] = ['code' => 'sk', 'name' =>'Slovak'];
        $langdefs[] = ['code' => 'sl', 'name' =>'Slovenian'];
        $langdefs[] = ['code' => 'sw', 'name' =>'Swahili (Latin)'];
        $langdefs[] = ['code' => 'sv', 'name' =>'Swedish'];
        $langdefs[] = ['code' => 'ta', 'name' =>'Tamil'];
        $langdefs[] = ['code' => 'tr', 'name' =>'Turkish'];
        $langdefs[] = ['code' => 'uk', 'name' =>'Ukrainian'];
        $langdefs[] = ['code' => 'ur', 'name' =>'Urdu'];
        $langdefs[] = ['code' => 'cy', 'name' =>'Welsh'];
        return $langdefs;
    }

    public static function get_youglish_config($ttslang){

            $langs= array(
                constants::M_LANG_ARAE =>  ['lang'=>'Arabic','accent'=>'eg'],
                constants::M_LANG_ARSA =>  ['lang'=>'Arabic','accent'=>'sa'],
                constants::M_LANG_DEDE => ['lang'=>'German','accent'=>false],
                constants::M_LANG_DECH => ['lang'=>'German','accent'=>false],
                constants::M_LANG_DEAT => ['lang'=>'German','accent'=>false],
                constants::M_LANG_ENUS =>  ['lang'=>'English','accent'=>'us'],
                constants::M_LANG_ENGB => ['lang'=>'English','accent'=>'uk'],
                constants::M_LANG_ENAU => ['lang'=>'English','accent'=>'aus'],
                constants::M_LANG_ENIN =>  ['lang'=>'English','accent'=>'uk'],
                constants::M_LANG_ENIE => ['lang'=>'English','accent'=>'ie'],
                constants::M_LANG_ENWL =>  ['lang'=>'English','accent'=>'uk'],
                constants::M_LANG_ENAB => ['lang'=>'English','accent'=>'sco'],
                constants::M_LANG_ESUS => ['lang'=>'Spanish','accent'=>'la'],
                constants::M_LANG_ESES => ['lang'=>'Spanish','accent'=>'es'],
                constants::M_LANG_FAIR => ['lang'=>false,'accent'=>false],
                constants::M_LANG_FILPH => ['lang'=>false,'accent'=>false],
                constants::M_LANG_FRCA =>  ['lang'=>'French','accent'=>'qc'],
                constants::M_LANG_FRFR => ['lang'=>'French','accent'=>'fr'],
                constants::M_LANG_HIIN => ['lang'=>false,'accent'=>false],
                constants::M_LANG_HEIL => ['lang'=>'Hebrew','accent'=>false],
                constants::M_LANG_IDID => ['lang'=>false,'accent'=>false],
                constants::M_LANG_ITIT => ['lang'=>'Italian','accent'=>false],
                constants::M_LANG_JAJP => ['lang'=>'Japanese','accent'=>false],
                constants::M_LANG_KOKR => ['lang'=>'Korean','accent'=>false],
                constants::M_LANG_MSMY => ['lang'=>false,'accent'=>false],
                constants::M_LANG_NLNL => ['lang'=>'Dutch','accent'=>'nl'],
                constants::M_LANG_NLBE => ['lang'=>'Dutch','accent'=>'be'],
                constants::M_LANG_PTBR => ['lang'=>'Portuguese','accent'=>'br'],
                constants::M_LANG_PTPT => ['lang'=>'Portuguese','accent'=>'pt'],
                constants::M_LANG_RURU => ['lang'=>'Russian','accent'=>false],
                constants::M_LANG_TAIN => ['lang'=>false,'accent'=>false],
                constants::M_LANG_TEIN => ['lang'=>false,'accent'=>false],
                constants::M_LANG_TRTR => ['lang'=>'Turkish','accent'=>false],
                constants::M_LANG_ZHCN =>  ['lang'=>'Chinese','accent'=>'cn'],
                constants::M_LANG_NONO => ['lang'=>false,'accent'=>false],
                //constants::M_LANG_NBNO => ['lang'=>false,'accent'=>false],
                constants::M_LANG_PLPL => ['lang'=>'Polish','accent'=>false],
                constants::M_LANG_RORO => ['lang'=>false,'accent'=>false],
                constants::M_LANG_SVSE => ['lang'=>'Swedish','accent'=>false],
                constants::M_LANG_UKUA => ['lang'=>'Ukrainian','accent'=>false],
                constants::M_LANG_EUES => ['lang'=>false,'accent'=>false],
                constants::M_LANG_FIFI => ['lang'=>false,'accent'=>false],
                constants::M_LANG_HUHU => ['lang'=>false,'accent'=>false],
            );

            if(array_key_exists($ttslang,$langs)) {
                $youglish= $langs[$ttslang];
                $youglish['token'] = self::fetch_youglish_token();
            }else{
                $youglish= ['lang'=>false,'accent'=>false,'token'=>false];
            }
            return $youglish;
    }

    public static function get_lexicala_langs($selected='en'){
        $langdefs=[];
        $langdefs[] = ['code'=>'af','name'=>'Afrikaans'];
        $langdefs[] = ['code'=>'ar','name'=>'Arabic'];
        $langdefs[] = ['code'=>'az','name'=>'Azerbaijani'];
        $langdefs[] = ['code'=>'bg','name'=>'Bulgarian'];
        $langdefs[] = ['code'=>'br','name'=>'Breton'];
        $langdefs[] = ['code'=>'ca','name'=>'Catalan'];
        $langdefs[] = ['code'=>'cs','name'=>'Czech'];
        $langdefs[] = ['code'=>'de','name'=>'German'];
        $langdefs[] = ['code'=>'dk','name'=>'Danish'];
        $langdefs[] = ['code'=>'el','name'=>'Greek'];
        $langdefs[] = ['code'=>'en','name'=>'English'];
        $langdefs[] = ['code'=>'es','name'=>'Spanish'];
        $langdefs[] = ['code'=>'et','name'=>'Estonian'];
        $langdefs[] = ['code'=>'fa','name'=>'Persian'];
        $langdefs[] = ['code'=>'fi','name'=>'Finnish'];
        $langdefs[] = ['code'=>'fr','name'=>'French'];
        $langdefs[] = ['code'=>'fy','name'=>'Western Frisian'];
        $langdefs[] = ['code'=>'he','name'=>'Hebrew'];
        $langdefs[] = ['code'=>'hi','name'=>'Hindi'];
        $langdefs[] = ['code'=>'hr','name'=>'Croatian'];
        $langdefs[] = ['code'=>'hu','name'=>'Hungarian'];
        $langdefs[] = ['code'=>'is','name'=>'Icelandic'];
        $langdefs[] = ['code'=>'it','name'=>'Italian'];
        $langdefs[] = ['code'=>'ja','name'=>'Japanese'];
        $langdefs[] = ['code'=>'ko','name'=>'Korean'];
        $langdefs[] = ['code'=>'lt','name'=>'Lithuanian'];
        $langdefs[] = ['code'=>'lv','name'=>'Latvian'];
        $langdefs[] = ['code'=>'ml','name'=>'Burmese'];
        $langdefs[] = ['code'=>'nl','name'=>'Dutch'];
        $langdefs[] = ['code'=>'no','name'=>'Norwegian'];
        $langdefs[] = ['code'=>'pl','name'=>'Polish'];
        $langdefs[] = ['code'=>'prs','name'=>'English'];
        $langdefs[] = ['code'=>'ps','name'=>'Pushto'];
        $langdefs[] = ['code'=>'pt','name'=>'Portuguese'];
        $langdefs[] = ['code'=>'ro','name'=>'Romanian'];
        $langdefs[] = ['code'=>'ru','name'=>'Russian'];
        $langdefs[] = ['code'=>'sk','name'=>'Slovak'];
        $langdefs[] = ['code'=>'sl','name'=>'Slovenian'];
        $langdefs[] = ['code'=>'sr','name'=>'Serbian'];
        $langdefs[] = ['code'=>'sv','name'=>'Swedish'];
        $langdefs[] = ['code'=>'th','name'=>'Thai'];
        $langdefs[] = ['code'=>'tr','name'=>'Turkish'];
        $langdefs[] = ['code'=>'tw','name'=>'Twi'];
        $langdefs[] = ['code'=>'uk','name'=>'Ukranian'];
        $langdefs[] = ['code'=>'ur','name'=>'Urdu'];
        $langdefs[] = ['code'=>'vi','name'=>'Vietnamese'];
        $langdefs[] = ['code'=>'zh','name'=>'Chinese'];
        $default_set = false;
        for($i=0;$i<count($langdefs);$i++){
            if($langdefs[$i]['code']==$selected){
                $langdefs[$i]['selected']=true;
                $default_set = true;
                break;
            }
        }
        if(!$default_set){$langdefs[10]['selected']=true;}
        return $langdefs;
    }

    public static function get_lang_name($fulllangcode){
        if(mb_strlen($fulllangcode)>2){
            $shortlangcode = mb_substr($fulllangcode,0,2);
        }else{
            $shortlangcode = $fulllangcode;
        }

        switch($shortlangcode){
            case 'ar': return 'Arabic';
            case 'en': return 'English';
            case 'es': return 'Spanish';
            case 'fr': return 'French';
            case 'id': return 'Bahasa Indonesia';
            case 'ja': return 'Japanese';
            case 'ko': return 'Korean';
            case 'pt': return 'Portuguese';
            case 'rus': return 'Russian';
            case 'ru': return 'Russian';
            case 'th': return 'Thai';
            case 'tr': return 'Turkish';
            case 'vi': return 'Vietnamese';
            case 'zh': return  $fulllangcode=="zh_tw" ? 'Chinese (trad.)' : 'Chinese (simpl.)';
            case constants::M_DEFLANG_OTHER:
                return get_string('deflang_other',constants::M_COMPONENT);
        }
    }

    public static function update_deflanguage($mod){
        global $DB;
        $terms = $DB->get_records(constants::M_TERMSTABLE,array('modid'=>$mod->get_mod()->id));
        if(!$terms){return;}
        //if the definitions language is other, we can not translate it
        if($mod->get_mod()->deflanguage==constants::M_DEFLANG_OTHER){return;}
        foreach($terms as $term){
            if(empty($term->translations)){continue;}
            if(!self::is_json($term->translations)){continue;}
            $translations = json_decode($term->translations);
            //english is a special case, lets support it
            if($mod->get_mod()->deflanguage=='en') {
                $translations->en=$term->sourcedef;
            }
            if(!empty($translations) &&
                isset($translations->{$mod->get_mod()->deflanguage})){
                if(isset($translations->{$mod->get_mod()->deflanguage}->text)){
                    //lexicala
                    $newdef = $translations->{$mod->get_mod()->deflanguage}->text;
                    if(is_array($newdef)){
                        //something is wrong here, we cant really trust the data
                        continue;
                    }
                }else{
                    //r and c db
                    $newdef = $translations->{$mod->get_mod()->deflanguage};
                }
                $DB->update_record(constants::M_TERMSTABLE, ['id'=>$term->id,'definition'=>$newdef]);
            }
        }
    }

    //can speak neural?
    public static function can_speak_neural($voice,$region){

        //check if the region is supported
        switch($region){
            case "useast1":
            case "tokyo":
            case "sydney":
            case "dublin":
            case "ottawa":
            case "frankfurt":
            case "london":
            case "singapore":
                //ok
                break;
            default:
                return false;
        }

        //check if the voice is supported
        if(in_array($voice,constants::M_NEURALVOICES)){
            return true;
        }else{
            return false;
        }
    }

    //Prepare the data for import
    //a row will look like this: term|definition|voice|modelsentence
    public static function prepare_import_data_row($rowdata, $delimiter,$mod){
        $trimchars = " \t\n\r\0\x0B";
        //we limit at 4 so any commas in the model answer will not be split on
        $cols = explode($delimiter,$rowdata,4);
        if(count($cols)>=2 && !empty($cols[0]) && !empty($cols[1])){
            $insertdata = new \stdClass();
            $insertdata->modid = $mod->get_mod()->id;
            $insertdata->term = trim($cols[0], $trimchars);
            $insertdata->definition = trim($cols[1], $trimchars);
            //voices
            $voices = utils::get_tts_voices($mod->get_mod()->ttslanguage);
            $insertdata->ttsvoice='';
            if(!empty($cols[2])){
                $thevoice = trim($cols[2],$trimchars);
                if(in_array($thevoice,$voices) && $thevoice!='auto') {
                    $voice = array_search($thevoice, $voices);
                    $insertdata->ttsvoice = $voice;
                }
            }
            if(empty($insertdata->ttsvoice)) {
                $insertdata->ttsvoice = utils::fetch_auto_voice($mod->get_mod()->ttslanguage);
            }

            //model sentence
            if(!empty($cols[3])) {
                $insertdata->model_sentence = trim($cols[3], $trimchars);
            }
            return $insertdata;
        }else{
            return false;
        }//end of if cols ok
    }

    public static function fetch_glossaries_list($courseid){
        global $DB;
        $glossaries = $DB->get_records(constants::M_GLOSSARYTABLE,array('course'=>$courseid));
        $glossarylist = [];
        foreach($glossaries as $glossary){
            $glossarylist[$glossary->id] = $glossary->name;
        }
        return $glossarylist;
    }

}
