define(['jquery', 'core/ajax', 'core/notification','core/modal_factory','core/str','core/modal_events', 'mod_wordcards/a4e', 'mod_wordcards/cardactions'],
    function($, Ajax, Notification,ModalFactory, str, ModalEvents, a4e, cardactions) {
  "use strict"; // jshint ;_;

  return {

    strings: {},

    init: function(opts) {

        var that = this;

        //init strings
        this.init_strings();

        //pick up opts from html
        var theid = '#' + opts['widgetid'];
        var propscontrol = $(theid).get(0);
        if (propscontrol) {
            var props = JSON.parse(propscontrol.value);
            this.props =props;
            $(theid).remove();
        } else {
            //if there is no config we might as well give up
            log.debug('No config found on page. Giving up.');
            return;
        }

            /* flashcards code start */
			var ef = $(".event_flashcards");
			var eg = $(".event_grid");

			var totalcards = $('.definition_flashcards_ul li').length;
			set_progress_info(1,totalcards);




        $('#Next').click(function (e) {
            var cr_index = $(".is-current").index() + 1;
            if(cr_index > (totalcards-1)){cr_index=0;}

            /* When user clicks the #Next button, we add to the current card a class (".next-button-slide-is-leaving", referring to 'the current slide is leaving after pressing the Next button') which triggers the move to the left and vanish CSS animation effect. */
            $('.definition_flashcards_ul li').addClass("next-button-slide-is-leaving");

            /* After the CSS animation has executed, we manage all the state-related behavior for that card by executing the "afterLeaving" function via an event delegation setTimeout of .5 seconds to get sure the CSS animation could run. Also removes the CSS class recently added for the animation.*/
            function afterLeaving() {
              $('.definition_flashcards_ul li').removeClass("is-current next-button-slide-is-leaving slide-is-visible").addClass("slide-is-hidden");
            }
            /* We do apply same process to the next slide which is entering */
            $('.definition_flashcards_ul li:eq(' + cr_index + ')').removeClass("slide-is-hidden").addClass("slide-is-visible next-button-slide-is-coming");

            function afterComing() {
              $('.definition_flashcards_ul li:eq(' + cr_index + ')').removeClass("slide-is-hidden next-button-slide-is-coming").addClass("slide-is-visible is-current");
            }

            setTimeout(afterLeaving, 500);
            setTimeout(afterComing, 500);
            set_progress_info(cr_index + 1,totalcards);
            cardactions.clearYouGlish();
            cardactions.restoreCards();



            /* Sketchy code for demo purposes only - show the restart button instead of the left arrow one, by adding a special class. */
            if (cr_index + 1 === totalcards) {
              $('#Next').addClass("isLast");
            }
            /* Sketchy code for demo purposes only - hide the left arrow in the first slide, by adding a special class. */
            if (cr_index + 1 >= 1) {
              $('#Prev').removeClass("isFirst");
            }
        });

      /* Same mechanics applied to the #Next button, but in reverse. */
        $('#Prev').click(function () {
            var cr_index = $(".is-current").index() - 1;
            if(cr_index <0){cr_index=(totalcards-1);}
            $('.definition_flashcards_ul li').addClass("prev-button-slide-is-leaving");
            function afterLeaving() {
              $('.definition_flashcards_ul li').removeClass("is-current prev-button-slide-is-leaving slide-is-visible").addClass("slide-is-hidden");
            }

                    $('.definition_flashcards_ul li:eq(' + cr_index + ')').removeClass("slide-is-hidden").addClass("slide-is-visible prev-button-slide-is-coming");
            function afterComing() {
              $('.definition_flashcards_ul li:eq(' + cr_index + ')').removeClass("slide-is-hidden prev-button-slide-is-coming").addClass("slide-is-visible is-current");
            }

            var curr_level_card = $('.curr_level_card').html();
            $('.curr_level_card').html(parseInt(curr_level_card) - 1);
            set_progress_info(cr_index + 1 ,totalcards);

            setTimeout(afterLeaving, 500);
            setTimeout(afterComing, 500);
            cardactions.clearYouGlish();
            cardactions.restoreCards();

            if (cr_index + 1 === 0) {
              $('#Prev').addClass("isFirst");
            }
        });



        function set_progress_info(index,total) {
            $(".definition_flashcards .wc_cardsprogress").text(index + ' / ' + total);
        }


		/* Definitions list and next buttons*/
        var container = $('#definitions-page-' + opts['widgetid']);
        var modid = props.modid;
        var canmanage = props.canmanage;
        var canattempt = props.canattempt;
        var btn = $('.definitions-next');

       //set up audio
       a4e.register_events();
       a4e.init_audio(props.token,props.region,props.owner);

       //set up card actions
        cardactions.init(props.youglish);

      container.on('click', '.term-seen-action', function(e) {
        e.preventDefault();

        var termNode = $(this).parents('.term').first();
        var termId = termNode.data('termid');

        //On the clicked (and visible) node add loading
        termNode.addClass('term-loading');
        Ajax.call([{
            'methodname': 'mod_wordcards_mark_as_seen',
            'args': {
              'termid': termId
            }
          }])[0].then(function(result) {
            if (!result) {
              return $.Deferred().reject();
            }

          //since we have two nodes (grid and flashcards) for a single term,
			// and the user might toggle between the grid and flashcards view we need to update both
			//so the old termNode.addClass('term-seen') is no good. it would only update one
          //  termNode.addClass('term-seen');
			$('.definition_flashcards [data-termid="' + termId + '"]').addClass('term-seen')
			$('.definition_grid [data-termid="' + termId + '"]').addClass('term-seen')
          })
          .fail(Notification.exception)
          .always(function() {
          	//remove loading from  node which loading was applied to
            termNode.removeClass('term-loading');

          });
      });

      btn.click(function(e) {
        e.preventDefault();
        var buttonhref= $(this).data('href');

        //f its not a reattempt ... proceed
        if($(this).data('action')!=='reattempt') {
            window.location.href = buttonhref;
            return;
        }

        //if its a reattempt, confirm and proceed
          ModalFactory.create({
              type: ModalFactory.types.SAVE_CANCEL,
              title: that.strings.reattempttitle,
              body: that.strings.reattemptbody
          })
          .then(function(modal) {
              modal.setSaveButtonText(that.strings.reattempt);
              var root = modal.getRoot();
              root.on(ModalEvents.save, function() {
                  window.location.href = buttonhref;
              });
              modal.show();
          });

      });

    },

    init_strings: function(){
        var that = this;
        // set up strings
        str.get_strings([
            {"key": "reattempttitle",       "component": 'mod_wordcards'},
            {"key": "reattemptbody",           "component": 'mod_wordcards'},
            {"key": "reattempt",           "component": 'mod_wordcards'}

        ]).done(function(s) {
            var i = 0;
            that.strings.reattempttitle = s[i++];
            that.strings.reattemptbody = s[i++];
            that.strings.reattempt = s[i++];
        });
    }

  }

});
