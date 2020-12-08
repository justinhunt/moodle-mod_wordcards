define("mod_wordcards/definitions", ["jquery", "core/ajax", "core/notification", "core/modal_factory", "core/str", "core/modal_events", "mod_wordcards/a4e"], function (a, b, c, d, e, f, g) {
	"use strict";
	return {
		strings: {},
		init: function init(e) {
			var i = this;
			this.init_strings();
			var j = "#" + e.widgetid,
				k = a(j).get(0);
			if (k) {
				var l = JSON.parse(k.value);
				this.props = l;
				a(j).remove()
			} else {
				log.debug("No config found on page. Giving up.");
				return
			}
			/* flashcards code start */
			var ef = a(".event_flashcards");
			var eg = a(".event_grid");

			$('.ProgressBar-step').on('click', function () {
				if ($(this).hasClass("is-current")) {
					return false;
				}
				a('.ProgressBar-step').removeClass("is-current").removeClass('is-complete');
				a(this).addClass("is-current");
				var str = $(this).index();
				if (str > 0) {
					for (var i = 0; i <= str; i++) {
						a('.ProgressBar')
							.find('li:eq(' + i + ')')
							.addClass('is-complete');
					}
				}
				a('.definition_flashcards_ul li').slideUp(300);
				a('.definition_flashcards_ul')
					.find('li:eq(' + str + ')')
					.slideDown(300);
				a('.is-current').removeClass('is-complete');
				check_prev_level();
				check_next_level();

			});
			if (window.matchMedia("(max-width: 767px)").matches) {

				var definition_flashcards_ul = a('.definition_flashcards_ul li').length;
				a('.wrapper_pr').append('<div class="mb_nav"><span class="curr_level_card">1</span> / <span class="tot_level_card">' + definition_flashcards_ul + '</span></div>');
				a('.ProgressBar').hide();
			}
			a(".definition_flashcards_ul li:gt(0)").hide();
			var $bar = a(".ProgressBar");
			$bar.children("li:first").addClass('is-current');
			check_prev_level();
			check_next_level();
			ef.click(function (d) {
				d.preventDefault();
				a('.definition_flashcards').show();
				a('.definition_grid').hide();
			});
			eg.click(function (d) {
				d.preventDefault();
				a('.definition_flashcards').hide();
				a('.definition_grid').show();
			});

			function check_prev_level() {

				var $bar = a(".ProgressBar");
				if ($bar.children("li:first").hasClass('is-current') === true) {
					a('#Prev').attr('disabled', 'disabled').addClass('add_opacity_level');
					return true;
				}

				a('#Prev').removeAttr('disabled');
				a('#Prev').removeAttr('disabled').removeClass('add_opacity_level');
			}

			a('#Next').click(function () {
				var cr_index = a(".is-current").index() + 1;
				$('.definition_flashcards_ul li').slideUp(300);
				$('.definition_flashcards_ul li:eq(' + cr_index + ')').slideDown(300);
				var curr_level_card = a('.curr_level_card').html();
				$('.curr_level_card').html(parseInt(curr_level_card) + 1);

				var $bar = a(".ProgressBar");
				if ($bar.children(".is-current").length > 0) {
					$bar.children(".is-current").removeClass("is-current").addClass("is-complete").next().addClass("is-current");
				} else {
					$bar.children().first().addClass("is-current");
				}
				check_prev_level();
				check_next_level();
			});

			a('#Prev').click(function () {
				var cr_index = $(".is-current").index() - 1;
				a('.definition_flashcards_ul li').slideUp(300);
				a('.definition_flashcards_ul li:eq(' + cr_index + ')').slideDown(300);
				var curr_level_card = a('.curr_level_card').html();
				a('.curr_level_card').html(parseInt(curr_level_card) - 1);
				var $bar = $(".ProgressBar");
				if ($bar.children(".is-current").length > 0) {
					$bar.children(".is-current").removeClass("is-current").prev().removeClass("is-complete").addClass("is-current");
				} else {
					$bar.children(".is-complete").last().removeClass("is-complete").addClass("is-current");
				}
				check_prev_level();
				check_next_level();
			});

			function check_next_level() {
				var $bar = a(".ProgressBar");
				if ($bar.children("li:last").hasClass('is-current') === true) {
					a('#Next').attr('disabled', 'disabled').addClass('add_opacity_level');
					return true;
				}
				a('#Next').removeAttr('disabled').removeClass('add_opacity_level');
			}
			/* flashcards code end */
			var m = a("#definitions-page-" + e.widgetid),
				n = l.modid,
				o = l.canmanage,
				p = l.canattempt,
				q = m.find(".definitions-next");
			g.register_events();
			g.init_audio(l.token, l.region, l.owner);

			function h() {
				return m.find(".term").length === m.find(".term.term-seen").length
			}
			m.on("click", ".term-seen-action", function (d) {
				d.preventDefault();
				var e = a(this).parents(".term").first(),
					f = e.data("termid");
				e.addClass("term-loading");

				b.call([{
					methodname: "mod_wordcards_mark_as_seen",
					args: {
						termid: f
					}
				}])[0].then(function (b) {
					if (!b) {
						return a.Deferred().reject()
					}
					//e.addClass("term-seen")
					$('.definition_flashcards [data-termid="' + f + '"]').addClass('term-seen')
					$('.definition_grid [data-termid="' + f + '"]').addClass('term-seen')

				}).fail(c.exception).always(function () {
					e.removeClass("term-loading");
					if (h()) {
						q.prop("disabled", !1)
					}
				})
			});
			if (!h() && !o) {
				q.prop("disabled", !0)
			}
			q.click(function (b) {
				b.preventDefault();
				var c = a(this).data("href");
				if ("reattempt" !== a(this).data("action")) {
					window.location.href = c;
					return
				}
				d.create({
					type: d.types.SAVE_CANCEL,
					title: i.strings.reattempttitle,
					body: i.strings.reattemptbody
				}).then(function (a) {
					a.setSaveButtonText(i.strings.reattempt);
					var b = a.getRoot();
					b.on(f.save, function () {
						window.location.href = c
					});
					a.show()
				})
			})
		},
		init_strings: function init_strings() {
			var a = this;
			e.get_strings([{
				key: "reattempttitle",
				component: "mod_wordcards"
			}, {
				key: "reattemptbody",
				component: "mod_wordcards"
			}, {
				key: "reattempt",
				component: "mod_wordcards"
			}]).done(function (b) {
				var c = 0;
				a.strings.reattempttitle = b[c++];
				a.strings.reattemptbody = b[c++];
				a.strings.reattempt = b[c++]
			})
		}
	}
});

//# sourceMappingURL=definitions.min.js.map