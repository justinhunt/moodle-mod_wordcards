define(['jquery', 'core/log', 'core/ajax', 'core/str', 'mod_wordcards/bgstatus'], function ($, log, ajax, str, bgstatus) {
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
                    { "key": "imagesgenerated", "component": "mod_wordcards" },
                    { "key": "ww_images_queued", "component": "mod_wordcards" },
                    { "key": "ww_queuefailed", "component": "mod_wordcards" }
                ]).done(function (s) {
                    var i = 0;
                    self.strings.noimagesgenerated = s[i++];
                    self.strings.generatingimages = s[i++];
                    self.strings.imagesgenerated = s[i++];
                    self.strings.imagesqueued = s[i++];
                    self.strings.queuefailed = s[i++];
                });
        },

        register_events: function(){
            log.debug('registering events');
            var self = this;
            var bulkbtn = $('.ww_words_fetch_image_btn');
            var resultscont = $('.mod_wordcards_ww_image_results');

            // The "will be made in the background" message is out of date once they have been.
            $(document).on(bgstatus.DONE_EVENT, function() {
                if (resultscont.find('.ww_images_queued').length) {
                    resultscont.empty().hide();
                }
            });
        
            bulkbtn.on('click', function(e){
                log.debug('bulkbtn clicked');
                if ($('.ww_images_background').prop('checked')) {
                    self.queue_images(bulkbtn, resultscont);
                    return;
                }
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

        // Make the pictures in the background instead, and say so.
        queue_images: function(bulkbtn, resultscont) {
            var self = this;
            bulkbtn.prop('disabled', true);
            ajax.call([{
                methodname: 'mod_wordcards_queue_images',
                args: {cmid: self.cmid}
            }])[0].then(function(result) {
                bgstatus.update(result.pending);
                resultscont.empty().append($('<div class="alert alert-info ww_images_queued"></div>')
                    .text(self.strings.imagesqueued)).show();
                bulkbtn.prop('disabled', false);
                return result;
            }).catch(function(ex) {
                log.error('Error queueing images: ', ex);
                resultscont.empty().append($('<div class="alert alert-danger"></div>').text(self.strings.queuefailed)).show();
                bulkbtn.prop('disabled', false);
            });
        },
    };//end of return value
});