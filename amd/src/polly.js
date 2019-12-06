/**
 * Polly Module.
 *
 * @package mod_wordcards
 * @author  Justin Hunt - Poodll.com
 */

define(['jquery','core/log'], function($,log){
    var voiceurl = M.cfg.wwwroot + '/filter/poodll/poodllfilelib.php?datatype=speaktext&paramone=';
    var voices ={};
    return {
        init: function(theplayer, ttslanguage){
            this.theplayer = theplayer;
            this.ttslanguage=ttslanguage;
            this.voices = this.init_voices(ttslanguage);
        },
        play_text: function(text,voice){
            if(!voice){voice = 'Kendra';}
            if(voice=='Auto'){
                //fetching a random voice from the list of voices per TTS language
                voice = this.voices[Math.floor(Math.random()*this.voices.length)];
            }
            var pollyformat = 'text';
            var datastring= pollyformat + '|' + voice + '|' + text;
            var theurl = voiceurl+encodeURIComponent(datastring);
            this.theplayer.attr('src',theurl);
            this.theplayer[0].play();
        },
        play_ssml: function(ssml,voice){
            if(!voice){voice = 'Kendra';}
            if(voice=='Auto'){
                //fetching a random voice from the list of voices per TTS language
                voice = this.voices[Math.floor(Math.random()*this.voices.length)];
            }
            var pollyformat = 'ssml';
            var datastring= pollyformat + '|' + voice + '|' + ssml;
            var theurl = voiceurl+encodeURIComponent(datastring);
            this.theplayer.attr('src',theurl);
            this.theplayer[0].play();
        },
        play_audio: function(audiourl){
            this.theplayer.attr('src',audiourl);
            this.theplayer[0].play();
        },
        init_voices: function(ttslanguage){
            switch(ttslanguage) {
                case 'en-GB' : return  ['Brian', 'Amy', 'Emma'];
                case 'en-AU' : return ['Russell', 'Nicole'];
                case 'en-IN' : return ['Aditi', 'Raveena'];
                case 'es-US' : return ['Miguel', 'Penelope'];
                case 'es-ES' : return ['Enrique', 'Conchita', 'Lucia'];
                case 'fr-CA' : return ['Chantal'];
                case 'fr-FR' : return ['Mathieu', 'Celine', 'Léa'];
                case 'de-DE' : return ['Hans', 'Marlene', 'Vicki'];
                case 'it-IT' : return ['Carla', 'Bianca', 'Giorgio'];
                case 'pt-BR' : return ['Ricardo', 'Vitoria'];
                case 'en-US' :
                default:
                    return ['Joey', 'Justin', 'Matthew', 'Ivy', 'Joanna', 'Kendra', 'Kimberly', 'Salli'];
            }
        }
  }//return object
});