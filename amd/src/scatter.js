/**
 * Scatter module.
 *
 * A grid of term and definition cards. The student clears the grid by tapping matching pairs.
 * Based on the scatter item type from Poodll MiniLesson.
 *
 * @module     mod_wordcards/scatter
 * @author     Justin Hunt - poodll.com
 * @copyright  2026 Justin Hunt (poodllsupport@gmail.com)
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define([
  'jquery',
  'core/ajax',
  'core/log',
  'mod_wordcards/a4e',
  'mod_wordcards/textfit',
  'core/templates'
], function($, Ajax, log, a4e, textFit, templates) {

  var app = {
    dryRun: false,

    //definition card display options, these match \mod_wordcards\constants
    DEF_ONLY: 0,
    IMAGE_ONLY: 1,
    DEF_AND_IMAGE: 2,

    init: function(props) {

      //pick up opts from html
      var theid = '#' + props.widgetid;
      this.dryRun = props.dryRun;
      this.nexturl = props.nexturl;
      this.modid = props.modid;
      this.scatteroptions = parseInt(props.scatteroptions, 10);
      this.isFreeMode = props.isfreemode;
      var configcontrol = $(theid).get(0);
      if (configcontrol) {
        var matchingdata = JSON.parse(configcontrol.value);
        $(theid).remove();
      } else {
        //if there is no config we might as well give up
        log.debug('No config found on page. Giving up.');
        return;
      }

      app.process(matchingdata);

      a4e.register_events();
      a4e.init_audio(props.token, props.region, props.owner, props.cloudpoodllurl);

      this.register_events();
    },

    register_events: function() {

      $('body').on('click', "#wordcards-close-results", function() {
        var url = app.nexturl.replace(/&amp;/g, '&');
        window.location.replace(url);
      });

      $('body').on('click', "#wordcards-try-again", function() {
        location.reload();
      });

      $('body').on('click', '#wordcards-start-button', function() {
        app.start();
      });

      $('body').on('click', '.wordcards-scatter-card', function() {
        app.check($(this));
      });

      $('body').on('keydown', '.wordcards-scatter-card', function(e) {
        if (e.key !== "Enter" && e.key !== " ") {
          return;
        }
        e.preventDefault();
        app.check($(this));
      });

    },

    process: function(json) {

      app.terms = json.terms;
      a4e.list_vocab("#vocab-list-inner", app.terms);

    },

    /**
     * Build the shuffled two-cards-per-term deck.
     *
     * @return {Array} the cards, in the order they will be laid out
     */
    build_cards: function() {
      var cards = [];
      $.each(app.terms, function(i, term) {
        cards.push(app.make_card(i, term, true));
        cards.push(app.make_card(i, term, false));
      });
      a4e.shuffle(cards);
      $.each(cards, function(i, card) {
        card.cardindex = i;
      });
      return cards;
    },

    /**
     * Build one card. Term cards always show the term. Definition cards show the definition,
     * the picture, or both, depending on the activity settings. When the activity is set to
     * show pictures only, but this term has no picture, we fall back to the definition text.
     *
     * @param {Number} key the index of the term this card is paired on
     * @param {Object} term the term record
     * @param {Boolean} isterm true for the term card, false for the definition card
     * @return {Object} the card data for the scatter_cards template
     */
    make_card: function(key, term, isterm) {
      var card = {
        key: key,
        isterm: isterm,
        showtext: true,
        showimage: false,
        hasboth: false,
        text: '',
        image: '',
        imagealt: ''
      };

      if (isterm) {
        card.text = term.term;
        return card;
      }

      var hasimage = !!term.image;
      card.text = term.definition;
      if (hasimage && app.scatteroptions !== app.DEF_ONLY) {
        card.showimage = true;
        card.image = term.image;
        //a picture alone tells a screen reader nothing, so describe it with the definition
        card.imagealt = app.strip_tags(term.definition);
      }
      //picture only, but only when we actually have a picture to show
      if (card.showimage && app.scatteroptions === app.IMAGE_ONLY) {
        card.showtext = false;
      }
      //the card splits its space when it carries a picture and the definition together
      card.hasboth = card.showimage && card.showtext;

      return card;
    },

    strip_tags: function(html) {
      return $('<div></div>').html(html).text().trim();
    },

    start: function() {
      app.results = [];
      app.selected = null;
      app.locked = false;
      //terms that have been part of a wrong match, they no longer count as learned
      app.wrong = {};
      //wrong pairings already reported, so exploratory tapping does not spam the server
      app.reportedfails = {};
      app.cards = app.build_cards();
      app.matchedcount = 0;

      $("#wordcards-vocab-list, #wordcards-start-button").hide();
      $("#wordcards-gameboard").show();
      $("#wordcards-time-counter").text("00:00");
      a4e.progress_dots(app.results, app.terms);

      templates.render('mod_wordcards/scatter_cards', {cards: app.cards}).then(
        function(html) {
          $("#wordcards-scatter-stage").html(html);
          var cardtext = $("#wordcards-scatter-stage .wordcards-scatter-cardtext");
          textFit(cardtext, {
            multiLine: true,
            maxFontSize: 24,
            alignHoriz: true,
            alignVert: true
          });
          return true;
        }
      ).catch(function(err) {
        log.debug('Unable to render scatter cards: ' + err);
      });

      app.timer = {
        interval: setInterval(function() {
          app.timer.update();
        }, 1000),
        count: 0,
        update: function() {
          app.timer.count++;
          $("#wordcards-time-counter").text(a4e.pretty_print_secs(app.timer.count));
        }
      };
    },

    check: function(card) {

      //while a wrong pair is shaking we ignore taps, so the board can not get out of step
      if (app.locked || card.hasClass('wordcards-scatter-matched')) {
        return;
      }

      var cardindex = parseInt(card.data('cardindex'), 10);

      //tapping the selected card again deselects it
      if (app.selected === cardindex) {
        app.set_selected(cardindex, false);
        app.selected = null;
        return;
      }

      //nothing selected yet, so this is the first card of a pair
      if (app.selected === null) {
        app.selected = cardindex;
        app.set_selected(cardindex, true);
        return;
      }

      var first = app.cards[app.selected];
      var second = app.cards[cardindex];
      app.set_selected(cardindex, true);

      if (first.key === second.key) {
        app.handle_match(app.selected, cardindex, first.key);
      } else {
        app.handle_mismatch(app.selected, cardindex, first.key, second.key);
      }
      app.selected = null;
    },

    set_selected: function(cardindex, selected) {
      var card = app.card_element(cardindex);
      card.toggleClass('wordcards-scatter-selected', selected);
      card.attr('aria-pressed', selected.toString());
    },

    card_element: function(cardindex) {
      return $("#wordcards-scatter-stage .wordcards-scatter-card[data-cardindex='" + cardindex + "']");
    },

    handle_match: function(firstindex, secondindex, key) {
      var term = app.terms[key];

      //a pair only counts as learned if it was matched without a wrong try on either of its cards
      var points = app.wrong[key] ? 0 : 1;
      if (points) {
        app.reportSuccess(term.id);
      }

      app.results.push({
        question: term.definition,
        correct: term.term,
        points: points,
        id: term.id
      });

      app.matchedcount++;
      a4e.progress_dots(app.results, app.terms);

      setTimeout(function() {
        app.card_element(firstindex).removeClass('wordcards-scatter-selected').addClass('wordcards-scatter-matched')
          .attr({'aria-pressed': 'false', 'tabindex': '-1'});
        app.card_element(secondindex).removeClass('wordcards-scatter-selected').addClass('wordcards-scatter-matched')
          .attr({'aria-pressed': 'false', 'tabindex': '-1'});
        if (app.matchedcount >= app.terms.length) {
          app.end();
        }
      }, 200);
    },

    handle_mismatch: function(firstindex, secondindex, firstkey, secondkey) {

      //both of these terms have now been got wrong, so neither can be claimed as learned
      app.wrong[firstkey] = true;
      app.wrong[secondkey] = true;

      //only report each wrong pairing once, however many times the student tries it
      var failkey = Math.min(firstkey, secondkey) + '|' + Math.max(firstkey, secondkey);
      if (!app.reportedfails[failkey]) {
        app.reportedfails[failkey] = true;
        app.reportFailure(app.terms[firstkey].id, app.terms[secondkey].id);
      }

      app.locked = true;
      var first = app.card_element(firstindex);
      var second = app.card_element(secondindex);
      first.addClass('wordcards-scatter-shake');
      second.addClass('wordcards-scatter-shake');
      setTimeout(function() {
        first.removeClass('wordcards-scatter-selected wordcards-scatter-shake').attr('aria-pressed', 'false');
        second.removeClass('wordcards-scatter-selected wordcards-scatter-shake').attr('aria-pressed', 'false');
        app.locked = false;
      }, 400);
    },

    end: function() {
      clearInterval(app.timer.interval);
      $("#wordcards-gameboard, #wordcards-start-button").hide();
      $("#wordcards-results").show();

      //template data
      var tdata = [];
      tdata['nexturl'] = this.nexturl;
      tdata['results'] = app.results;
      tdata['total'] = app.terms.length;
      tdata['totalcorrect'] = a4e.calc_total_points(app.results);
      var total_time = app.timer.count;
      if (total_time == 0) {
        tdata['prettytime'] = '00:00';
      } else {
        tdata['prettytime'] = a4e.pretty_print_secs(total_time);
      }
      templates.render('mod_wordcards/feedback', tdata).then(
        function(html) {
          $("#results-inner").html(html);
          // Add listeners for the "Add to my words" buttons.
          require(["mod_wordcards/mywords"], function(mywords) {
            mywords.initFromFeedbackPage();
          });
          return true;
        }
      ).catch(function(err) {
        log.debug('Unable to render scatter feedback: ' + err);
      });

      if (!app.isFreeMode) {
        Ajax.call([{
          methodname: 'mod_wordcards_report_step_grade',
          args: {
            modid: app.modid,
            correct: tdata['totalcorrect']
          }
        }]);
      }
    },

    reportFailure: function(term1id, term2id) {
      if (this.dryRun) {
        return;
      }

      Ajax.call([{
        methodname: 'mod_wordcards_report_failed_association',
        args: {
          term1id: term1id,
          term2id: term2id,
          isfreemode: app.isFreeMode
        }
      }]);
    },

    reportSuccess: function(termid) {
      if (this.dryRun) {
        return;
      }

      Ajax.call([{
        methodname: 'mod_wordcards_report_successful_association',
        args: {
          termid: termid,
          isfreemode: app.isFreeMode
        }
      }]);
    }
  };

  return app;

});
