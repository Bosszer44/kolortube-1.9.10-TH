(function () {
	'use strict';
	if (!window.WPSLiveAnalytics || !window.fetch) {
		return;
	}
	var storageKey = 'wps_visitor_id';
	var visitor = '';
	try {
		visitor = window.localStorage.getItem(storageKey) || '';
		if (!visitor) {
			visitor = 'v_' + Date.now().toString(36) + '_' + Math.random().toString(36).slice(2) + Math.random().toString(36).slice(2);
			window.localStorage.setItem(storageKey, visitor);
		}
	} catch (error) {
		visitor = 's_' + Math.random().toString(36).slice(2) + Date.now().toString(36);
	}

	function sourceType() {
		if (!document.referrer) {
			return 'direct';
		}
		try {
			var ref = new URL(document.referrer);
			if (ref.hostname === window.location.hostname) {
				return 'internal';
			}
			if (/google\.|bing\.|yahoo\.|duckduckgo\./i.test(ref.hostname)) {
				return 'search';
			}
			if (/facebook\.|twitter\.|x\.com$|instagram\.|tiktok\.|youtube\./i.test(ref.hostname)) {
				return 'social';
			}
		} catch (error) {
			return 'referral';
		}
		return 'referral';
	}

	function ping() {
		var data = new URLSearchParams();
		data.append('action', window.WPSLiveAnalytics.action);
		data.append('visitor', visitor);
		data.append('page', window.location.pathname + window.location.search);
		data.append('source', sourceType());
		window.fetch(window.WPSLiveAnalytics.url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {'Content-Type': 'application/x-www-form-urlencoded;charset=UTF-8'},
			body: data.toString(),
			keepalive: true
		}).catch(function () {});
	}

	ping();
	window.setInterval(ping, Math.max(60000, parseInt(window.WPSLiveAnalytics.interval, 10) || 120000));
}());
