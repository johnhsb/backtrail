/*
 * Backtrail web app helpers (jQuery 3, Bootstrap 5, bootbox 6).
 * Pages are loaded into #content by action.php and call these helpers.
 */
var BT = (function ($) {
	'use strict';

	// Translations for the current language, written into the page by index.php
	var STRINGS = window.BT_STRINGS || {};

	// Translate English interface text; extra arguments fill in %s or %1$s
	function t(text) {
		var args = Array.prototype.slice.call(arguments, 1), next = 0;
		return (STRINGS[text] || text).replace(/%(?:(\d+)\$)?s/g, function (m, n) {
			return String(n ? args[n - 1] : args[next++]);
		});
	}

	var LOADING = '<div class="text-center py-5 text-muted"><div class="spinner-border" role="status"><span class="visually-hidden">' + t('Loading') + '</span></div></div>';

	// Page shown in #content, so a language change can show it again
	var current = 'welcome';

	// Bootstrap ignores hide() while a modal is still animating in, which
	// can leave a "please wait" dialog open after a fast AJAX reply.
	bootbox.addLocale('bt', { OK: t('OK'), CANCEL: t('Cancel'), CONFIRM: t('OK') });
	bootbox.setDefaults({ animate: false, centerVertical: true, locale: 'bt' });

	function parse(data) {
		return (typeof data === 'string') ? JSON.parse(data) : data;
	}

	function escapeHtml(text) {
		return $('<div>').text(text == null ? '' : String(text)).html();
	}

	function initTooltips() {
		document.querySelectorAll('.tooltip').forEach(function (t) { t.remove(); });
		document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
			bootstrap.Tooltip.getOrCreateInstance(el, { container: 'body', trigger: 'hover', html: true });
		});
	}

	function show(page) {
		current = page;
		$('#content').html(LOADING).load('action.php?page=' + page, function (responseTxt, statusTxt, xhr) {
			if (statusTxt === 'error') {
				bootbox.alert({ title: t('Unable to load the page'), message: escapeHtml(xhr.status + ': ' + xhr.statusText) });
			}
		});
	}

	function post(page, data) {
		current = page;
		$('#content').html(LOADING);
		$.post('action.php?page=' + page, data).done(function (html) {
			$('#content').html(html);
		});
	}

	function busy(message, closable) {
		return bootbox.dialog({
			message: '<div class="text-center py-2"><div class="spinner-border text-primary mb-3" role="status"></div><div>' + message + '</div></div>',
			closeButton: !!closable
		});
	}

	function fail(title, error) {
		bootbox.alert({
			title: title,
			message: '<div class="alert alert-danger mb-3"><i class="fas fa-exclamation-triangle me-1"></i> <b>' + error + '</b></div><p class="mb-0">' + t('Check your settings and try again.') + '</p>'
		});
	}

	// Submit a JSON-returning AJAX form step, then go to the next page
	function submit(url, data, nextPage, errorTitle) {
		$.post(url, data).done(function (d) {
			var r = parse(d);
			if (r.status) show(nextPage);
			else fail(errorTitle, r.error);
		});
	}

	function togglePassword(button) {
		var $input = $(button).closest('.input-group').find('input');
		$input.prop('type', $input.prop('type') === 'password' ? 'text' : 'password');
		$('i', button).toggleClass('fa-eye fa-eye-slash');
	}

	function shareSearch() {
		busy(t('Scanning network for shared drives...'));
		$.post('/ajax/nas-search.php', { type: 'cifs' }).done(function (html) {
			bootbox.hideAll();
			bootbox.alert({ message: html });
		});
	}

	$(document).on('click', '.bt-share', function () {
		$('#cifs_location').val($(this).data('location'));
		$('#cifs_domain').val($(this).data('domain'));
		bootbox.hideAll();
	});

	// Mount the drive chosen on the "This computer / Network drive / ..." tabs
	function submitLocation(form, nextPage, message) {
		busy(message, true);
		var type = $('#redo_tabs .nav-link.active').attr('data-bs-target');
		$.post('/ajax/mount-drive.php', { type: type, vars: $(form).serialize() })
			.done(function (d) {
				bootbox.hideAll();
				var r = parse(d);
				if (r.status) show(nextPage);
				else fail(t('Failed to access drive'), r.error);
			})
			.fail(function () {
				bootbox.hideAll();
				fail(t('Failed to access drive'), t('No response from the backup service'));
			});
	}

	// Open the folder or file picker (yad) on the local display
	function choose(type, input, errorTitle, errorHint) {
		busy(type === 'dir' ? t('Waiting for folder selection...') : t('Waiting for file selection...'), true);
		var data = { type: type };
		data[type] = $(input).val();
		$.post('/ajax/open-dialog.php', data).done(function (d) {
			bootbox.hideAll();
			var r = parse(d);
			$(input).val(r[type]);
			if (r.error) bootbox.alert({ title: errorTitle, message: '<p class="mb-0">' + escapeHtml(r.error) + '. ' + errorHint + '</p>' });
		});
	}

	// Partition tables: "select all" box, selection summary and partition bar
	function bindPartitions(table) {
		var $t = $(table);
		function update() {
			var count = 0, bytes = 0;
			$t.find('tbody input[type=checkbox]').each(function () {
				var on = this.checked;
				$('.bt-pbar [data-part="' + this.value + '"]').toggleClass('off', !on);
				if (on) { count++; bytes += Number($(this).data('bytes')) || 0; }
			});
			$('#sel-count').text(count);
			$('#sel-size').text(formatBytes(bytes));
			$t.find('thead input[type=checkbox]').prop('checked', count > 0 && count === $t.find('tbody input[type=checkbox]:enabled').length);
		}
		$t.on('change', 'tbody input[type=checkbox]', update);
		$t.on('change', 'thead input[type=checkbox]', function () {
			$t.find('tbody input[type=checkbox]:enabled').prop('checked', this.checked);
			update();
		});
		update();
	}

	function formatBytes(b) {
		var units = ['B', 'KiB', 'MiB', 'GiB', 'TiB'], i = 0;
		while (b >= 1024 && i < units.length - 1) { b /= 1024; i++; }
		return (i ? b.toFixed(1) : b) + ' ' + units[i];
	}

	// Poll a backup/restore/verify worker and update the progress page
	function runProgress(opts) {
		var timer = null;

		function set(id, value, fn) {
			if (value != null) (fn || function (v) { $('#' + id).text(v); })(value);
		}

		function finish() {
			clearInterval(timer);
			$('#overall_bar').removeClass('progress-bar-striped progress-bar-animated');
			$('#cancel').addClass('d-none');
			$('#again, #exit').removeClass('d-none');
		}

		function tick() {
			$.get(opts.endpoint).done(function (d) {
				var r = parse(d);
				if (!r.status) {
					finish();
					$('#overall_bar').addClass('bg-danger');
					bootbox.alert({
						title: '<i class="fas fa-times-circle text-danger me-1"></i> ' + opts.failTitle,
						message: '<p>' + t('The operation failed and was stopped. The error was:') + '</p><p class="mb-0"><code>' + escapeHtml(r.log_msg) + '</code></p>'
					});
					return;
				}
				if (opts.showDest && r.dest_pct != null) {
					$('#dest_used').text(r.dest_used);
					$('#dest_free').text(r.dest_free);
					$('#dest_bar').css('width', r.dest_pct + '%')
						.toggleClass('bg-warning', r.dest_pct >= 85 && r.dest_pct < 95)
						.toggleClass('bg-danger', r.dest_pct >= 95);
				}
				set('overall', r.overall_pct, function (v) {
					$('#overall_pct').text(Number(v).toFixed(1));
					$('#overall_bar').css('width', v + '%').attr('aria-valuenow', v);
				});
				set('part_pct', r.part_pct);
				set('part_num', r.part_num);
				set('part_size', r.part_size);
				set('part_used', r.part_used);
				set('part_mode', r.part_mode);
				set('time_elapsed', r.time_elapsed);
				set('time_remaining', r.time_remaining);
				set('speed', r.speed);
				set('target', r.target);
				set('details', r.details);
				if (r.log_msg != null) {
					var box = $('#log-box')[0];
					box.value += r.log_msg + '\n';
					box.scrollTop = box.scrollHeight;
				}
				if (r.done != null) {
					finish();
					$('#overall_pct').text('100.0');
					$('#overall_bar').css('width', '100%');
					bootbox.alert({
						title: '<i class="fas fa-check-circle text-success me-1"></i> ' + opts.doneTitle,
						message: '<p class="mb-0">' + escapeHtml(r.done) + '</p>'
					});
				}
			});
		}

		$('#cancel').on('click', function () {
			bootbox.confirm({
				title: t('Cancel this operation?'),
				message: '<p class="mb-0">' + opts.cancelMessage + '</p>',
				buttons: {
					confirm: { label: t('Cancel operation'), className: 'btn-danger' },
					cancel: { label: t('Keep going'), className: 'btn-outline-secondary' }
				},
				callback: function (yes) {
					if (yes) { clearInterval(timer); $('#content').load('/ajax/exit.php'); }
				}
			});
		});
		$('#exit').on('click', function () { $('#content').load('/ajax/exit.php'); });
		$('#again').on('click', function () { location.replace('/'); });
		$('#copy-log').on('click', function () {
			var text = $('#log-box').val();
			if (navigator.clipboard) navigator.clipboard.writeText(text);
			else { $('#log-box').trigger('select'); document.execCommand('copy'); }
		});

		function start() { timer = setInterval(tick, 1000); }

		if (opts.confirm) {
			bootbox.confirm({
				title: opts.confirm.title,
				message: opts.confirm.message,
				buttons: {
					confirm: { label: opts.confirm.label, className: 'btn-danger' },
					cancel: { label: t('Go back'), className: 'btn-outline-secondary' }
				},
				callback: function (yes) {
					if (yes) start();
					else show(opts.confirm.backPage);
				}
			});
		} else {
			start();
		}
	}

	// Light/dark theme, remembered per browser
	function setTheme(theme) {
		document.documentElement.setAttribute('data-bs-theme', theme);
		try { localStorage.setItem('bt-theme', theme); } catch (e) { /* storage unavailable */ }
		$('#theme-toggle i').attr('class', theme === 'dark' ? 'fas fa-sun' : 'fas fa-moon');
	}

	// Interface language: saved in a cookie so the PHP pages use it too, then
	// the current page is shown again. Not during an operation, whose
	// progress page would start over.
	function setLanguage(lang) {
		if (lang === document.documentElement.lang) return;
		if (/-progress$/.test(current)) {
			bootbox.alert({ message: '<p class="mb-0">' + t('You can change the language when the operation has finished.') + '</p>' });
			return;
		}
		document.cookie = 'bt-lang=' + encodeURIComponent(lang) + '; path=/; max-age=31536000; SameSite=Lax';
		location.replace('/?page=' + encodeURIComponent(current));
	}

	$(document).on('click', '[data-lang]', function () {
		setLanguage($(this).attr('data-lang'));
	});

	$(function () {
		setTheme(document.documentElement.getAttribute('data-bs-theme') || 'light');
		$('#theme-toggle').on('click', function () {
			setTheme(document.documentElement.getAttribute('data-bs-theme') === 'dark' ? 'light' : 'dark');
		});
	});

	return {
		t: t,
		show: show,
		post: post,
		busy: busy,
		fail: fail,
		submit: submit,
		parse: parse,
		escapeHtml: escapeHtml,
		initTooltips: initTooltips,
		togglePassword: togglePassword,
		shareSearch: shareSearch,
		submitLocation: submitLocation,
		choose: choose,
		bindPartitions: bindPartitions,
		runProgress: runProgress
	};
})(jQuery);
