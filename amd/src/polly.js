/**
 * Polly Module.
 *
 * @package mod_wordcards
 * @author  Justin Hunt - Poodll.com
 */

define(['jquery','core/log'], function($,log){
    var voiceurl = M.cfg.wwwroot + '/filter/poodll/poodllfilelib.php?datatype=speaktext&paramone=';
    var theplayer = null;
    return {
        init: function(theplayer){
            this.theplayer = theplayer;
        },
        play_text: function(text){
            var pollyformat = 'text';
            var voice = 'Kendra';
            var datastring= pollyformat + '|' + voice + '|' + text;
            var theurl = voiceurl+encodeURIComponent(datastring);
            this.theplayer.attr('src',theurl);
            this.theplayer[0].play();
        },
        play_ssml: function(ssml){
            var pollyformat = 'ssml';
            var voice = 'Kendra';
            var datastring= pollyformat + '|' + voice + '|' + ssml;
            var theurl = voiceurl+encodeURIComponent(datastring);
            this.theplayer.attr('src',theurl);
            this.theplayer[0].play();
        }
}
});