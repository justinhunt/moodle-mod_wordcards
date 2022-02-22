/**
 * Apps4EFL module.
 *
 * @package mod_wordcards
 * @author David Watson - evolutioncode.uk
 */
define(['jquery', 'core/ajax'], function($, ajax) {
    const SELECTOR = {
        DATA_SET: '*[data-action="wordcards-set-my-words"]'
    }

    const CLASS = {
        ICON_NO: 'fa-plus',
        ICON_YES: 'fa-check',
        SPINNER: 'fa-spinner',
        PULSE: 'fa-pulse',
        DISABLED: 'disabled',
        EXCLAMATION: 'fa-exclamation-triangle',
        BTN_NOT_IN_MY_WORDS: 'btn-outline-primary',
        BTN_IN_MY_WORDS: 'btn-primary',
        DANGER: 'text-danger'
    }

    const EVENT = {
        CLICK: 'click'
    }

    const DATA = {
        TERM_ID: 'data-termid',
        VALUE: 'data-value'
    }
    return {
        init: function (courseId) {
            $(document).ready(function() {
                $(SELECTOR.DATA_SET).on(EVENT.CLICK, function(e) {
                    const currTar = $(e.currentTarget);
                    const termId = currTar.attr(DATA.TERM_ID);
                    const ELEMS = {
                        ADD_BUTTON: $("#wordcards-add-btn-" + termId),
                        REMOVE_BUTTON: $("#wordcards-remove-btn-" + termId),
                        SAVING: $("#wordcards-spinner-" + termId),
                    }
                    if (!ELEMS.ADD_BUTTON.hasClass(CLASS.DISABLED) && !ELEMS.REMOVE_BUTTON.hasClass(CLASS.DISABLED)) {
                        ELEMS.ADD_BUTTON.addClass(CLASS.DISABLED)
                        ELEMS.REMOVE_BUTTON.addClass(CLASS.DISABLED)
                        const newStatus = currTar.attr(DATA.VALUE) === "0" // If zero now, set to 1.
                        ELEMS.SAVING.fadeIn();
                        currTar.fadeOut();
                        ajax.call([{
                            methodname: 'mod_wordcards_set_my_words',
                            args: {
                                courseid: courseId,
                                termid: termId,
                                newstatus: newStatus
                            }
                        }])[0].done(function(response) {
                            if (response.success) {
                                ELEMS.SAVING.fadeOut()
                                if (response.newStatus) {
                                    ELEMS.REMOVE_BUTTON.fadeIn();
                                } else
                                    ELEMS.ADD_BUTTON.fadeIn();
                                }
                                ELEMS.ADD_BUTTON.removeClass(CLASS.DISABLED)
                                ELEMS.REMOVE_BUTTON.removeClass(CLASS.DISABLED)
                            }).fail(function() {
                                ELEMS.ADD_BUTTON.removeClass(CLASS.DISABLED)
                                ELEMS.REMOVE_BUTTON.removeClass(CLASS.DISABLED)
                        })
                    }
                })
            })
        }
    }
});