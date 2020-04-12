define(['jquery', 'core/ajax', 'core/notification'], function($, Ajax, Notification) {
  "use strict"; // jshint ;_;

  return {

    init: function(opts) {

      var container = $('#definitions-page-' + opts['uniqid']),
        modid = opts['modid'],
        canmanage = opts['canmanage'],
        btn = container.find('.continue-button');

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