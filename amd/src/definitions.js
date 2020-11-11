define(['jquery', 'core/ajax', 'core/notification','core/modal_factory','core/str','core/modal_events', 'mod_wordcards/a4e'],
    function($, Ajax, Notification,ModalFactory, str, ModalEvents, a4e) {
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

      var container = $('#definitions-page-' + opts['widgetid']),
        modid = props.modid,
        canmanage = props.canmanage,
        canattempt = props.canattempt,
        btn = container.find('.definitions-next');

       //set up audio
       a4e.register_events();
       a4e.init_audio(props.token,props.region,props.owner);

      function seenAll() {
        return container.find('.term').length === container.find('.term.term-seen').length
      }

      container.on('click', '.term-seen-action', function(e) {
        e.preventDefault();

        var termNode = $(this).parents('.term').first(),
          termId = termNode.data('termid');

        // TODO Ajax.
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
            termNode.addClass('term-seen');
          })
          .fail(Notification.exception)
          .always(function() {
            termNode.removeClass('term-loading');
            if (seenAll()) {
              btn.prop('disabled', false);
            }
          });
      });

      // Teachers can jump to the next steps.
      if (!seenAll() && !canmanage) {
        btn.prop('disabled', true);
      }

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