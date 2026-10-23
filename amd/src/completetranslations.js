/**
 * The Word Wizard's button for filling in missing translations with the site's AI.
 *
 * The site's AI may only be used once its policy has been accepted, so the first press by somebody
 * who has not accepted it shows the policy instead, and carries on once they agree.
 *
 * @module     mod_wordcards/completetranslations
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/log', 'core/ajax', 'core/str', 'mod_wordcards/aipolicy'], function($, log, ajax, str, aipolicy) {

    "use strict";

    return {
        /**
         * Set the button up.
         *
         * @param {Object} opts cmid, the button and results element ids, and policyaccepted
         */
        init: function(opts) {
            var self = this;
            self.cmid = opts.cmid;
            self.policyaccepted = opts.policyaccepted;
            self.button = $('#' + opts.buttonid);
            self.results = $('#' + opts.resultsid);

            self.button.on('click', function(e) {
                e.preventDefault();
                if (self.button.hasClass('disabled')) {
                    return;
                }
                if (self.policyaccepted) {
                    self.run();
                    return;
                }
                aipolicy.accept().then(function(accepted) {
                    if (accepted) {
                        self.policyaccepted = true;
                        self.run();
                    }
                    return accepted;
                }).catch(log.debug);
            });
        },

        /**
         * Queue the words.
         */
        run: function() {
            var self = this;
            self.button.addClass('disabled');
            ajax.call([{
                methodname: 'mod_wordcards_complete_translations',
                args: {cmid: self.cmid}
            }])[0].done(function(response) {
                self.results.text(response.message);
                self.policyaccepted = !response.policyneeded;
                self.button.removeClass('disabled');
            }).fail(function(err) {
                log.debug(err);
                str.get_string('ai_completetranslations_failed', 'mod_wordcards').done(function(message) {
                    self.results.text(message);
                });
                self.button.removeClass('disabled');
            });
        }
    };
});
