/**
 * Word Preview Module
 *
 * @package mod_wordcards
 * @author  Justin Hunt - poodll.com
 * *
 */

define([
  'jquery',
  'core/ajax',
  'core/log',
  'core/str',
  'mod_wordcards/a4e',
  'mod_wordcards/textfit',
  'core/templates',
  'core/notification'
], function($, Ajax, log, str, a4e, textFit, templates, notification) {

  var app = {
    dryRun: false,
    termAtTop: "0",
    strings: {},
    nexturl: '',

    init: function(props) {

      //pick up opts from html
      var theid = '#' + props.widgetid;
      this.dryRun = props.dryRun;
      this.nexturl = props.nexturl;
      this.modid = props.modid;
      this.msoptions=props.msoptions;
      this.isFreeMode = props.isfreemode;
      var configcontrol = $(theid).get(0);
      if (configcontrol) {
        var appdata = JSON.parse(configcontrol.value);
        $(theid).remove();
      } else {
        //if there is no config we might as well give up
        log.debug('No config found on page. Giving up.');
        return;
      }

      //init strings
      this.init_strings();

      //set next url
      if(app.nexturl === '') {
        var currentUrl = new URL(window.location.href);
        currentUrl.searchParams.delete('practicetype');
        app.nexturl = currentUrl.toString();
      }
      log.debug("Next URL: " + app.nexturl);

      app.process(appdata);

      a4e.register_events();
      a4e.init_audio(props.token, props.region, props.owner, props.cloudpoodllurl);

      this.register_events();
    },

    init_strings: function(){
        // set up strings
        str.get_strings([
            {"key": "nowordsselected", "component": 'mod_wordcards'},
            {"key": "selectedwordstest", "component": 'mod_wordcards'},
            {"key": "continue", "component": 'core'},
        ]).done(function(s) {
            var i = 0;
            app.strings.nowordsselected = s[i++];
            app.strings.selectedwordstest = s[i++];
            app.strings.continue = s[i++];
        });
    },

    register_events: function() {

      $('body').on('click', "#wordcards-close-results", function(e) {
        e.preventDefault();
        var total_time = app.timer.count;
        log.debug("app.nexturl" + app.nexturl);
        var url = app.nexturl.replace(/&amp;/g, '&') + "&localscattertime=" + total_time
        window.location.replace(url);
      });

      $('body').on('click', "#wordcards-try-again", function() {
        location.reload();
      });

      $("body").on('click', '.a4e-distractor', function(e) {
        app.check($(this).data('correct'), this);
      });

      $('body').on('click', '#wordcards-start-button', function(e) {
        e.preventDefault();
        //collect terms that were selected
        var selectedcheckboxes = $("#selfselect-inner .wc_selfselector").find('input:checked');
        app.selectedterms= [];
        for (var i = 0; i < selectedcheckboxes.length; i++) {
          var termid = $(selectedcheckboxes[i]).data('termid');
          // If its already learned, we do not need to test it.
          // We only test the self selected ones (and  not ye learned ones)
          var learned = $(selectedcheckboxes[i]).data('learned');
          if(learned==1 || learned=='true'){
            continue;
          }
          // Push the term into the selected terms array
          var term = app.terms.filter(function(t) {
            return t.id == termid;
          })[0];
          app.selectedterms.push(term);
        }
        log.debug("Next URL confirm: " + app.nexturl);
        //if some words are selected start test. If not confirm and redirect
        if(app.selectedterms.length === 0){
          notification.confirm(app.strings.continue, 
            app.strings.nowordsselected,
            app.strings.continue,'',
            function(){
              // In order to set the state for the current step complete
              // We need at least one successful association.
              // Though its not ideal we pass one in.
              var aterm = app.terms[0];
              app.reportSuccess(aterm.id);

              //Then we set the next url and go
              var theurl = app.nexturl.replace(/&amp;/g, '&');
              window.location.href=theurl;
            });
          return;
        }else{
          notification.confirm(app.strings.continue, 
            app.strings.selectedwordstest,
            app.strings.continue,'',
            app.start);
          return;
        }
        
      });

    },

    process: function(appdata) {

      app.terms = appdata.terms;
      a4e.list_selfselect("#selfselect-inner", appdata.terms);

    },
    start: function() {
      app.results = [];
      a4e.shuffle(app.selectedterms);
      app.pointer = 0;
      $(".selfselect-container, #wordcards-start-button").hide();
      $("#wordcards-gameboard").show();
      $("#wordcards-time-counter").text("00:00");
      app.timer = {
        interval: setInterval(function() {
          app.timer.update();
        }, 1000),
        count: 0,
        update: function() {
          app.timer.count++;
          $("#wordcards-time-counter").text(a4e.pretty_print_secs(app.timer.count));
        }
      }
      app.next();
    },
    quit: function() {
      clearInterval(app.timer.interval);
      $("#wordcards-gameboard").hide();
      $("#wordcards-vocab-list, #wordcards-start-button").show();
    },

    end: function() {
      clearInterval(app.timer.interval);
      $("#wordcards-gameboard, #wordcards-start-button").hide();
      $("#wordcards-results").show();

      //template data
      var tdata = [];
      tdata['nexturl'] = this.nexturl;
      tdata['results'] = app.results;
      tdata['total'] = app.selectedterms.length;
      tdata['totalcorrect'] = a4e.calc_total_points(app.results);
      var total_time = app.timer.count;
      if (total_time == 0) {
        tdata['prettytime'] = '00:00';
      } else {
        tdata['prettytime'] = a4e.pretty_print_secs(total_time);
      }
      templates.render('mod_wordcards/wordpreview_feedback', tdata).then(
        function(html, js) {
          $("#results-inner").html(html);
          // Add listeners for the "Add to my words" buttons.
          require(["mod_wordcards/mywords"], function(mywords) {
            mywords.initFromFeedbackPage();
          });
        }
      );
      // HACK - Post a 100% grade. There should be no grade
      if (!app.isFreeMode) {
        var termscount = app.terms.length;
        Ajax.call([{
          methodname: 'mod_wordcards_report_step_grade',
          args: {
            modid: app.modid,
            correct: termscount
          }
        }]);
      }
    },

    next: function() {
      a4e.progress_dots(app.results, app.selectedterms);
      var templateopts=app.selectedterms[app.pointer];
      if(app.msoptions===app.termAtTop){
        templateopts.termisheader=true;
      }
      templates.render('mod_wordcards/definition_as_header', templateopts).then(
          function(html, js) {
            $("#wordcards-question").html(html);
            //do text fit
            var defheaders = $(".definition-as-header");
            textFit(defheaders, {
              multiLine: true,
              maxFontSize: 50,
              alignHoriz: true,
              alignVert: true
            });
          }
      );

      $("#wordcards-input").html(app.get_distractors());

    },

    check: function(correct, clicked) {
      var points = 0;
      if (correct == true) {
        //createjs.Sound.play('correct');
        points = 1;
      } else {
        //createjs.Sound.play('incorrect');
      }
      $(".a4e-distractor").css('pointer-events', 'none');
      
      var result = {
        question: app.selectedterms[app.pointer]['definition'],
        selected: $(clicked).text(),
        correct: app.selectedterms[app.pointer]['term'],
        points: points,
        id: app.selectedterms[app.pointer]['id']
      };
      
      app.results.push(result);

      var background = correct == true ? 'a4e-correct' : 'a4e-incorrect';
      $(clicked).addClass(background).append("<i style='color:" + (correct ? 'green' : 'red') + ";margin-left:5px;' class='fa fa-" + (correct ? 'check' : 'times') + "'></i>").parent().addClass('a4e-click-disabled');

      if (!correct) {
        $(".a4e-distractor[data-correct='true']").addClass('a4e-correct').append("<i style='color:green;margin-left:5px;' class='fa fa-check'></i>");
      }

      //post results to server
      if (correct) {
        this.reportSuccess(app.selectedterms[app.pointer]['id']);
      }

      app.pointer++;
      if (!correct) {
        setTimeout(function() {
          if (app.pointer < app.selectedterms.length) {
            app.next();
          } else {
            app.end();
          }
        }, 1500)
      } else {
        setTimeout(function() {
          if (app.pointer < app.selectedterms.length) {
            app.next();
          } else {
            app.end();
          }
        }, 1000)
      }
    },

    get_distractors: function() {
      var distractors = app.terms.slice(0);
      //find pointer in terms of current term in selected terms
      var termspointer = app.terms.findIndex(function(t) {
        return t.id === app.selectedterms[app.pointer].id;
      });
      var answer = app.terms[termspointer]['term'];
      distractors.splice(termspointer, 1);
      a4e.shuffle(distractors);
      distractors = distractors.slice(0, 4);
      distractors.push(app.terms[termspointer]);
      a4e.shuffle(distractors);
      var options = [];
      $.each(distractors, function(i, o) {
        var is_correct = o['term'] == answer;
        var term_id = o['id'];
        //depending on options  show option label as term or def
        if(app.msoptions===app.termAtTop){
          var label= o['definition'];
        }else{
          var label= o['term'];
        }
        options.push('<li data-id="' + term_id + '" data-correct="' + is_correct.toString() + '" class="list-group-item a4e-distractor a4e-noselect">' + label + '</li>');
      });
      var code = '<ul class="list-group a4e-distractors">' + options.join('') + '</ul>';
      return code;
    },

    reportSuccess: function(termid) {
      if (this.dryRun) {
        return;
      }

      Ajax.call([{
        methodname: 'mod_wordcards_report_successful_learnclaim',
        args: {
          termid: termid
        }
      }]);
    }
  };

  return app;

});