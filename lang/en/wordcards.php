<?php
/**
 * Displays information about the wordcards in the course.
 *
 * @package mod_wordcards
 * @author  Frédéric Massart - FMCorz.net
 */

defined('MOODLE_INTERNAL') || die();

$string['activitycompleted'] = 'Activity completed';
$string['completedmsg'] = 'Completed message';
$string['completedmsg_help'] = 'This is the message displayed on the final screen of the activity when the student complete the last practice.';
$string['completionwhenfinish'] = 'The student has finished the activity.';
$string['congrats'] = 'Congratulations!';
$string['congratsitsover'] = '<div  style="text-align: center;">You have completed this activity. Feel free to go back and practice more!</div>';
$string['definition'] = 'Definition';
$string['definitions'] = 'Definitions';
$string['deleteallentries'] = 'Delete all user attempts and stats (keep the terms/definitions)';
$string['deleteterm'] = 'Delete term \'{$a}\'';
$string['delimiter'] = 'Delimiter Character';
$string['delim_tab'] = 'Tab';
$string['delim_comma'] = 'Comma';
$string['delim_pipe'] = 'Pipe';
$string['description'] = 'Description';
$string['editterm'] = 'Edit term \'{$a}\'';
$string['finishscatterin'] = '<h4 style="text-align: center;">Congratulations!</h4>';
$string['wordcards:addinstance'] = 'Add an instance';
$string['wordcards:view'] = 'View the module';
$string['reviewactivity'] = 'Review';
$string['reviewactivityfinished'] = 'You finished the review session in {$a->seconds} seconds.';

$string['gotit'] = 'Got it';
$string['import'] = 'Import';
$string['importdata'] = 'Import Data';
$string['importresults'] = 'Successfully imported {$a->imported} rows. {$a->failed} rows failed.';
$string['introduction'] = 'Introduction';
$string['learnactivityfinished'] = 'You finished the practice session in {$a->seconds} seconds.';
$string['finishedstepmsg'] = 'Finished message';
$string['finishedstepmsg_help'] = 'This is the message displayed when you end a practice session.';
$string['step1termcount'] = 'Step 1 word set size';
$string['step2termcount'] = 'Step 2 word set size';
$string['step3termcount'] = 'Step 3 word set size';
$string['step4termcount'] = 'Step 4 word set size';
$string['step5termcount'] = 'Step 5 word set size';
$string['loading'] = 'Loading';
$string['learnactivity'] = 'New Words';
$string['markasseen'] = 'Mark as seen';
$string['modulename'] = 'Wordcards';
$string['modulename_help'] = 'The wordcards activity module enables a teacher to create custom wordcards games for encouraging students learning new words.';
$string['modulenameplural'] = 'Wordcards';
$string['mustseealltocontinue'] = 'All the words must be marked as seen to continue.';
$string['name'] = 'Name';
$string['nodefinitions'] = 'No words were added yet.';
$string['noteaboutseenforteachers'] = 'Note: Teachers\' seen status are not saved.';
$string['pluginadministration'] = 'Wordcards administration';
$string['pluginname'] = 'Wordcards';
$string['reallydeleteterm'] = 'Are you sure you want to delete the term \'{$a}\'?';
$string['removeuserdata'] = 'Remove Wordcards user data';
$string['setup'] = 'Words Admin';
$string['skipreview'] = 'Hide first review session';
$string['skipreview_help'] = 'Hide the review session of this specific activity if no wordcards activities have been completed in this course.';
$string['tabdefinitions'] = 'Definitions';
$string['tabsetup'] = 'Words Admin';
$string['tabimport'] = 'Import';
$string['term'] = 'Term';
$string['termadded'] = 'The term \'{$a}\' has been added.';
$string['termdeleted'] = 'The term has been deleted.';
$string['termnotseen'] = 'Term not seen';
$string['termsaved'] = 'The term \'{$a}\' has been saved.';
$string['termseen'] = 'Term seen';

$string['step1practicetype'] = 'Step 1 activity';
$string['step2practicetype'] = 'Step 2 activity';
$string['step3practicetype'] = 'Step 3 activity';
$string['step4practicetype'] = 'Step 4 activity';
$string['step5practicetype'] = 'Step 5 activity';
$string['matchselect'] = 'Choose match';
$string['matchtype'] = 'Type match';
$string['dictation'] = 'Dictation';
$string['scatter'] = 'Scatter';
$string['speechcards'] = 'Speech Cards';


$string['apiuser']='Poodll API User ';
$string['apiuser_details']='The Poodll account username that authorises Poodll on this site.';
$string['apisecret']='Poodll API Secret ';
$string['apisecret_details']='The Poodll API secret. See <a href= "https://support.poodll.com/support/solutions/articles/19000083076-cloud-poodll-api-secret">here</a> for more details';
$string['useast1'] = 'US East';
$string['tokyo'] = 'Tokyo, Japan';
$string['sydney'] = 'Sydney, Australia';
$string['dublin'] = 'Dublin, Ireland';
$string['ottawa'] = 'Ottawa, Canada';
$string['frankfurt'] = 'Frankfurt, Germany';
$string['london'] = 'London, U.K';
$string['saopaulo'] = 'Sao Paulo, Brazil';
$string['mumbai'] = 'Mumbai, India';
$string['singapore'] = 'Singapore';
$string['forever']='Never expire';

