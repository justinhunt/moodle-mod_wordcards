/**
 * Asks for the site's AI policy to be accepted, for the Word Wizard's AI buttons.
 *
 * The policy modal only exists from Moodle 4.5, which is also where those buttons first show, so it
 * is loaded only when it is needed.
 *
 * @module     mod_wordcards/aipolicy
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/log'], function(log) {

    "use strict";

    // The policy modal saves the acceptance itself, without telling us when it is done, so give it
    // a moment before asking the server to rely on it.
    var SAVE_WAIT = 1500;

    return {
        /**
         * Show the policy.
         *
         * @returns {Promise<boolean>} true once it is accepted, false if it is closed without
         */
        accept: function() {
            return new Promise(function(resolve) {
                require(['core_ai/policymodal', 'core/custom_interaction_events', 'core/modal_events'],
                    function(PolicyModal, CustomEvents, ModalEvents) {
                        var modalclass = PolicyModal.default || PolicyModal;
                        var accepted = false;
                        modalclass.create().then(function(modal) {
                            modal.getModal().on(CustomEvents.events.activate, modal.getActionSelector('save'), function() {
                                accepted = true;
                                setTimeout(function() {
                                    resolve(true);
                                }, SAVE_WAIT);
                            });
                            modal.getRoot().on(ModalEvents.hidden, function() {
                                if (!accepted) {
                                    resolve(false);
                                }
                            });
                            return modal;
                        }).catch(function(ex) {
                            log.debug(ex);
                            resolve(false);
                        });
                    });
            });
        }
    };
});
