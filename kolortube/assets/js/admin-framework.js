(function () {
	'use strict';

	var cfg = window.WPSControlCenter || {};
	var root = document.getElementById('wps-control-center');
	if (!root) return;

	var timer = null;
	var running = false;
	var destructiveTasks = {
		media_rollback: 'Restore media state from the latest backup? Current featured-image and media mapping values may change.',
		rebuild: 'Create a media backup and queue the complete rebuild workflow?',
		cleanup_run: 'Run the safe cleanup rules now? Eligible database records will be permanently deleted.',
		duplicates_trash: 'Move exact duplicate posts from the latest report to WordPress Trash?'
	};

	function request(action, data) {
		var body = new URLSearchParams(Object.assign({ action: action, nonce: cfg.nonce }, data || {}));
		return fetch(cfg.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8' },
			body: body.toString()
		}).then(function (response) {
			return response.text().then(function (text) {
				var payload;
				try {
					payload = JSON.parse(text);
				} catch (error) {
					throw new Error(text || ('HTTP ' + response.status));
				}
				if (!response.ok || !payload.success) {
					throw new Error(payload && payload.data && payload.data.message ? payload.data.message : ('HTTP ' + response.status));
				}
				return payload;
			});
		});
	}

	function notice(message, error) {
		var box = document.getElementById('wps-live-notice');
		if (!box) return;
		box.classList.remove('wps-hidden', 'notice-info', 'notice-error', 'notice-success');
		box.classList.add(error ? 'notice-error' : 'notice-success');
		var paragraph = box.querySelector('p');
		if (paragraph) paragraph.textContent = message;
	}

	function escapeHtml(value) {
		return String(value == null ? '' : value).replace(/[&<>'"]/g, function (character) {
			return { '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[character];
		});
	}

	function render(status) {
		if (!status) return;
		var counts = status.counts || {};
		var summary = document.getElementById('wps-queue-summary');
		var taskList = document.getElementById('wps-task-list');
		var bar = document.getElementById('wps-overall-progress');
		var tasks = status.tasks || [];

		if (summary) {
			summary.innerHTML = ['pending', 'running', 'completed', 'failed'].map(function (key) {
				return '<div><span>' + escapeHtml(key) + '</span><strong>' + Number(counts[key] || 0) + '</strong></div>';
			}).join('');
		}

		var percent = status.progress && Number.isFinite(Number(status.progress.percent)) ? Number(status.progress.percent) : 0;
		percent = Math.max(0, Math.min(100, percent));
		if (bar) bar.style.width = percent + '%';

		if (taskList) {
			taskList.innerHTML = tasks.length ? tasks.map(function (task) {
				var type = String(task.type || 'task').replace(/_/g, ' ');
				var taskPercent = Math.max(0, Math.min(100, Number(task.percent || 0)));
				return '<div class="wps-task-row"><div><strong>#' + Number(task.id || 0) + ' · ' + escapeHtml(type) + '</strong><small>' + escapeHtml(task.message || task.status || '') + '</small></div><div class="wps-task-progress"><span style="width:' + taskPercent + '%"></span></div><b class="status-' + escapeHtml(task.status || '') + '">' + escapeHtml(task.status || '') + ' · ' + taskPercent + '%</b></div>';
			}).join('') : '<p>No queue history yet.</p>';
		}

		if (Number(counts.pending || 0) + Number(counts.running || 0) > 0) startPolling();
		else stopPolling();
	}

	function poll(runWorker) {
		if (running) return;
		running = true;
		request(runWorker ? 'wps_run_queue' : 'wps_queue_status')
			.then(function (response) {
				render(runWorker ? response.data.status : response.data);
			})
			.catch(function (error) {
				notice(error.message || (cfg.labels && cfg.labels.error) || 'The request could not be completed.', true);
				stopPolling();
			})
			.finally(function () { running = false; });
	}

	function startPolling() {
		if (!timer) timer = window.setInterval(function () { poll(true); }, Number(cfg.pollInterval || 2500));
	}

	function stopPolling() {
		if (timer) {
			window.clearInterval(timer);
			timer = null;
		}
	}

	function taskData(type) {
		var data = { task_type: type };
		var input;
		if (type === 'search_match') input = document.getElementById('wps-search-post-id');
		if (type === 'player_api_test') input = document.getElementById('wps-player-post-id');
		if (input) {
			data.post_id = input.value;
			if (!data.post_id || Number(data.post_id) < 1) {
				throw new Error('Enter a valid Post ID.');
			}
		}
		return data;
	}

	function renderIndexResults(rows) {
		var box = document.getElementById('wps-index-results');
		if (!box) return;
		if (!rows || !rows.length) {
			box.innerHTML = '<p>No matching indexed posts were found.</p>';
			return;
		}
		box.innerHTML = '<ol class="wps-index-results">' + rows.map(function (row) {
			var details = [row.code, row.actress, row.studio].filter(Boolean).map(escapeHtml).join(' · ');
			return '<li><strong>#' + Number(row.post_id || 0) + ' · ' + escapeHtml(row.post_title || row.post_name || '') + '</strong>' +
				(details ? '<small>' + details + '</small>' : '') +
				(row.video_url ? '<code>' + escapeHtml(row.video_url) + '</code>' : '') + '</li>';
		}).join('') + '</ol>';
	}

	function searchIndex() {
		var input = document.getElementById('wps-index-query');
		var button = document.getElementById('wps-index-search');
		var query = input ? input.value.trim() : '';
		if (!query) {
			notice('Enter a search term.', true);
			return;
		}
		if (button) button.disabled = true;
		request('wps_search_index_query', { query: query })
			.then(function (response) { renderIndexResults(response.data.results || []); })
			.catch(function (error) { notice(error.message || cfg.labels.error, true); })
			.finally(function () { if (button) button.disabled = false; });
	}

	root.addEventListener('click', function (event) {
		var taskButton = event.target.closest('.wps-task-button');
		if (taskButton) {
			event.preventDefault();
			var type = taskButton.getAttribute('data-task');
			if (destructiveTasks[type] && !window.confirm(destructiveTasks[type])) return;
			var data;
			try {
				data = taskData(type);
			} catch (error) {
				notice(error.message, true);
				return;
			}
			taskButton.disabled = true;
			request('wps_enqueue_task', data)
				.then(function (response) {
					notice(response.data.message || (cfg.labels && cfg.labels.queued) || 'Task queued.', false);
					render(response.data.status);
					poll(true);
				})
				.catch(function (error) { notice(error.message || cfg.labels.error, true); })
				.finally(function () { taskButton.disabled = false; });
			return;
		}

		if (event.target.id === 'wps-index-search') {
			event.preventDefault();
			searchIndex();
			return;
		}
		if (event.target.id === 'wps-run-worker') {
			event.preventDefault();
			poll(true);
		}
		if (event.target.id === 'wps-cancel-queue') {
			event.preventDefault();
			if (window.confirm(cfg.confirmCancel)) {
				request('wps_cancel_queue')
					.then(function (response) { render(response.data); notice('Queue cancelled.', false); })
					.catch(function (error) { notice(error.message || cfg.labels.error, true); });
			}
		}
		if (event.target.id === 'wps-cleanup-queue') {
			event.preventDefault();
			request('wps_cleanup_queue')
				.then(function (response) { notice('Queue history cleaned.', false); render(response.data.status); })
				.catch(function (error) { notice(error.message || cfg.labels.error, true); });
		}
	});

	root.addEventListener('keydown', function (event) {
		if (event.target && event.target.id === 'wps-index-query' && event.key === 'Enter') {
			event.preventDefault();
			searchIndex();
		}
	});

	document.addEventListener('submit', function (event) {
		if (event.target.classList.contains('wps-reset-form')) {
			var full = event.target.querySelector('input[name="reset_scope"][value="all"]');
			var message = full && full.checked ? 'Reset framework and legacy theme options? Menu locations will be preserved.' : 'Reset AV Framework PRO options only?';
			if (!window.confirm(message)) event.preventDefault();
		}
		if (event.target.querySelector('input[name="action"][value="wps_database_query"]')) {
			var sql = event.target.querySelector('textarea[name="sql_statement"]');
			if (sql && /^(UPDATE|INSERT|DELETE|REPLACE)\b/i.test(sql.value.trim()) && !window.confirm('Run this guarded database write? A recent full backup and exact confirmation phrase are required.')) {
				event.preventDefault();
			}
		}
		if (event.target.querySelector('input[name="action"][value="wps_theme_file_save"]') && !window.confirm('Back up, validate and save this current-theme source file?')) {
			event.preventDefault();
		}
	});

	var summary = document.getElementById('wps-queue-summary');
	var initial = summary ? summary.getAttribute('data-status') : '';
	try {
		render(JSON.parse(initial));
	} catch (error) {
		poll(false);
	}
}());
