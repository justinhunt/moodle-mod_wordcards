define(['jquery', 'core/log', 'core/ajax', 'core/str'], function ($, log, ajax, str) {
    "use strict"; // jshint ;_;
    /*
    This file does bulk image generation for wordcards
     */

    log.debug('Bulk Image Generator: initialising');

    return {
        cmid:  false,
        strings: {},

        init: function(cmid){
            this.cmid = cmid;
            this.init_strings();
            this.register_events();
            log.debug('Bulk Image Generator: initialised');
        },

        init_strings: function() {
            var self = this;
             // Set up strings
            str.get_strings([
                    { "key": "noimagesgenerated", "component": "mod_wordcards" },
                    { "key": "generatingimages", "component": "mod_wordcards" },
                    { "key": "imagesgenerated", "component": "mod_wordcards" }
                ]).done(function (s) {
                    var i = 0;
                    self.strings.noimagesgenerated = s[i++];
                    self.strings.generatingimages = s[i++];
                    self.strings.imagesgenerated = s[i++];
                });
        },

        register_events: function(){
            log.debug('registering events');
            var self = this;
            var bulkbtn = $('.ww_words_fetch_image_btn');
            var resultscont = $('.mod_wordcards_ww_image_results');
        
            bulkbtn.on('click', function(e){
                log.debug('bulkbtn clicked');
                resultscont.empty();
                resultscont.append('<div>' + self.strings.generatingimages + '</div><i class="fa fa-spinner fa-spin fa-3x"></i>');
                resultscont.show();

                //Disable the button to prevent multiple clicks
                bulkbtn.prop('disabled', true);

                //Call the ajax method to generate images
                log.debug('Calling ajax to generate images');
                ajax.call([{
                        methodname: 'mod_wordcards_generate_bulk_images',
                        args: {
                            cmid: self.cmid
                        }
                    }])[0].then(function(result) {
                        log.debug(result);
                        resultscont.empty();
                        if(result.images && result.images.length > 0) {
                            resultscont.append('<div>' + result.images.length + ' ' + self.strings.imagesgenerated + '</div>');
                            for (var i = 0; i < result.images.length; i++) {
                                var image = result.images[i];
                                var img = $('<img>', {
                                    src: image.url,
                                    class: 'ww_bulk_image_thumb'
                                });
                                resultscont.append(img);
                            }
                        } else {
                            resultscont.append('<div class="alert alert-info">' + self.strings.noimagesgenerated + '</div>');
                        }
                        // Re enable the button after the operation is complete
                        bulkbtn.prop('disabled', false);
                    
                }).catch(function(ex) {
                    log.error('Error generating images: ', ex);
                    resultscont.empty();
                    resultscont.append('<div class="alert alert-danger">' + (ex.message || ex) + '</div>');
                    // Re enable the button after the operation is complete
                    bulkbtn.prop('disabled', false);
                });
            });
        },
    };//end of return value
});