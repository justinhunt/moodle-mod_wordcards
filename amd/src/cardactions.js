/**
 * Module to watch my words buttons for clicks and report to back end.
 *
 * @package mod_wordcards
 * @author David Watson - evolutioncode.uk
 */
define(['jquery', 'core/ajax', 'core/str', 'core/log'], function($, ajax, str,log) {
    const SELECTOR = {
        CARD: '.definition_flashcards .wc-faces',
        DETAILSFACE: '[data-face="one"]',
        TERMFACE: '[data-face="two"]',
        YOUGLISHFACE: '[data-face="three"]',
    }

    const CLASS = {
        BTN_IN_MY_WORDS: 'btn-primary'
    }

    const EVENT = {
        CLICK: 'click'
    }


    const FACES = {
        DETAILS: 'one',
        TERM: 'two',
        YOUGLISH: 'three',
    }

    var youglish_template='<a id="yg-widget-0" class="youglish-widget" data-query="@@DATA-QUERY@@" data-lang="english" data-zones="all,us,uk,aus" data-components="8412" data-bkg-color="theme_light"  rel="nofollow" href="https://youglish.com">Visit YouGlish.com</a>\n' +
        '<script async src="https://youglish.com/public/emb/widget.js" charset="utf-8"></script>';

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
    }

    const initButtonListeners = function() {
        log.debug('button listeners inited');
        $(SELECTOR.CARD).on(EVENT.CLICK, function(e) {
            log.debug('click event happened');
            // There are two buttons for each term (one in grid and one in flashcards).
            const currTar = $(e.currentTarget);

            var facedetails = currTar.find(SELECTOR.DETAILSFACE);
            var faceterm = currTar.find(SELECTOR.TERMFACE);
            var faceyouglish = currTar.find(SELECTOR.YOUGLISHFACE);
            if(facedetails.is(":visible")){
                log.debug('facedetails is visible');
                facedetails.hide();
                faceterm.show();
            }else if(faceterm.is(":visible")){
                log.debug('faceterm is visible');
                faceterm.hide();
                var theterm=facedetails.find('.term-title').text();
                log.debug(theterm)
                var widget= youglish_template.replace('@@DATA-QUERY@@', theterm);
                faceyouglish.html(widget);
                faceyouglish.show();
            }else if(faceyouglish.is(":visible")){
                log.debug('faceyouglish is visible');
                faceyouglish.html('');
                faceyouglish.hide();
                facedetails.show();
            }
        })
    }

    return {
        init: function () {
            $(document).ready(function() {
                initStrings();
                initButtonListeners();
            })
        }
    }
});