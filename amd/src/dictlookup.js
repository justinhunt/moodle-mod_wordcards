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

        replace_placeholder_with_result: function(placeholder, termdata) {
            var that = this;
            templates.render('mod_wordcards/word_wizard_oneresult', termdata).then(
                function (html, js) {
                    placeholder.replaceWith(html);
                    templates.runTemplateJS(js);
                }
            );
        },

        getwords: async function (allwords,sourcelang,definitionslang) {
            var that = this;
            var searchbtn = $(".ww_words_fetch_btn");

            //if we have no words, do nothing
            if (allwords.trim() === '') {
                return false;
            }

            that.resultscont.empty();

        //originally we passed a single request with all words in a CSV list in the terms arg
        //but that was too slow because the server would process them sequentially
        // so now we make a request for each word. It would still work with a single request
            var requests = [];
            var templatepromises = [];
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
                    let tdata = {'term': word, 'termno': requests.length-1};
                    templatepromises.push(templates.render('mod_wordcards/ww_skeleton',tdata));
                }
            }
            //first create all the placeholders, we use await in case the ajax requests return faster than the placeholders are created
            //replace button caption with spinner while we wait
            var searchbtncontent = searchbtn.html();
            searchbtn.html('<i class="icon fa fa-spinner fa-spin fa-fw"></i>');
           await Promise.all(templatepromises).then((results) => {
                results.forEach((html) => {
                    that.resultscont.append(html);
                });
            });
           //replace the spinner with the original caption
            searchbtn.html(searchbtncontent);
        
           // Loop through the requests, send and respond to each 
           for (let reqindex=0; reqindex < requests.length; reqindex++){
                ajax.call([requests[reqindex]],true)[0].then(response=>{

                    //fethc the placeholder
                    log.debug('placeholder - #mod_wordcards_wwskeleton_'+ reqindex);
                    var placeholder = $('#mod_wordcards_wwskeleton_'+ reqindex);

                    //if return code=0, disaster, log and continue
                    if (response.success === 0) {
                        log.debug(response.payload);
                    }
                    var terms = JSON.parse(response.payload);
                    for (var i = 0; i < terms.length; i++) {
                        var theterm = terms[i];
                        //if a word search failed
                        if (theterm.count === 0 || theterm.results.length===0) {
                            var senses = [];
                            senses.push({
                                definition: 'No definition', sourcedefinition: 'No definition',
                                modelsentence: '', senseindex: 0, translations: '{}'
                            })
                            var tdata = {term: theterm.term, senses: senses, modid: that.modid};
                            that.replace_placeholder_with_result(placeholder,tdata);

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
                                    } else {
                                        definition = 'No translation available';
                                    }
                                }else{
                                    if (sense.hasOwnProperty('meaning')) {
                                        definition = sense.meaning;
                                    } else if (sense.hasOwnProperty('lang_en')) {
                                        definition = sense['lang_en'];
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
                            that.replace_placeholder_with_result(placeholder,tdata);
                        }
                    }//end of terms loop
                });
           }
    
        },
    }

});

