/**
 * dictionary lookup
 *
 * @package mod_wordcards
 * @author  Justin Hunt - poodll.com
 * *
 */

define(['jquery','core/log'], function($,log) {

    "use strict"; // jshint ;_;

    log.debug('Wordcards dictionary lookup: initialising');

    return {
        init: function (cmid) {
            log.debug('Wordcards dictionary lookup: initialising');
            this.cmid = cmid;
            this.modid = modid;
        },

        english_lone: async function (allwords, langdef) {
            var that = this;
            var langs = ['ar', 'id', 'zh', 'zh_tw', 'ja', 'ko', 'pt', 'es', 'th', 'vi', 'fr', 'rus'];

            //if we have no words, do nothing
            if (allwords.trim() === '') {
                return false;
            }
            //if the definitions language is not a language supported by the ESL Apps dictionary, re-route to MS API
            if (langs.indexOf(langdef) === -1) {
                return alllangs_lone(allwords, 'en', langdef);
            }

            var promises = await ajax.call([
                {
                    methodname: 'mod_wordcards_search_dictionary',
                    args: {terms: allwords, cmid: that.cmid, sourcelang: 'en', targetlangs: langdef}
                },
            ]);

           var final_results = await promises[0].done(async function (response) {
                //if return code=0, disaster, log and die
                if (response.success === 0) {
                    log.debug(response.payload);
                    return false;
                }
                var repeatsearch_terms = [];
                var terms = JSON.parse(response.payload);
                var allterms_result = [];
                for (var i = 0; i < terms.length; i++) {
                    var theterm = terms[i];
                    //if a word search failed, try MS API for it
                    if (theterm.count === 0) {
                        repeatsearch_terms.push(theterm.term);
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
                            if (langdef !== "en") {
                                if (sense.hasOwnProperty('lang_' + langdef)) {
                                    definition = sense['lang_' + langdef];
                                } else if (langdef === 'en') {
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
                //if we have any words that failed on ESL Apps dictionary, we try MS API for them
                if(repeatsearch_terms.length>0){
                  var repeatsearch_results= await alllangs_lone(repeatsearch_terms.join(','), 'en', langdef);
                  if (repeatsearch_results) {
                      allterms_result = allterms_result.concat(repeatsearch_results);
                  }
                }
                //we return the combined results of search and repeatsearch
                return allterms_result;
            });
           //we return results to mustache
            return final_results;
        },

        alllangs_lone: async function (allwords, sourcelang, targetlang) {
            var that = this;
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
           

            //if we have no words, do nothing
            if (allwords.trim() === '') {
                log.debug('no words submitted to search for');
                return [];
            }

            //if the definitions language is not a language supported by the MS dictionary we give up
            if (langs.indexOf(sourcelang) === -1 || langs.indexOf(targetlang) === -1) {
                log.debug('MS API does not support ' + sourcelang + ' or ' + targetlang);
                return [];
            }

            var promises = await ajax.call([
                {
                    methodname: 'mod_wordcards_search_dictionary',
                    args: {terms: allwords, cmid: that.cmid, sourcelang: 'en', targetlangs: langdef}
                },
            ]);

            var final_results = await promises[0].done(async function (response) {
                //if return code=0, disaster, log and die
                if (response.success === 0) {
                    log.debug(response.payload);
                    return [];
                }

                //fetch the words from the API and turn them into data that the mustache template can use
                var terms = JSON.parse(response.payload);
                var allterms_result = [];
                for (var i = 0; i < terms.length; i++) {
                    var theterm = terms[i];
                    //if a word search failed, and an empty blank result
                    if (theterm.count === 0) {
                        var senses=[];
                        senses.push({definition: '',sourcedefinition: 'No def. available',
                            modelsentence: '', senseindex: 0, translations: '{}'})
                        var tdata = {term: theterm.term, senses: senses, modid:that.modid};
                        allterms_result.push(tdata);

                    } else {
                        var tdata = {term: theterm.term, senses: [], modid: that.modid};
                        for (var sindex in theterm.results) {
                            var sense = theterm.results[sindex];
                            //definition = the translation, ie L2 definitions
                            //source definition = the L1 definition. we might not know that, and prob wont use it
                            var definition = sense.definition;
                            var alltrans = {};
                            alltrans[targetlang] = definition;

                            var translations = JSON.stringify(alltrans);
                            //how could it be empty?
                            if (definition === "") {
                                definition = 'No translation available';
                            }

                            //model sentence is only in english (for now)
                            var modelsentence = sense.example;


                            tdata.senses.push({
                                definition: definition, sourcedefinition: definition,
                                modelsentence: modelsentence, senseindex: sindex, translations: translations
                            });
                        }//end of results loop
                        allterms_result.push(tdata);
                    }
                }

                //we return the combined results of search and repeatsearch
                return allterms_result;

            });
            //we return results to mustache
            return final_results;


        }
    }

});

