(function () {
    function resolveTrackUrl() {
        var s = document.currentScript;
        if (!s || !s.src) {
            return 'track.php';
        }
        // Заменяем /assets/tracker.js на /track.php
        return s.src.replace(/\/assets\/tracker\.js(?:\?.*)?$/i, '/track.php');
    }

    var TRACK_URL = resolveTrackUrl();

    function sendEvent(type, payload) {
        try {
            var data = {
                type: type,
                payload: payload || {},
                ts: new Date().toISOString()
            };

            var json = JSON.stringify(data);

            if (navigator.sendBeacon) {
                var blob = new Blob([json], {type: 'application/json'});
                navigator.sendBeacon(TRACK_URL, blob);
            } else {
                var xhr = new XMLHttpRequest();
                xhr.open('POST', TRACK_URL, true);
                xhr.setRequestHeader('Content-Type', 'application/json');
                xhr.send(json);
            }
        } catch (e) {
            // глушим
        }
    }

    function onPageView() {
        sendEvent('page_view', {
            url: window.location.href,
            referrer: document.referrer || ''
        });
    }

    function onClick(e) {
        var el = e.target;
        while (el && el.tagName && el.tagName.toLowerCase() !== 'a') {
            el = el.parentNode;
        }
        if (!el || !el.href) {
            return;
        }

        var href = el.href;
        var fileExtMatch = href.match(/\.(pdf|zip|rar|7z|docx?|xlsx?|pptx?|xls|doc|jpg|jpeg|png|gif|mp3|mp4|avi|mov)(\?|#|$)/i);
        var type = fileExtMatch ? 'file_download' : 'link_click';

        sendEvent(type, {
            url: window.location.href,
            target: href,
            text: (el.textContent || '').trim().slice(0, 200)
        });
    }

    if (document.readyState === 'complete' || document.readyState === 'interactive') {
        onPageView();
    } else {
        document.addEventListener('DOMContentLoaded', onPageView);
    }

    document.addEventListener('click', onClick, true);
})();