$string['en-us'] = 'English (US)';
$string['en-gb'] = 'English (GB)';
$string['en-au'] = 'English (AU)';
$string['en-in'] = 'English (IN)';
$string['es-es'] = 'Spanish (ES)';
$string['es-us'] = 'Spanish (US)';
$string['fr-fr'] = 'French (FR.)';
$string['fr-ca'] = 'French (CA)';
$string['ko-kr'] = 'Korean(KR)';
$string['pt-br'] = 'Portuguese(BR)';
$string['it-it'] = 'Italian(IT)';
$string['de-de'] = 'German(DE)';
$string['hi-in'] = 'Hindi(IN)';
$string['ko-kr'] = 'Korean';
$string['ar-ae'] = 'Arabic (Gulf)';
$string['ar-sa'] = 'Arabic (Modern Standard)';
$string['zh-cn'] = 'Chinese (Mandarin-Mainland)';
$string['nl-nl'] = 'Dutch';
$string['en-ie'] = 'English (Ireland)';
$string['en-wl'] = 'English (Wales)';
$string['en-ab'] = 'English (Scotland)';
$string['fa-ir'] = 'Farsi';
$string['de-ch'] = 'German (Swiss)';
$string['he-il'] = 'Hebrew';
$string['id-id'] = 'Indonesian';
$string['ja-jp'] = 'Japanese';
$string['ms-my'] = 'Malay';
$string['pt-pt'] = 'Portuguese (PT)';
$string['ru-ru'] = 'Russian';
$string['ta-in'] = 'Tamil';
$string['te-in'] = 'Telegu';
$string['tr-tr'] = 'Turkish';

$string['awsregion']='AWS Region';
$string['region']='AWS Region';
$string['expiredays']='Days to keep file';
$string['displaysubs'] = '{$a->subscriptionname} : expires {$a->expiredate}';
$string['noapiuser'] = "No API user entered. Word Cards will not work correctly.";
$string['noapisecret'] = "No API secret entered. Word Cards will not work correctly.";
$string['credentialsinvalid'] = "The API user and secret entered could not be used to get access. Please check them.";
$string['appauthorised']= "Poodll Word Cards is authorised for this site.";
$string['appnotauthorised']= "Poodll Word Cards is NOT authorised for this site.";
$string['refreshtoken']= "Refresh license information";
$string['notokenincache']= "Refresh to see license information. Contact Poodll support if there is a problem.";
//these errors are displayed on activity page
$string['nocredentials'] = 'API user and secret not entered. Please enter them on <a href="{$a}">the settings page.</a> You can get them from <a href="https://poodll.com/member">Poodll.com.</a>';
$string['novalidcredentials'] = 'API user and secret were rejected and could not gain access. Please check them on <a href="{$a}">the settings page.</a> You can get them from <a href="https://poodll.com/member">Poodll.com.</a>';
$string['nosubscriptions'] = "There is no current subscription for this site/plugin.";

$string['transcriber'] = 'Transcriber';
$string['transcriber_details'] = 'The transcription engine to use';
$string['transcriber_none'] = 'No transcription';
$string['transcriber_amazontranscribe'] = 'Regular Transcription';
$string['transcriber_googlecloud'] = 'Fast Transcription (< 60s only)';
$string['enabletts_details'] = 'TTS is currently not implemented';
$string['ttslanguage'] = 'Target Language';
$string['ttsvoice'] = 'TTS Voice';
$string['alternates'] = 'Acceptable mistranscribes';

$string['audiofile'] = 'Audio file';
$string['imagefile'] = 'Image file';
$string['starttest'] = 'Begin';
$string['quit'] = 'Quit';
$string['next'] = 'Next';
$string['previous'] = 'Prev';
$string['ok'] = 'OK';
$string['done'] = 'Done';
$string['listen'] = 'Listen';
$string['delete'] = 'Delete';
$string['submit'] = 'Submit';
$string['flip'] = 'Flip';
$string['word'] = 'Word';
$string['meaning'] = 'Meaning';
$string['correct'] = 'Correct';
$string['backtostart'] = 'Back to Start';
$string['loading'] = 'Loading';
$string['title_matchselect'] = 'Choose the Answer';
$string['title_matchtype'] = 'Type the Answer';
$string['title_dictation'] = 'Listen and Type';
$string['title_scatter'] = 'Match the Words';
$string['title_speechcards'] = 'Say the Words';

$string['review'] = 'Review';
$string['practice'] = 'Practice';

$string['title_noactivity'] = 'None';
$string['title_matchselect_rev'] = 'Choose the Answer (Review)';
$string['title_matchtype_rev'] = 'Type the Answer (Review)';
$string['title_dictation_rev'] = 'Listen and Type (Review)';
$string['title_scatter_rev'] = 'Match the Words (Review)';
$string['title_speechcards_rev'] = 'Say the Words (Review)';

$string['title_vocablist'] = 'Get Ready';
$string['instructions_matchselect'] = 'Choose the word from the list below which best matches the highlighted word.';
$string['instructions_matchtype'] = 'Type the word that you learnt, which best matches the highlighted word.';
$string['instructions_dictation'] = 'Listen and type the word(s) that you hear. Tap the blue button to hear the word(s).';
$string['instructions_scatter'] = 'Match the Cards with the same meaning, by tapping them,';
$string['instructions_speechcards'] = 'Tap the blue button and speak the word(s) that you see on the card. Speak slowly and clearly.';
$string['instructions_vocablist'] = 'Check the words that you will be tested on in this activity. Tap the word card or press the \'meaning\' or \'word\' buttons to flipe the word card. When you are ready press \'Begin\' and test how well you remember.';
$string['pushtospeak'] = 'Push to Speak';