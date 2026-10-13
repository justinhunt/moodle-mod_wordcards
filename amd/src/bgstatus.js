/**
 * Keeps the message about words being added in the background up to date, and takes it away
 * once they are all in.
 *
 * @module     mod_wordcards/bgstatus
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/log', 'core/ajax', 'core/templates'], function($, log, ajax, templates) {

    "use strict";

    // How often to ask whether the background work is done.
    var INTERVAL = 15000;

    // Fired on the document when the background work is all done, for messages to clear themselves.
    var DONE_EVENT = 'mod_wordcards:backgrounddone';

    return {
        timer: null,
        DONE_EVENT: DONE_EVENT,

        /**
         * Start watching, if there is anything to watch.
         */
        init: function() {
            if (this.holder().attr('data-active') === '1') {
                this.watch();
            }
        },

        /**
         * The message's holder on the page.
         *
         * @returns {jQuery}
         */
        holder: function() {
            return $('[data-region="mod_wordcards_bgstatus"]');
        },

        /**
         * Show what is pending now, and keep checking until it is done.
         *
         * @param {Object} pending words, images and retrying, as get_background_status returns them
         */
        update: function(pending) {
            var that = this;
            var holder = this.holder();
            if (!holder.length) {
                return;
            }
            var active = pending.words > 0 || pending.images > 0;
            var wasactive = holder.attr('data-active') === '1';
            var reloadurl = holder.attr('data-reloadurl') || '';
            var context = {
                cmid: holder.attr('data-cmid'),
                reloadurl: reloadurl,
                words: pending.words,
                active: active,
                haswords: pending.words > 0,
                hasimages: pending.images > 0,
                hasretrying: pending.retrying > 0,
                // Where the page lists the words, it is out of date once they are in.
                done: !active && wasactive && reloadurl !== ''
            };
            templates.render('mod_wordcards/ww_bgstatus', context).then(function(html) {
                that.holder().replaceWith(html);
                return html;
            }).catch(function(ex) {
                log.debug(ex);
            });
            if (active) {
                this.watch();
            } else if (wasactive) {
                $(document).trigger(DONE_EVENT);
            }
        },

        /**
         * Check again in a while.
         */
        watch: function() {
            var that = this;
            if (this.timer) {
                return;
            }
            this.timer = setTimeout(function() {
                that.timer = null;
                that.check();
            }, INTERVAL);
        },

        /**
         * Ask the server what is still pending.
         */
        check: function() {
            var that = this;
            ajax.call([{
                methodname: 'mod_wordcards_get_background_status',
                args: {cmid: Number(this.holder().attr('data-cmid'))}
            }])[0].then(function(pending) {
                that.update(pending);
                return pending;
            }).catch(function(ex) {
                // Try again later rather than give up on a passing failure.
                log.debug(ex);
                that.watch();
            });
        }
    };
});
