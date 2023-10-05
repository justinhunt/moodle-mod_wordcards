/**
 * mod_worcards YouGlish AMD module
 *
 * @package mod_wordcards
 * @author  Justin Hunt - poodll.com
 * *
 */

define([
  'jquery','core/log'
], function($,log) {

  var app = {
    widget: false,
    term: '',
    views:  0,
    curTrack: 0,
    totalTracks: 0,
    YG_ID: 'mod_wordcardsyouglish-widget',
    YG_WIDGET: '#mod_wordcardsyouglish-widget',
    YG_PLACEHOLDER: '.youglish-placeholder',
    YGC: {
      searchbox: 1,
      accentpanel: 2,
      title: 4,
      captions:	8,
      speedcontrols: 16,
      toggleui:	32,
      controlbuttons: 64,
      dictionary: 128,
      nearbypanel: 256,
      phoneticpanel: 512,
      draggable: 1024,
      minimizable: 2048,
      closable:	4096,
      allcaptions:	8192,
      togglelight:	16384,
      togglethumbnails:	32768
    },


    //The API will call this method when the search is done
    onFetchDone: function (event) {
      log.debug('onFetchDone');
      if (event.totalResult === 0) {
        alert("No result found");
      }else{
        app.totalTracks = event.totalResult;
        app.element.find(app.YG_PLACEHOLDER).hide();
        $(app.YG_WIDGET).show();
      }
    },

    //The API will call this method when switching to a new video.
    onVideoChange: function (event) {
      app.curTrack = event.trackNumber;
      app.views = 0;
    },

    //Player can take API calls now
    onPlayerReady: function (event) {
      log.debug('playerready');
    },
    //Player can take API calls now
    onError: function (event) {
      log.debug('error');
      log.debug('error: ' + event.code);
    },

    // The API will call this method when a caption is consumed.
    onCaptionConsumed:  function (event) {
      log.debug(app.views,'views');
      if (++app.views < 3) {
        app.widget.replay();
      }else if (app.curTrack < app.totalTracks) {
        //turn autostart on for the next video
        app.widget.autoStart = 1;
        app.widget.next();
      }
    },


    load: function (lang,term,accent,element) {
      app.term=term;
      app.element=element;

      if(!lang||lang==='false'){
        return;
      }

      log.debug('loading the term: '+ app.term);
      var thefunc = function () {
            //create the widget
            log.debug('creating the widget');
            app.element.append('<div id="'+app.YG_ID+'" class="'+app.YG_ID+'"></div>');
            app.widget = new YG.Widget(app.YG_ID, {
              width: 480,
              components: app.YGC.captions | app.YGC.dictionary | app.YGC.controlbuttons | app.YGC.toggleui | app.YGC.togglelight | app.YGC.togglethumbnails,
              autoStart: 1,
              events: {
                'onFetchDone': app.onFetchDone,
                'onVideoChange': app.onVideoChange,
                'onCaptionConsumed': app.onCaptionConsumed,
                'onPlayerReady': app.onPlayerReady,
                'onError': app.onError
              }
            });
            // process the query
            $('#' + app.YG_ID).hide();
            log.debug('first fetch of the term: '+ app.term);
            if(accent && accent!=='false') {
              app.widget.fetch(app.term, lang, accent);
            }else{
              app.widget.fetch(app.term, lang);
            }
      };

      if (typeof YG === 'undefined') {
        $.getScript('https://youglish.com/public/emb/widget.js', thefunc);
      } else if(app.widget===false) {
        thefunc();
      }else{
        log.debug('reusing the widget');
        $("#" + app.YG_ID).detach().appendTo(element);
        $('#' + app.YG_ID).hide();
        log.debug('close the previous widget');
        app.widget.close();
        log.debug('reuse fetch the term: '+ app.term);
        if(accent && accent!=='false') {
          app.widget.fetch(app.term, lang, accent);
        }else{
          app.widget.fetch(app.term, lang);
        }
      }
    },
    clear: function () {
        if(this.widget===false){return;}
        this.widget.close();
    },
    stop: function () {
        if(this.widget===false){return;}
        this.widget.pause();
    }
  }
  return app;

});