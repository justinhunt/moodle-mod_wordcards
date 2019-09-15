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
    'mod_wordcards/glidecards',
    'mod_wordcards/cloudpoodllloader',
    'core/templates'
], function($, Ajax, log, a4e,glidecards,cloudpoodll, templates) {

    var app = {
        pointer: 1,
        whatheard: null,
        jsondata: null,
        glider: null,
        dryRun: false,
        controls: {},
        init: function(props){

            //pick up opts from html
            var theid = '#' + props.widgetid;
            this.dryRun=props.dryRun;
            this.nexturl=props.nexturl;
            var jsondata = $(theid).get(0);
            if(jsondata){
                var jsondata = JSON.parse(jsondata.value);
                $(theid).remove();
            }else{
                //if there is no config we might as well give up
                log.debug('No config found on page. Giving up.');
                return;
            }
            app.jsondata = jsondata;

            app.process(jsondata);
            app.whatheard = $('#submitted'); //$('#speechcards_whatheard');
            a4e.register_events();
            this.init_controls();
            this.register_events();
        },
        init_controls: function(){
            app.controls.close_results = $("#close-results");
            app.controls.results = $("#results");
            app.controls.vocab_list = $("#vocab-list");
            app.controls.question_counter = $("#question-counter");
            app.controls.quit_button = $("#quit-button");
            app.controls.the_list = $("#speechcards_thelist");
            app.controls.gameboard = $("#gameboard");
            app.controls.time_counter = $("#time-counter");
            app.controls.progress_correct = $("#progress-correct");
            app.controls.progress_incorrect = $("#progress-incorrect");
            app.controls.prev_button = $(".wordcards-speechcards_prevbutton");
            app.controls.next_button = $(".wordcards-speechcards_nextbutton");
        },
        do_next: function() {
            if(!app.is_end()) {
                app.whatheard.removeClass('wordcards-speechcards_gotit');
                app.whatheard.text('........');
                app.glider.go('>');
                app.update_header();
            }else{
                app.do_end();
            }
        },
        do_prev: function() {
            app.whatheard.removeClass('wordcards-speechcards_gotit');
            app.whatheard.text('........');
            app.glider.go('<');
            app.update_header();
        },

        register_events: function(){

            app.controls.prev_button.click(function(){
                app.do_prev();
                //if we have a result already for this result, display it
                app.update_whatheard();
            });
            app.controls.next_button.click(function(){

                //user has given up tryint pronounce it,update word as failed
                var failedword =app.terms[app.pointer-1].term;
                app.check(false,failedword);
                app.do_next();

                //if we have a result already for this result, display it
                app.update_whatheard();
            });

            $('body').on('click',"#close-results",function(){
                //try again with this one
               // app.controls.results.hide();
               // app.controls.vocab_list.show();

                //"finish" with this one
                var total_time=a4e.calc_total_time(app.results);
                window.location.replace(app.nexturl.replace('&amp;','&') + "&localscattertime=" + total_time);
            });

            $('body').on('click','#start-button',function(){
                app.start();
            });


            app.controls.quit_button.click(function(){
                app.quit();
            });
        },

        process:function(jsondata){

            app.terms=jsondata.terms;
            app.has_images=jsondata.has_images;
            a4e.list_vocab("#vocab-list-inner",app.terms);

            //init components
            this.initComponents();

        },

        initCards: function(){
            $.getScript('https://cdn.jsdelivr.net/npm/glidejs@2.1.0/dist/glide.min.js').done(function() {

                function setPointer(newpointer){
                    app.pointer=newpointer;
                }

                //add speechcards
                var li_template = "<li class='glide__slide'><div class='wordcards-poodllspeechcards_box'>@thetext@</div></li>";
                var thelist = app.controls.the_list;

                $.each(app.jsondata.terms, function (index,card) {
                    thelist.append(li_template.replace('@thetext@', card.term))
                });

                app.glider = $("#speechcards_glide").glide({
                    type: "carousel",
                    autoplay: false,
                    afterTransition: function (data) {
                        setPointer(data.index);
                    },
                    afterInit: function (data) {
                        setPointer(data.index);
                    },
                }).data('glide_api');
            });
        },

        initComponents: function(){

            //The logic here is that on correct we transition.
            //on incorrect we do not. A subsequent nav button click then doesnt need to post a result
            var theCallback = function(message){
                console.log(message);
                switch(message.type){
                    case 'speech':
                        var speechtext = message.capturedspeech;
                        app.whatheard.text(speechtext);
                        var cleanspeechtext = app.cleanText(speechtext);
                        if(cleanspeechtext===app.terms[app.pointer-1].term){
                            app.whatheard.addClass('wordcards-speechcards_gotit');
                            app.check(true,cleanspeechtext);
                            if(app.is_end()){
                                app.update_header();
                                setTimeout(function(){
                                    app.do_end();
                                },700);
                            }else{
                                setTimeout(function(){
                                    app.do_next();
                                },700);
                            }

                        }else{
                            //we wont send false results until user gives up and clicks next
                            //app.check(false,speechtext);
                        }
                }
            };
            cloudpoodll.init('speechcards_pushrecorder',theCallback);
        },

        cleanText: function(text){
            return text.toLowerCase().replace(/[^\w\s]|_/g, "").replace(/\s+/g, " ").trim();
        },

        start:function(){
            app.results=[];
            a4e.shuffle(app.terms);
            app.controls.vocab_list.hide();
            app.controls.gameboard.show();
            app.controls.quit_button.show();
            app.controls.time_counter.text("00:00");
            app.controls.progress_correct.css('width','0%');
            app.controls.progress_incorrect.css('width','0%');
            app.whatheard.text('........');
            app.timer={
                interval:setInterval(function(){ app.timer.update(); }, 1000),
                count:0,
                update:function(){
                    app.timer.count++;
                    app.controls.time_counter.text(a4e.pretty_print_secs(app.timer.count));
                }
            }
            app.update_header();
            this.initCards();
        },
        quit:function(){
            clearInterval(app.timer.interval);
            app.controls.gameboard.hide();
            app.controls.quit_button.hide();
            app.controls.vocab_list.show();
        },

        //no longer used
        do_end_jump:function(){
            clearInterval(app.timer.interval);
            app.controls.gameboard.hide();
            app.controls.quit_button.hide();

            var total_time=0;
            $.each(app.results,function(i,o){
                if(o.time!=null){
                    total_time+=o.time;
                }
            });
            window.location.replace(this.nexturl.replace('&amp;','&') + "&localscattertime=" + total_time);
        },

        do_end:function(){
            clearInterval(app.timer.interval);
            $("#gameboard, #quit-button, #start-button").hide();
            $("#results").show();

            //template data
            var tdata=[];
            tdata['results']=app.results;
            tdata['total']=app.terms.length;
            tdata['totalcorrect']=a4e.calc_total_points(app.results);
            var total_time=a4e.calc_total_time(app.results);
            if(total_time==0){
                tdata['prettytime']='00:00';
            }else{
                tdata['prettytime']=a4e.pretty_print_secs(total_time);
            }
            templates.render('mod_wordcards/feedback',tdata).then(
                function(html,js){
                    $("#results-inner").html(html);
                }
            );

            var data={
                results:app.results,
                activity:"speechcards"
            };
            console.log(data);
        },

        is_end:function(){
            if(app.pointer<app.terms.length) {
                return false;
            }else{
                return true;
            }
        },

        update_whatheard: function(){
            $.each(app.results,function(result){
                if(app.pointer===result.pointer){
                    app.whatheard.text(result.selected) ;
                    if(app.cleanText(result.selected)===app.terms[app.pointer-1].term) {
                        app.whatheard.addClass('wordcards-speechcards_gotit');
                    }
                }

            });
        },

        update_header: function(){

            var progress={
                correct:app.results.filter(function(e){return e.points>0}).length/app.terms.length*100,
                incorrect:app.results.filter(function(e){return e.points==0}).length/app.terms.length*100
            }

            app.controls.progress_correct.css('width',progress.correct+'%');
            app.controls.progress_incorrect.css('width',progress.incorrect+'%');

            app.controls.question_counter.text((app.pointer)+"/"+app.terms.length);

          //this will display distractors which we do not need
            //  $("#input").html(app.get_distractors());

        },

        check:function(correct,spokenwords){
            var points=1;
            if(correct==true){
                //createjs.Sound.play('correct');
                points=1;
            }
            else{
                points=0;
                //createjs.Sound.play('incorrect');
            }
            $(".a4e-distractor").css('pointer-events','none');
            var result={
                pointer: app.pointer,
                question:app.terms[app.pointer-1]['definition'],
                selected:spokenwords,
                correct:app.terms[app.pointer-1]['term'],
                points: points,
                time: app.timer.count
            };
            app.timer.count=0;
            $.each(app.results,(function(result){
                if(app.pointer===result.pointer){
                    //something here to remove the old result
                }
            }));
            //finally add our result to the results
            app.results.push(result);

            //post results to server
            if(correct){
                this.reportSuccess(app.terms[app.pointer-1]['id']);
            }else{
                this.reportFailure(app.terms[app.pointer-1]['id'],-1);
            }

        },

        get_distractors:function(){
            var distractors=app.terms.slice(0);
            var answer=app.terms[app.pointer-1]['term'];
            distractors.splice(app.pointer-1,1);
            a4e.shuffle(distractors);
            distractors=distractors.slice(0,4);
            distractors.push(app.terms[app.pointer-1]);
            a4e.shuffle(distractors);
            var options=[];
            $.each(distractors,function(i,o){
                var is_correct=o['term']==answer;
                var term_id = o['id'];
                options.push('<li data-id="' + term_id +'" data-correct="'+is_correct.toString()+'" class="list-group-item a4e-distractor a4e-noselect">'+o['term']+'</li>');
            });
            var code='<ul class="list-group a4e-distractors">'+options.join('')+'</ul>';
            return code;
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
