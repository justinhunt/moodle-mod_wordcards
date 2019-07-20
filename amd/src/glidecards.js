/**
 * GlideCards module.
 *
 * @package mod_wordcards
 * @author  Justin Hunt - Poodll.com
 */

define([
    'jquery',
    'core/ajax',
    'mod_wordcards/glide'
], function($, Ajax, glide) {

return {

    opts: null,

    init: function(config){
        //pick up opts from html
        var theid='#' + config['id'];
        var configcontrol = $(theid).get(0);
        if(configcontrol){
            this.opts = JSON.parse(configcontrol.value);
            $(theid).remove();
        }else{
            //if there is no config we might as well give up
            log.debug('Glidecards js: No config found on page. Giving up.');
            return;
        }

        //register the controls
        this.register_controls();

        //register events
        this.register_controls();
    },

    register_controls: function(){

    },
    register_events: function(){

    }


};//end of return

});
