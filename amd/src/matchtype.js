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
    'mod_wordcards/keyboard'
], function($, Ajax, log, a4e, keyboard) {

    var app = {
        dryRun: false,
        init: function(props){

            //pick up opts from html
            var theid = '#' + props.widgetid;
            this.dryRun=props.dryRun;
            var configcontrol = $(theid).get(0);
            if(configcontrol){
                var matchingdata = JSON.parse(configcontrol.value);
                $(theid).remove();
            }else{
                //if there is no config we might as well give up
                log.debug('No config found on page. Giving up.');
                return;
            }

            app.process(matchingdata);

            a4e.register_events();

            this.register_events();
        },

        register_events: function(){


            $("#next-button").on("click",function(){
                app.next();
            });

            $("#close-results").click(function(){
                $("#results").hide();
                $("#start-button, #vocab-list").show();
            });


            $("#start-button").click(function(){
                app.start();
            });

            $("#quit-button").click(function(){
                app.quit();
            });
        },

        process:function(json){

            app.terms=json.terms;
            app.has_images=json.has_images;
            a4e.list_quizlet_vocab("#vocab-list",app.terms);

        },
        start:function(){
            app.results=[];
            a4e.shuffle(app.terms);
            app.pointer=0;
            $("#vocab-list, #start-button").hide();
            $("#gameboard, #quit-button").show();
            $("#time-counter").text("00:00");
            $("#progress-correct").css('width','0%');
            $("#progress-incorrect").css('width','0%');
            app.timer={
                interval:setInterval(function(){ app.timer.update(); }, 1000),
                count:0,
                update:function(){
                    app.timer.count++;
                    $("#time-counter").text(a4e.pretty_print_secs(app.timer.count));
                }
            }
            app.next();
        },
        quit:function(){
            keyboard.clear();
            clearInterval(app.timer.interval);
            $("#gameboard, #quit-button").hide();
            $("#vocab-list, #start-button").show();
        },
        end:function(){
            keyboard.clear();
            clearInterval(app.timer.interval);
            $("#gameboard, #quit-button, #start-button").hide();
            $("#results").show();
            var code=a4e.basic_feedback(app.results);
            code+=a4e.detailed_feedback(app.results);
            $("#results-inner").html(code);

            var data={
                results:app.results,
                activity:"match_type"
            };

            console.log(data);

        },
        next:function(){

            $("#next-button").hide();
            $("#submitted").html("").removeClass("a4e-correct a4e-incorrect");

            keyboard.create("input",app.terms[app.pointer]['term'],app.pointer,true,function(value){
                $("#submitted").html(app.terms[app.pointer]['term']);
                keyboard.disable();
                app.check(value);
            });

            $("#question-counter").text((app.pointer+1)+"/"+app.terms.length);

            var progress={
                correct:app.results.filter(function(e){return e.points>0}).length/app.terms.length*100,
                incorrect:app.results.filter(function(e){return e.points==0}).length/app.terms.length*100
            }

            $("#progress-correct").css('width',progress.correct+'%');
            $("#progress-incorrect").css('width',progress.incorrect+'%');
            $("#question").html("");

            if(app.terms[app.pointer]['definition']!=="" && app.terms[app.pointer]['term']!==""){
                if(app.terms[app.pointer].image!==null){
                    $("#question").html("<img style='height:200px;width:auto;' class='center-block img-responsive img-thumbnail' src='"+app.terms[app.pointer].image.url+"'><br/>");
                }
                else if(app.has_images && app.terms[app.pointer].image==null){
                    $("#question").html("<img style='height:200px;width:auto;' class='center-block img-responsive img-thumbnail' src='/images/no-image.png'><br/>");
                }
                $("#question").append("<strong>"+app.terms[app.pointer]['definition']+"</strong>");
            }

            else if(app.terms[app.pointer].image!==null){
                $("#question").html("<img class='center-block img-responsive img-thumbnail' src='"+app.terms[app.pointer].image.url+"'>");
            }

            else{
                a4e.alert("Could not generate a test with these settings!","error");
                app.end();
            }

        },

        check:function(selected){
            var correct=selected.toLowerCase().trim()==app.terms[app.pointer]['term'].toLowerCase().trim();
            var points=0;
            if(correct==true){
                //createjs.Sound.play('correct');
                $("#submitted").addClass("a4e-correct");
                points=1;
            }
            else{
                $("#submitted").addClass("a4e-incorrect");
                //createjs.Sound.play('incorrect');
            }

            //post results to server
            if(correct){
                this.reportSuccess(app.terms[app.pointer]['id']);
            }else{
                this.reportFailure(app.terms[app.pointer]['id'],0);
            }

            var result={
                question:app.terms[app.pointer]['definition'],
                selected:selected,
                correct:app.terms[app.pointer]['term'],
                points: points,
                time: app.timer.count
            };
            app.timer.count=0;
            app.results.push(result);

            if(app.pointer<app.terms.length-1){
                app.pointer++;
                if(!correct){
                    $("#next-button").show();
                }
                else{
                    setTimeout(function(){
                        $("#next-button").trigger('click');
                    },1000)
                }
            }

            else{
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
