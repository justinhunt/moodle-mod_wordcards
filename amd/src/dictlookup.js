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

        add_result_to_page: function(termdata) {
            var that = this;
            templates.render('mod_wordcards/word_wizard_oneresult', termdata).then(
                function (html, js) {
                    that.resultscont.prepend(html);
                    templates.runTemplateJS(js);
                }
            );
        },

        getwords: function (allwords,sourcelang,definitionslang) {
            var that = this;

            //if we have no words, do nothing
            if (allwords.trim() === '') {
                return false;
            }

            that.resultscont.empty();

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
                        async: true
                    });
                    //add placeholders for each word
                    var tdata = {'term': word, 'termno': i};
                    templates.render('mod_wordcards/ww_skeleton',tdata).done(function(html, js) {
                        that.resultscont.append(html);
                    }).fail(function(ex) {
                        log.error(ex);
                    });
                }
            }
        
           // Loop through the requests, send and respond to each 
           for (let reqindex=0; reqindex < requests.length; reqindex++){
                ajax.call([requests[reqindex]],true)[0].then(response=>{
                    //remove the skeleton placeholder
                    $('#mod_wordcards_wwskeleton_'+ reqindex).remove();

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
                            
                            that.add_result_to_page(tdata);
                        }
                    }//end of terms loop
                });
           }
           
           return;

                  
           var wordpromises = [];
           for (var reqindex=0; reqindex < requests.length; reqindex++){
                wordpromises.push(ajax.call([requests[reqindex]],true)[0]); 
           }

      
          // This, oddly, did not run the requests in parallel.
          // So it was too slow and errored on occasion (timeouts I think).
          //var wordpromises = ajax.call(requests,true);
         
            
            Promise.all(wordpromises).then(function(allresponses){
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

