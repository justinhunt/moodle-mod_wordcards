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

        show_failed: function(placeholder, word) {
            this.replace_placeholder_with_result(placeholder,
                {term: word, senses: [], nosenses: true, failed: true, modid: this.modid});
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
                // The server has already read the dictionary's results into senses (word_adder).
                const placeholder = $('#mod_wordcards_wwskeleton_' + reqindex);
                const word = requests[reqindex].args.terms;
                ajax.call([requests[reqindex]], true)[0].then(function(response) {
                    if (!response.success) {
                        log.debug(response.payload);
                        that.show_failed(placeholder, word);
                        return;
                    }
                    var entries = JSON.parse(response.payload);
                    entries.forEach(function(entry) {
                        that.replace_placeholder_with_result(placeholder, {
                            term: entry.term,
                            senses: entry.senses,
                            nosenses: !entry.found,
                            modid: that.modid
                        });
                    });
                    return;
                }).catch(function(ex) {
                    log.debug(ex);
                    that.show_failed(placeholder, word);
                });
           }
    
        },
    }

});

