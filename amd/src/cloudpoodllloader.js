/**
 * CloudPoodll Loader module.
 *
 * @package mod_wordcards
 * @author  Justin Hunt - Poodll.com
 */

define(['jquery','core/log','https://cdn.jsdelivr.net/gh/justinhunt/cloudpoodll@latest/amd/build/cloudpoodll.min.js'], function($,log,CloudPoodll){
//define(['jquery','core/log','http://localhost/moodle/local/cpapi/cloudpoodll/amd/src/cloudpoodll.js'], function($,log,CloudPoodll){
    return {
        init: function(recorderid, thecallback){
            CloudPoodll.createRecorder(recorderid);
            CloudPoodll.theCallback = thecallback;
            CloudPoodll.initEvents();

        }
}
});