/**
 * Module to manage actions on a card in learn mode in mod_wordcards.
 *
 * @package mod_wordcards
 * @author Justin Hunt - justin@poodll.com
 */
define(['jquery', 'core/ajax', 'core/str', 'core/log', 'mod_wordcards/youglish'], function($, ajax, str,log, youglish) {
    const SELECTOR = {
        CARD: '.definition_flashcards .wc-faces',
        FRONTFACE: '[data-face="term"]',
        BACKFACE: '[data-face="details"]',
        YOUGLISH_HOLDER: '.term-video',
        YOUGLISH_WIDGET: '#mod_wordcardsyouglish-widget',
        YOUGLISH_PLACEHOLDER: '.youglish-placeholder',
    }


    const EVENT = {
        CLICK: 'click'
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

    const clearYouGlish = function(currentface) {
        log.debug('clearYouGlish');
        var youglishwidget = $(SELECTOR.YOUGLISH_WIDGET);
        var youglishplaceholder = currentface.find(SELECTOR.YOUGLISH_PLACEHOLDER);
        youglishplaceholder.show();
        youglishwidget.hide();
        youglish.clear();
    }
    const loadYouGlish = function(currentface) {
        log.debug('loadYouGlish');
        var youglishholder = currentface.find(SELECTOR.YOUGLISH_HOLDER);
        var youglishplaceholder = currentface.find(SELECTOR.YOUGLISH_PLACEHOLDER);
        youglish.load(youglishplaceholder.data('term'),youglishholder);
    }


    const initButtonListeners = function() {
        $(SELECTOR.CARD).on(EVENT.CLICK, function(e) {

            const currTar = $(e.currentTarget);
            const faceback = currTar.find(SELECTOR.BACKFACE);
            const facefront = currTar.find(SELECTOR.FRONTFACE);
            if(faceback.is(":visible")){
                faceback.hide();
                facefront.show();
                clearYouGlish(faceback);
            }else if(facefront.is(":visible")){
                facefront.hide();
                faceback.show();
            }
        });

        $(SELECTOR.YOUGLISH_PLACEHOLDER).on(EVENT.CLICK, function(e) {
            e.stopPropagation();
            const currTar = $(e.currentTarget);
            loadYouGlish(currTar.closest(SELECTOR.BACKFACE));
        });
    };

    return {
        init: function () {
            $(document).ready(function() {
                initStrings();
                initButtonListeners();
                initYouGlish();
            })
        }
    }
});