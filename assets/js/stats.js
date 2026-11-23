/* Anonymous page-view count (no cookies). See includes/site_stats.php. */
(function () {
    try {
        if (navigator.doNotTrack === '1' || /\/admin\//.test(location.pathname)) return;
        var u = new URLSearchParams(location.search), ref = document.referrer || '';
        var data = JSON.stringify({ p: location.pathname, id: u.get('id') || '', r: ref, us: u.get('utm_source') || '', uc: u.get('utm_campaign') || '' });
        var me = document.currentScript, url = me && me.dataset.hit;
        if (!url) return;
        if (navigator.sendBeacon) navigator.sendBeacon(url, new Blob([data], { type: 'application/json' }));
        else fetch(url, { method: 'POST', body: data, keepalive: true, headers: { 'Content-Type': 'application/json' } });
    } catch (e) { /* never break the page */ }
})();
