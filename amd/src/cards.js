/**
 * Cards module.
 *
 * @package mod_flashcards
 * @author  Frédéric Massart - FMCorz.net
 */

// TODO Handle window resizing/rotating?
// TODO Test Edge
// TODO Report success/errors via Ajax

define([
    'jquery',
    'core/ajax',
    'core/notification'
], function($, Ajax, Notification) {

    /**
     * Randomize array element order in-place.
     * Using Durstenfeld shuffle algorithm.
     * @see http://stackoverflow.com/questions/2450954/how-to-randomize-shuffle-a-javascript-array
     */
    function shuffleArray(array) {
        for (var i = array.length - 1; i > 0; i--) {
            var j = Math.floor(Math.random() * (i + 1));
            var temp = array[i];
            array[i] = array[j];
            array[j] = temp;
        }
        return array;
    }

    var Cards = function(selector, terms) {
        this.container = $(selector);
        this.terms = terms;
        this.selected = null;
    };

    Cards.prototype.init = function() {
        var pool = [],
            width = this.container.width(),
            height = $(window).height(),
            perRow = 2,
            cardCount = this.terms.length * 2;

        if (cardCount % 2 > cardCount % 3) {
            perRow = 3;
        }
        var cardWidth = Math.floor(width / perRow);
        var cardHeight = Math.min(Math.round((height - 50) / Math.ceil(cardCount / perRow)), 60);
        
        this.terms.forEach(function(item) {
            pool.push(this._makeCard(item.id, item.term));
            pool.push(this._makeCard(item.id, item.definition));
        }.bind(this));

        var row = 0,
            col = 0;
        shuffleArray(pool);
        pool.forEach(function(item) {
            item.css({
                top: row * cardHeight,
                left: col * cardWidth,
                width: col == perRow  - 1 ? cardWidth : cardWidth - 4,
                height: cardHeight - 4
            })
            this.container.append(item);

            col++;
            if (col >= perRow) {
                col = 0
                row++;
            }
        }.bind(this));

        this.container.css({height: row * cardHeight});

        this.container.on('click', '.flashcard', this._handlePick.bind(this));
    };

    Cards.prototype._checkComplete = function() {
        if (this.container.find('.flashcard.found').length == this.terms.length * 2) {
            this._trigger('complete');
        }
    }

    Cards.prototype._handlePick = function(e) {
        e.preventDefault();
        var card = $(e.currentTarget);

        // It's already invisible.
        if (card.hasClass('found')) {
            return;
        }

        // It's the first out of the two picks.
        if (!this.selected) {
            this.selected = card;
            card.addClass('selected');
            return;
        }

        // We've clicked the selected card.
        if (this.selected == card) {
            return;
        }

        // It's a match!
        if (card.data('id') == this.selected.data('id')) {
            this.selected
                .addClass('found')
                .animate({'opacity': 0});
            card.addClass('found')
                .animate({'opacity': 0});

            this._checkComplete();

        // It's not a match...
        } else {
            var original = this.selected;
            original.addClass('mismatch');
            card.addClass('mismatch');
            setTimeout(function() {
                original.removeClass('mismatch');
                card.removeClass('mismatch');
            }, 600);
        }

        // Reset the selection.
        this.selected.removeClass('selected');
        this.selected = null;
    }

    Cards.prototype._makeCard = function(id, text) {
        var container = $('<div class="flashcard">')
            .data('id', id);

        container.append($('<div>').text(text));
        return container
    };

    Cards.prototype.on = function(action, cb) {
        this.container.on(action, cb);
    }

    Cards.prototype._trigger = function(action) {
        this.container.trigger(action);
    }

    return Cards;

});