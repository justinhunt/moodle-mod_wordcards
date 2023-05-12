/**
 * dictionary lookup
 *
 * @package mod_wordcards
 * @author  Justin Hunt - poodll.com
 * *
 */

define(['jquery','core/log','core/ajax','core/templates'], function($,log,ajax,templates) {

    "use strict"; // jshint ;_;

    log.debug('Wordcards dictionary lookup: initialising');

    return {
        init: function (cmid,modid,resultscont) {
            log.debug('Wordcards dictionary lookup: initialising');
            this.cmid = cmid;
            this.modid = modid;
            this.resultscont = resultscont;
        },

        update_page: function(alldata){
            //update the page
            var that = this;
            that.resultscont.empty();
            for(var i = 0; i < alldata.length; i++)
            {
                var tdata = alldata[i];
                templates.render('mod_wordcards/word_wizard_oneresult', tdata).then(
                    function (html, js) {
                        that.resultscont.append(html);
                        templates.runTemplateJS(js);
                    }
                );
            }
        },

        getwords: function (allwords,sourcelang,definitionslang) {
            var that = this;
            var langs = ['ar', 'id', 'zh', 'zh_tw', 'ja', 'ko', 'pt', 'es', 'th', 'vi', 'fr', 'rus'];

            //if we have no words, do nothing
            if (allwords.trim() === '') {
                return false;
            }

            var p = ajax.call([
                {
                    methodname: 'mod_wordcards_search_dictionary',
                    args: {terms: allwords, cmid: that.cmid, sourcelang: sourcelang, targetlangs: definitionslang},
                    async: false
                },
            ])[0];

            p.then(async function(response){
                var allterms_result = [];
                //if return code=0, disaster, log and die
                if (response.success === 0) {
                    log.debug(response.payload);
                    return allterms_result;
                }


                    var terms = JSON.parse(response.payload);
                    for (var i = 0; i < terms.length; i++) {
                        var theterm = terms[i];
                        //if a word search failed
                        if (theterm.count === 0) {
                            var senses=[];
                            senses.push({definition: '',sourcedefinition: 'No def. available',
                                modelsentence: '', senseindex: 0, translations: '{}'})
                            var tdata = {term: theterm.term, senses: senses, modid: that.modid };
                            allterms_result.push(tdata);

                        } else {
                            var tdata = {term: theterm.term, senses: [], modid: that.modid};
                            for (var sindex in theterm.results) {
                                var sense = theterm.results[sindex];
                                //by default its term:English def:English
                                var sourcedefinition = sense.definition;
                                var alltrans = {};
                                for (var ti = 0; ti < langs.length; ti++) {
                                    alltrans[langs[ti]] = sense['lang_' + langs[ti]];
                                }
                                var translations = JSON.stringify(alltrans);
                                var definition = sourcedefinition;
                                //if its NOT term:english and def:english, we pull the definition from the translation
                                if (definitionslang !== "en") {
                                    if (sense.hasOwnProperty('lang_' + definitionslang)) {
                                        definition = sense['lang_' + definitionslang];
                                    } else if (definitionslang === 'en') {
                                        definition = sense.meaning;
                                    } else {
                                        definition = 'No translation available';
                                    }
                                }

                                //model sentence is only in english (for now)
                                var modelsentence = sense.example;


                                tdata.senses.push({
                                    definition: definition, sourcedefinition: sourcedefinition,
                                    modelsentence: modelsentence, senseindex: sindex, translations: translations
                                });
                            }//end of results loop
                            allterms_result.push(tdata);
                        }
                    }

                    that.update_page(allterms_result );
            });//end of promise then
        },


        /*
            var langs = {
                "af": "Afrikaans",
                "ar": "Arabic",
                "bn": "Bangla",
                "bs": "Bosnian",
                "bg": "Bulgarian",
                "ca": "Catalan",
                "cs": "Czech",
                "cy": "Welsh",
                "da": "Danish",
                "de": "German",
                "el": "Greek",
                "en": "English",
                "es": "Spanish",
                "et": "Estonian",
                "fa": "Persian",
                "fi": "Finnish",
                "fr": "French",
                "ht": "Haitian Creole",
                "he": "Hebrew",
                "hi": "Hindi",
                'hr': 'Croatian',
                'hu': 'Hungarian',
                'id': 'Indonesian',
                'is': 'Icelandic',
                'it': 'Italian',
                'ja': 'Japanese',
                'ko': 'Korean',
                'lt': 'Lithuanian',
                'lv': 'Latvian',
                'mww': 'Hmong Daw',
                'ms': 'Malay',
                'mt': 'Maltese',
                'nl': 'Dutch',
                'nb': 'Norwegian',
                'pl': 'Polish',
                'pt': 'Portuguese',
                'ro': 'Romanian',
                'ru': 'Russian',
                'sr-Latn': 'Serbian (Latin)',
                'sk': 'Slovak',
                'sl': 'Slovenian',
                'sv': 'Swedish',
                'ta': 'Tamil',
                'th': 'Thai',
                'tr': 'Turkish',
                'uk': 'Ukrainian',
                'ur': 'Urdu',
                'vi': 'Vietnamese',
                'zh-Hans': 'Chinese Simplified'
            }
*/

    }

});

