(function ($) {
	"use strict";

	function overlay(label, pct) {
		var $el = $("#qpedia-scan-overlay");
		if (!$el.length) {
			$el = $(
				'<div id="qpedia-scan-overlay" class="qpedia-scan-overlay"><div class="inner"><p class="qpedia-scan-label"></p><div class="qpedia-progress"><span></span></div><p class="qpedia-scan-pct"></p></div></div>'
			);
			$("body").append($el);
		}
		$el.find(".qpedia-scan-label").text(label || (window.QpediaSEO && QpediaSEO.i18n.scanning) || "");
		$el.find(".qpedia-progress > span").css("width", (pct || 0) + "%");
		$el.find(".qpedia-scan-pct").text((pct || 0) + "%");
		$el.show();
	}

	function hideOverlay() {
		$("#qpedia-scan-overlay").remove();
	}

	function post(action, extra) {
		return $.post(QpediaSEO.ajax, $.extend({ action: action, nonce: QpediaSEO.nonce }, extra || {}));
	}

	function runBatches() {
		post("qpedia_scan_batch")
			.done(function (res) {
				if (!res || !res.success) {
					hideOverlay();
					window.alert(QpediaSEO.i18n.scanError);
					return;
				}
				var d = res.data || {};
				overlay(d.message || QpediaSEO.i18n.scanning, d.percent || 0);
				if (d.done) {
					overlay(QpediaSEO.i18n.scanComplete, 100);
					window.setTimeout(function () {
						window.location.reload();
					}, 400);
					return;
				}
				runBatches();
			})
			.fail(function () {
				hideOverlay();
				window.alert(QpediaSEO.i18n.scanError);
			});
	}

	$(document).on("click", "#qpedia-start-scan", function (e) {
		e.preventDefault();
		var msg = $("#qpedia-start-scan").text().indexOf("اسکن") === -1 && QpediaSEO.i18n.confirmRescan ? QpediaSEO.i18n.confirmScan : QpediaSEO.i18n.confirmScan;
		if (!window.confirm(msg)) {
			return;
		}
		overlay(QpediaSEO.i18n.scanning, 0);
		post("qpedia_scan_start")
			.done(function (res) {
				if (!res || !res.success) {
					hideOverlay();
					window.alert(QpediaSEO.i18n.scanError);
					return;
				}
				runBatches();
			})
			.fail(function () {
				hideOverlay();
				window.alert(QpediaSEO.i18n.scanError);
			});
	});

	$(document).on("click", "#qpedia-export-zip", function (e) {
		e.preventDefault();
		var $st = $("#qpedia-export-status");
		$st.text(QpediaSEO.i18n.exporting);
		post("qpedia_export_report")
			.done(function (res) {
				if (res && res.success && res.data && res.data.url) {
					window.location = res.data.url;
					$st.text("");
					return;
				}
				$st.text(QpediaSEO.i18n.exportError);
			})
			.fail(function () {
				$st.text(QpediaSEO.i18n.exportError);
			});
	});

	$(document).on("click", "#qpedia-reanalyze", function (e) {
		e.preventDefault();
		var id = $(this).data("post-id");
		var $box = $(this).closest(".qpedia-metabox");
		$box.find(".qpedia-reanalyze-status").text(QpediaSEO.i18n.reanalyzing);
		post("qpedia_reanalyze_post", { post_id: id })
			.done(function (res) {
				if (res && res.success && res.data) {
					$box.find(".qpedia-score-num").text(res.data.score);
					$box.find(".qpedia-score-grade").text(res.data.grade);
					$box.find(".bar > span").css("width", res.data.score + "%");
					$box.find(".qpedia-reanalyze-status").text(QpediaSEO.i18n.reanalyzeOk);
					return;
				}
				$box.find(".qpedia-reanalyze-status").text(QpediaSEO.i18n.reanalyzeError);
			})
			.fail(function () {
				$box.find(".qpedia-reanalyze-status").text(QpediaSEO.i18n.reanalyzeError);
			});
	});

	$(document).on("click", "#qpedia-clear-cache", function (e) {
		e.preventDefault();
		if (!window.confirm(QpediaSEO.i18n.confirmCache)) {
			return;
		}
		post("qpedia_clear_cache").done(function () {
			window.alert(QpediaSEO.i18n.cacheCleared);
			window.location.reload();
		});
	});

	$(document).on("submit", "#qpedia-settings-form", function (e) {
		if (!window.QpediaSEO) {
			return;
		}
		e.preventDefault();
		var $f = $(this);
		var payload = {
			inject_schema: $f.find("[name=inject_schema]").is(":checked") ? 1 : 0,
			skip_schema_if_rank_math: $f.find("[name=skip_schema_if_rank_math]").is(":checked") ? 1 : 0,
			inject_breadcrumb: $f.find("[name=inject_breadcrumb]").is(":checked") ? 1 : 0,
			weekly_scan: $f.find("[name=weekly_scan]").is(":checked") ? 1 : 0,
			daily_quick: $f.find("[name=daily_quick]").is(":checked") ? 1 : 0,
			batch_size: $f.find("[name=batch_size]").val(),
		};
		post("qpedia_save_settings", payload).done(function (res) {
			window.alert(res && res.success ? QpediaSEO.i18n.saved : QpediaSEO.i18n.saveError);
		});
	});
})(jQuery);
