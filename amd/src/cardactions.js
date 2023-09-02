/**
 * Module to manage actions on a card in learn mode in mod_wordcards.
 *
 * @package mod_wordcards
 * @author Justin Hunt - justin@poodll.com
 */
define(['jquery', 'core/ajax', 'core/str', 'core/log', 'mod_wordcards/youglish'], function($, ajax, str,log, youglish) {
    const SELECTOR = {
        CARDCONTAINER: '.flip-card',
        CARD: '.definition_flashcards .wc-faces.flip-card-inner',
        FRONTFACE: '[data-face="term"]',
        BACKFACE: '[data-face="details"]',
        YOUGLISH_HOLDER: '.term-video',
        YOUGLISH_WIDGET: '#mod_wordcardsyouglish-widget',
        YOUGLISH_PLACEHOLDER: '.youglish-placeholder',
        TESTING: '.testing-event-click'
    }


    const EVENT = {
        CLICK: 'click',
        HOVERIN: 'mouseover',
        HOVEROUT: 'mouseout'
    }


    var stringStore = {};

    const initStrings = function (callback) {
        str.get_strings([
            {key: "addtomywords", component: "mod_wordcards"},
            {key: "removefrommywords", component: "mod_wordcards"},
        ]).done(function (strings) {
            stringStore = strings;
            if (typeof callback == 'function') {
                callback();
            }
        });
    };

    const initYouGlish = function() {
        $.getScript('https://youglish.com/public/emb/widget.js', function(){
            log.debug('youglish script loaded');
        });
    };

    const clearYouGlish = function() {
        log.debug('clearYouGlish');
        youglish.clear();
    }
    const loadYouGlish = function(currentface) {
        log.debug('loadYouGlish');
        var youglishholder = currentface.find(SELECTOR.YOUGLISH_HOLDER);
        var youglishplaceholder = currentface.find(SELECTOR.YOUGLISH_PLACEHOLDER);
        youglish.load(youglishplaceholder.data('lang'),
            youglishplaceholder.data('term'),
            youglishplaceholder.data('accent'),
            youglishholder);
    }


    const initButtonListeners = function() {
        var that = this;
        
        $(SELECTOR.CARDCONTAINER).on(EVENT.CLICK, function(e) {
            const currTar = $(e.currentTarget);
            const actualTar = currTar.children();
            if (actualTar.hasClass("show-back-side")) {
                actualTar.removeClass("show-back-side");
            } else {
                actualTar.addClass("show-back-side")
            }
        });

       /*
        $("#card-audio").on(EVENT.CLICK, function(e) {
            
            //$(SELECTOR.CARDCONTAINER).css("pointer-events","none");

            console.log("Audio sounds");
            e.stopPropagation();
        });
        */

/*
        $(SELECTOR.CARD).on(EVENT.CLICK, function(e) {
            const currTar = $(e.currentTarget);
            const cardContainer = currTar.find(SELECTOR.CARD);
            currTar.css("transform", "rotateY(180deg)");
            console.log(cardContainer);
            const faceback = currTar.find(SELECTOR.BACKFACE);
            const facefront = currTar.find(SELECTOR.FRONTFACE);
            

            if(faceback.is(":visible")){
                faceback.hide();
                facefront.show();
                console.log("hello from Wordcards");
                clearYouGlish(faceback);
            }else if(facefront.is(":visible")){
                facefront.hide();
                faceback.show();
                console.log("hi from Wordcards");
            }
        });
*/
        $(SELECTOR.YOUGLISH_PLACEHOLDER).on(EVENT.CLICK, function(e) {
            e.stopPropagation();
            if($(".term-image")){
                $(".term-image").css('display', 'none');
                $(".retrieve-image").css('display', 'block');
            }
            const currTar = $(e.currentTarget);
            loadYouGlish(currTar.closest(SELECTOR.BACKFACE));

            if($(".retrieve-image")){
                $(".retrieve-image").on(EVENT.CLICK, function(e) {
                    e.stopPropagation();
                    $(".term-video").css('display', 'none');
                    $(".retrieve-image").css('display', 'none');
                    $(".term-image").css('display', 'block');
                    $(".retrieve-video").css('display', 'block');
                    clearYouGlish();
                })
            }

            if($(".retrieve-video")){
                $(".retrieve-video").on(EVENT.CLICK, function(e) {
                    e.stopPropagation();
                    $(".term-video").css('display', 'block');
                    $(".retrieve-image").css('display', 'block');
                    $(".term-image").css('display', 'none');
                    $(".retrieve-video").css('display', 'none');
                    //this will be different to the currentTarget above
                    const localCurrTar = $(e.currentTarget);
                    loadYouGlish(currTar.closest(SELECTOR.BACKFACE));
                })
            }
        });


     
    };

    return {
        init: function () {
            $(document).ready(function() {
                initStrings();
                initButtonListeners();
                initYouGlish();
            })
        },
        clearYouGlish: clearYouGlish,
    }
});