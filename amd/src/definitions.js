define(['jquery', 'core/ajax', 'core/notification', 'mod_wordcards/a4e'], function($, Ajax, Notification, a4e) {
  "use strict"; // jshint ;_;

  return {

    init: function(opts) {

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
        btn = container.find('.continue-button');

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
        termNode.addClass('term-loading')
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
        location.href = $(this).data('href');
      });

    }

  }

});