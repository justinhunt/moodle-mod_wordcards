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

            //if we have no words, do nothing
            if (allwords.trim() === '') {
                return false;
            }
        //originally we passed a single request with all words in a CSV list in the terms arg
        //but that was too slow because the server would process them sequentially
        // so now we make a request for each word. It would still work with a single request
            var requests = [];
            var wordarray = allwords.split(',');
            for (var i = 0; i < wordarray.length; i++) {
                var word = wordarray[i].trim();
                if (word !== '') {
                    requests.push({
                        methodname: 'mod_wordcards_search_dictionary',
                        args: {terms: word, cmid: that.cmid, sourcelang: sourcelang, targetlangs: definitionslang},
                        async: false
                    });
                }
            }

            var wordpromises = ajax.call(requests);
            Promise.all(wordpromises).then(async function(allresponses){
                var allterms_result = [];
                if(allresponses.length===0){
                    return allterms_result;
                }

                for(var responseindex = 0; responseindex < allresponses.length; responseindex++) {

                    var response = allresponses[responseindex];

                    //if return code=0, disaster, log and continue
                    if (response.success === 0) {
                        log.debug(response.payload);
                    }
                    var terms = JSON.parse(response.payload);
                    for (var i = 0; i < terms.length; i++) {
                        var theterm = terms[i];
                        //if a word search failed
                        if (theterm.count === 0) {
                            var senses = [];
                            senses.push({
                                definition: '', sourcedefinition: 'No def. available',
                                modelsentence: '', senseindex: 0, translations: '{}'
                            })
                            var tdata = {term: theterm.term, senses: senses, modid: that.modid};
                            allterms_result.push(tdata);

                        } else {
                            var tdata = {term: theterm.term, senses: [], modid: that.modid};
                            for (var sindex in theterm.results) {
                                var sense = theterm.results[sindex];
                                //by default its term:English def:English
                                var sourcedefinition = sense.definition;
                                var alltrans = {};
                                for (var langkey in sense) {
                                    if (sense.hasOwnProperty(langkey) && langkey.startsWith('lang_')) {
                                        alltrans[langkey.substring(5)] = sense[langkey];
                                    }
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

                                //model sentence)
                                var modelsentence = sense.example;


                                tdata.senses.push({
                                    definition: definition, sourcedefinition: sourcedefinition,
                                    modelsentence: modelsentence, senseindex: sindex, translations: translations
                                });
                            }//end of results loop
                            allterms_result.push(tdata);
                        }
                    }//end of terms loop
                }//end of allresponses loop
                that.update_page(allterms_result );
            });//end of promise then
        },
    }

});

