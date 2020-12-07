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
					$('.definition_flashcards [data-termid="'+f+'"]').addClass('term-seen')
					$('.definition_grid [data-termid="'+f+'"]').addClass('term-seen')
					
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