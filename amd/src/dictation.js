/**
 * Matching module.
 *
 * @package mod_wordcards
 * @author  Justin Hunt - poodll.com
 * * (based on Paul Raine's APPs 4 EFL)
 */

define([
  'jquery',
  'core/ajax',
  'core/log',
  'mod_wordcards/a4e',
  'mod_wordcards/keyboard',
  'mod_wordcards/pollyhelper',
  'core/templates'
], function($, Ajax, log, a4e, keyboard, polly, templates) {

  var app = {
    dryRun: false,
    audio: false,
    ttslanguage: 'en-US',
    init: function(props) {

      //pick up opts from html
      var theid = '#' + props.widgetid;
      this.dryRun = props.dryRun;
      this.nexturl = props.nexturl;
      var configcontrol = $(theid).get(0);
      if (configcontrol) {
        var matchingdata = JSON.parse(configcontrol.value);
        $(theid).remove();
      } else {
        //if there is no config we might as well give up
        log.debug('No config found on page. Giving up.');
        return;
      }

      polly.init(props.token, props.region, props.owner);
      app.ttslanguage = props.ttslanguage;
      app.process(matchingdata);
      a4e.register_events();
      this.register_events();
    },

    register_events: function() {

      // Get the audio element
      var aplayer = $("#dictation_player");

      $('body').on('click', "#close-results", function() {

        var total_time = a4e.calc_total_time(app.results);
        var url = app.nexturl.replace(/&amp;/g, '&') + "&localscattertime=" + total_time
        window.location.replace(url);

      });

      $('body').on('click', "#try-again", function() {
        location.reload();
      });

      $("#listen-button").click(function() {
        if (app.audio) {
          aplayer.attr('src', app.audio);
          aplayer[0].play();
        } else {
          polly.fetch_polly_url(app.tts, 'text', app.ttsvoice);
        }

      });

      //play what was returned in polly.fetch_polly_url
      polly.onnewpollyurl = function(theurl) {
        aplayer.attr('src', theurl);
        aplayer[0].play();
      };

      $('body').on('click', '#start-button', function() {
        app.start();
      });

      $('body').on('click', '#quit-button', function() {
        app.quit();
      });


    },

    process: function(json) {

      app.terms = json.terms;
      app.has_images = json.has_images;
      a4e.list_vocab("#vocab-list-inner", app.terms);

    },
    start: function() {
      app.results = [];
      a4e.shuffle(app.terms);
      app.pointer = 0;
      $("#vocab-list, #start-button").hide();
      $("#gameboard, #quit-button").show();
      $("#time-counter").text("00:00");
      app.timer = {
        interval: setInterval(function() {
          app.timer.update();
        }, 1000),
        count: 0,
        update: function() {
          app.timer.count++;
          $("#time-counter").text(a4e.pretty_print_secs(app.timer.count));
        }
      };
      app.next();
    },
    quit: function() {
      keyboard.clear();
      clearInterval(app.timer.interval);
      $("#gameboard, #quit-button").hide();
      $("#vocab-list, #start-button").show();
    },

    end: function() {
      keyboard.clear();
      clearInterval(app.timer.interval);
      $("#gameboard, #quit-button, #start-button").hide();
      $("#results").show();

      //template data
      var tdata = [];
      tdata['results'] = app.results;
      tdata['total'] = app.terms.length;
      tdata['totalcorrect'] = a4e.calc_total_points(app.results);
      var total_time = a4e.calc_total_time(app.results);
      if (total_time == 0) {
        tdata['prettytime'] = '00:00';
      } else {
        tdata['prettytime'] = a4e.pretty_print_secs(total_time);
      }
      templates.render('mod_wordcards/feedback', tdata).then(
        function(html, js) {
          $("#results-inner").html(html);
        }
      );

      var data = {
        results: app.results,
        activity: "dictation"
      };
      console.log(data);

    },


    next: function() {
      
      a4e.progress_dots(app.results, app.terms);

      $("#submitted").html("").removeClass("a4e-correct a4e-incorrect");

      keyboard.create("input", app.terms[app.pointer]['term'], app.pointer, true, function(value) {
        $("#submitted").html(app.terms[app.pointer]['term']);
        keyboard.disable();
        app.check(value);
      });

      app.tts = app.terms[app.pointer]['term'];
      
      if (app.terms[app.pointer]['ttsvoice']) {
        app.ttsvoice = app.terms[app.pointer]['ttsvoice'];
      } 
      
      else {
        app.ttsvoice = 'auto';
      }
      
      if (app.terms[app.pointer]['audio']) {
        app.audio = app.terms[app.pointer]['audio'];
      } 
      
      else {
        app.audio = false;
      }
      
      $("#listen-button").trigger("click");

    },

    check: function(selected) {
      var correct = selected.toLowerCase().trim() == app.terms[app.pointer]['term'].toLowerCase().trim();
      var points = 0;
      if (correct == true) {
        //createjs.Sound.play('correct');
        $("#submitted").addClass("a4e-correct");
        points = 1;
      } else {
        $("#submitted").addClass("a4e-incorrect");
        //createjs.Sound.play('incorrect');
      }

      //post results to server
      if (correct) {
        this.reportSuccess(app.terms[app.pointer]['id']);
      } else {
        this.reportFailure(app.terms[app.pointer]['id'], 0);
      }

      var result = {
        question: app.terms[app.pointer]['definition'],
        selected: selected,
        correct: app.terms[app.pointer]['term'],
        points: points,
        time: app.timer.count
      };
      app.timer.count = 0;
      app.results.push(result);

      if (app.pointer < app.terms.length - 1) {
        app.pointer++;
        setTimeout(function() {
          app.next();
        }, 1000)
      } else {
        app.end();
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
          term2id: term2id
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
          termid: termid
        }
      }]);
    }
  };

  return app;

});