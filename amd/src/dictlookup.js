/**
 * The Word Wizard: looks words up, shows the meanings of each, and adds the one chosen.
 *
 * Each word gets a card of its own. Once a meaning of it is added the card fades away and the
 * word joins the list of added words, so a second meaning of the same word cannot be added by
 * mistake. The server refuses duplicates as well, this just keeps them from being offered.
 *
 * @module     mod_wordcards/dictlookup
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['jquery', 'core/log', 'core/ajax', 'core/templates', 'core/str', 'core/notification', 'mod_wordcards/bgstatus'],
    function($, log, ajax, templates, str, notification, bgstatus) {

    "use strict";

    return {
        // The page's settings, from init().
        opts: null,
        // The parts of the page we work with.
        searchbtn: null,
        resultscont: null,
        addedcont: null,
        // Each word's entry from the server, by its card number.
        entries: {},
        // Bumped on each search, so the answers to an earlier one are ignored.
        searchno: 0,
        strings: {},

        /**
         * Set the wizard up.
         *
         * @param {Object} opts cmid, modid, langterm (the language being taught) and root (a selector)
         */
        init: function(opts) {
            var root = $(opts.root);
            this.opts = opts;
            this.searchbtn = root.find('.ww_words_fetch_btn');
            this.resultscont = root.find('.mod_wordcards_ww_results');
            this.addedcont = root.find('.mod_wordcards_ww_added');
            this.wordsbox = root.find('.mod_wordcards_ww_words');
            this.langdefselect = root.find('select[name="ww_langdef"]');
            this.backgroundbox = root.find('.mod_wordcards_ww_background');
            this.withimagesbox = root.find('.mod_wordcards_ww_withimages');
            this.queueresult = root.find('.mod_wordcards_ww_queueresult');

            var that = this;
            str.get_strings([
                {key: 'ww_addfailed', component: 'mod_wordcards'},
                {key: 'ww_needdefinition', component: 'mod_wordcards'},
                {key: 'ww_queuefailed', component: 'mod_wordcards'}
            ]).then(function(s) {
                that.strings.addfailed = s[0];
                that.strings.needdefinition = s[1];
                that.strings.queuefailed = s[2];
                return s;
            }).catch(notification.exception);

            this.searchbtn.on('click', function() {
                if (that.backgroundbox.prop('checked')) {
                    that.queueWords();
                } else {
                    that.search();
                }
            });
            // The "will be added in the background" message is out of date once they have been.
            $(document).on(bgstatus.DONE_EVENT, function() {
                that.queueresult.prop('hidden', true).text('');
            });
            // In the background there is nobody to choose a meaning, and pictures can be made too.
            this.backgroundbox.on('change', function() {
                var background = $(this).prop('checked');
                that.withimagesbox.closest('.mod_wordcards_ww_withimagesopt').prop('hidden', !background);
                that.searchbtn.text(that.searchbtn.attr(background ? 'data-queuelabel' : 'data-fetchlabel'));
            });
            this.resultscont.on('click', '[data-action]', function(e) {
                e.preventDefault();
                that.handleAction($(this));
            });
            // Keep the meaning shown in step with its edit box.
            this.resultscont.on('input', '.mod_wordcards_ww_defedit', function() {
                $(this).closest('.mod_wordcards_ww_sense').find('.mod_wordcards_ww_def').text($(this).val().trim());
            });
            this.resultscont.on('input', '.mod_wordcards_ww_sentenceedit', function() {
                $(this).closest('.mod_wordcards_ww_sense').find('.mod_wordcards_ww_sentence').text($(this).val().trim());
            });
        },

        /**
         * The words in the box: split on commas and new lines, with blanks and repeats left out.
         *
         * @returns {string[]}
         */
        getWords: function() {
            var seen = {};
            return this.wordsbox.val().split(/[,\n]/).map(function(word) {
                return word.trim();
            }).filter(function(word) {
                var key = word.toLowerCase();
                if (word === '' || seen[key]) {
                    return false;
                }
                seen[key] = true;
                return true;
            });
        },

        /**
         * Look up every word in the box, each in its own request so they come back in parallel.
         */
        search: function() {
            var that = this;
            var words = this.getWords();
            var langdef = this.langdefselect.val();
            // With "other" as the definitions language there is nothing the dictionary can give us.
            if (words.length === 0 || !langdef || langdef === 'zz') {
                return;
            }

            this.searchno++;
            this.entries = {};
            this.resultscont.empty();
            this.queueresult.prop('hidden', true);
            this.searchbtn.prop('disabled', true).attr('aria-busy', 'true');

            var lookups = words.map(function(word, cardno) {
                return that.lookupWord(cardno, word, langdef);
            });
            var searchno = this.searchno;
            Promise.all(lookups).then(function() {
                if (searchno === that.searchno) {
                    that.searchbtn.prop('disabled', false).removeAttr('aria-busy');
                }
                return null;
            }).catch(notification.exception);
        },

        /**
         * Queue every word in the box to be looked up and added in the background.
         */
        queueWords: function() {
            var that = this;
            var words = this.getWords();
            var langdef = this.langdefselect.val();
            if (words.length === 0 || !langdef || langdef === 'zz') {
                return;
            }

            this.searchbtn.prop('disabled', true).attr('aria-busy', 'true');
            ajax.call([{
                methodname: 'mod_wordcards_queue_words',
                args: {
                    cmid: this.opts.cmid,
                    words: words,
                    deflang: langdef,
                    withimages: this.withimagesbox.prop('checked')
                }
            }])[0].then(function(result) {
                var strings = [{key: 'ww_queued', component: 'mod_wordcards', param: result.queued}];
                if (result.alreadyinset.length) {
                    strings.push({key: 'ww_queued_already', component: 'mod_wordcards',
                        param: result.alreadyinset.join(', ')});
                }
                bgstatus.update(result.pending);
                that.wordsbox.val('');
                return str.get_strings(strings);
            }).then(function(messages) {
                that.queueresult.text(messages.join(' ')).prop('hidden', false);
                return messages;
            }).catch(function(ex) {
                log.debug(ex);
                that.queueresult.text(that.strings.queuefailed).prop('hidden', false);
            }).then(function() {
                that.searchbtn.prop('disabled', false).removeAttr('aria-busy');
                return null;
            }).catch(notification.exception);
        },

        /**
         * Look one word up and show its card, a loading card until the answer is in.
         *
         * @param {number} cardno the word's position in the results
         * @param {string} word
         * @param {string} langdef the definitions language
         * @returns {Promise}
         */
        lookupWord: function(cardno, word, langdef) {
            var that = this;
            var searchno = this.searchno;
            var card = this.resultscont.children('[data-cardno="' + cardno + '"]');

            return templates.render('mod_wordcards/ww_skeleton', {term: word}).then(function(html) {
                if (searchno !== that.searchno) {
                    throw new Error('A newer search has started');
                }
                var placeholder = $(html).attr('data-cardno', cardno);
                if (card.length) {
                    card.replaceWith(placeholder);
                } else {
                    // Keep the cards in the order the words were typed, whatever order they render in.
                    that.insertCard(placeholder, cardno);
                }
                return ajax.call([{
                    methodname: 'mod_wordcards_search_dictionary',
                    args: {terms: word, cmid: that.opts.cmid, sourcelang: that.opts.langterm, targetlangs: langdef}
                }])[0];
            }).then(function(response) {
                if (!response.success) {
                    log.debug(response.payload);
                    return {term: word, failed: true};
                }
                // One word was asked for, so one comes back.
                return JSON.parse(response.payload)[0] || {term: word, found: false, senses: []};
            }).catch(function(ex) {
                log.debug(ex);
                return {term: word, failed: true};
            }).then(function(entry) {
                if (searchno !== that.searchno) {
                    return null;
                }
                entry.langdef = langdef;
                that.entries[cardno] = entry;
                return that.showCard(cardno, entry);
            });
        },

        /**
         * Put a card into the results in its place.
         *
         * @param {jQuery} card
         * @param {number} cardno
         */
        insertCard: function(card, cardno) {
            var after = this.resultscont.children().filter(function() {
                return Number($(this).attr('data-cardno')) > cardno;
            }).first();
            if (after.length) {
                after.before(card);
            } else {
                this.resultscont.append(card);
            }
        },

        /**
         * Show a word's card, replacing whatever it shows now.
         *
         * @param {number} cardno
         * @param {Object} entry the word, as the server sent it
         * @returns {Promise}
         */
        showCard: function(cardno, entry) {
            var that = this;
            return templates.render('mod_wordcards/word_wizard_oneresult', entry).then(function(html) {
                var card = $(html).attr('data-cardno', cardno);
                that.resultscont.children('[data-cardno="' + cardno + '"]').replaceWith(card);
                return card;
            }).catch(notification.exception);
        },

        /**
         * Do what a button on a card asks.
         *
         * @param {jQuery} button
         */
        handleAction: function(button) {
            var card = button.closest('.mod_wordcards_ww_word');
            var cardno = Number(card.attr('data-cardno'));
            var entry = this.entries[cardno];
            switch (button.attr('data-action')) {
                case 'skip':
                    this.removeCard(card);
                    break;
                case 'retry':
                    this.lookupWord(cardno, entry.term, entry.langdef);
                    break;
                case 'edit':
                    var editor = button.closest('.mod_wordcards_ww_sense').find('.mod_wordcards_ww_senseedit');
                    var open = editor.prop('hidden');
                    editor.prop('hidden', !open);
                    button.attr('aria-expanded', open ? 'true' : 'false');
                    if (open) {
                        editor.find('input').trigger('focus');
                    }
                    break;
                case 'use':
                    this.useSense(card, button.closest('.mod_wordcards_ww_sense'), entry);
                    break;
            }
        },

        /**
         * Add the chosen meaning of a word to the activity.
         *
         * @param {jQuery} card the word's card
         * @param {jQuery} senseitem the meaning chosen
         * @param {Object} entry the word, as the server sent it
         */
        useSense: function(card, senseitem, entry) {
            var that = this;
            var senseindex = Number(senseitem.attr('data-senseindex'));
            var sense = entry.senses.find(function(s) {
                return s.senseindex === senseindex;
            });
            var errorbox = senseitem.find('.mod_wordcards_ww_senseerror');
            var definition = senseitem.find('.mod_wordcards_ww_defedit').val().trim();
            var modelsentence = senseitem.find('.mod_wordcards_ww_sentenceedit').val().trim();

            if (definition === '') {
                errorbox.text(this.strings.needdefinition).prop('hidden', false);
                senseitem.find('.mod_wordcards_ww_senseedit').prop('hidden', false);
                senseitem.find('[data-action="edit"]').attr('aria-expanded', 'true');
                senseitem.find('.mod_wordcards_ww_defedit').trigger('focus');
                return;
            }
            errorbox.prop('hidden', true);

            // The translations we hold are of the sentence the dictionary gave us. If the teacher has
            // rewritten it they no longer describe what will be on the card, so drop them and say so:
            // the server queues a different task for a sentence of its own.
            var sentenceedited = modelsentence !== sense.modelsentence;

            var buttons = card.find('button');
            buttons.prop('disabled', true);
            ajax.call([{
                methodname: 'mod_wordcards_submit_newterm',
                args: {
                    modid: this.opts.modid,
                    term: entry.term,
                    definition: definition,
                    sourcedef: sense.sourcedefinition,
                    translations: sense.translations,
                    modelsentence: modelsentence,
                    modelsentencetranslations: sentenceedited ? '' : sense.modelsentencetranslations,
                    sentenceedited: sentenceedited
                }
            }])[0].then(function(result) {
                if (result.added) {
                    that.showAdded(entry.term, definition);
                    that.removeCard(card);
                } else if (result.alreadyinset) {
                    entry.alreadyinset = true;
                    that.showCard(Number(card.attr('data-cardno')), entry);
                } else {
                    errorbox.text(that.strings.addfailed).prop('hidden', false);
                    buttons.prop('disabled', false);
                }
                return result;
            }).catch(function(ex) {
                log.debug(ex);
                errorbox.text(that.strings.addfailed).prop('hidden', false);
                buttons.prop('disabled', false);
            });
        },

        /**
         * Add a word to the list of words added.
         *
         * @param {string} term
         * @param {string} definition
         */
        showAdded: function(term, definition) {
            var that = this;
            templates.render('mod_wordcards/ww_added', {term: term, definition: definition}).then(function(html) {
                that.addedcont.find('.mod_wordcards_ww_addedlist').append(html);
                that.addedcont.find('.mod_wordcards_ww_addedcount').text(
                    that.addedcont.find('.mod_wordcards_ww_addeditem').length);
                that.addedcont.prop('hidden', false);
                return html;
            }).catch(notification.exception);
        },

        /**
         * Fade a word's card away, letting the ones below it move up.
         *
         * @param {jQuery} card
         */
        removeCard: function(card) {
            var reduce = window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches;
            if (reduce) {
                card.remove();
                return;
            }
            card.fadeTo(250, 0).slideUp(250, function() {
                card.remove();
            });
        }
    };
});
