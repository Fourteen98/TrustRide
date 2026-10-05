<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex">
    <title>TrustRide · {{ $trip->run->route->name }}</title>
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
    <style>
        :root { --bg: #F4F2ED; --card: #ffffff; --text: #16181B; --muted: #6B7076; --line: #E2DED4; --gold: #B08D3C; }
        @media (prefers-color-scheme: dark) {
            :root { --bg: #16181B; --card: #202326; --text: #F4F2ED; --muted: #A1A5AA; --line: #33373B; }
        }
        * { box-sizing: border-box; }
        html, body { height: 100%; margin: 0; }
        body { display: flex; flex-direction: column; background: var(--bg); color: var(--text); font: 16px/1.4 -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; }
        #map { flex: 1 1 55%; min-height: 240px; background: var(--line); }
        #panel { flex: 0 0 auto; max-height: 45%; overflow-y: auto; background: var(--card); padding: 16px; border-top: 1px solid var(--line); }
        h1 { font-size: 20px; margin: 0 0 2px; }
        .muted { color: var(--muted); font-size: 14px; }
        #headline { font-size: 18px; font-weight: 700; margin: 12px 0 4px; }
        ol { list-style: none; margin: 12px 0 0; padding: 0; }
        li { display: flex; gap: 10px; align-items: baseline; padding: 6px 0; border-top: 1px solid var(--line); }
        li .dot { flex: none; width: 10px; height: 10px; border-radius: 50%; border: 2px solid var(--muted); transform: translateY(1px); }
        li.reached .dot { background: var(--gold); border-color: var(--gold); }
        li.mine { font-weight: 700; }
        li .time { margin-left: auto; color: var(--muted); font-size: 14px; font-weight: 400; }
        .van { width: 22px; height: 22px; border-radius: 50%; background: var(--gold); border: 3px solid #fff; box-shadow: 0 1px 6px rgba(0,0,0,.45); }
        .van.stale { background: #8a8f95; }
        .stop { width: 12px; height: 12px; border-radius: 50%; background: #fff; border: 3px solid #16181B; }
        .stop.reached { border-color: var(--gold); }
        .stop.mine { width: 18px; height: 18px; border-color: #C0392B; }
        body.embed h1, body.embed #car { display: none; }
    </style>
</head>
<body class="{{ request()->boolean('embed') ? 'embed' : '' }}">
    <div id="map" role="img" aria-label="Map of the route and the van"></div>
    <div id="panel">
        <h1>{{ $trip->run->route->name }}</h1>
        <div class="muted" id="car">Car {{ $trip->run->vehicle->registration_number }}, {{ $trip->run->vehicle->colour }}</div>
        <div id="headline" aria-live="polite">Loading…</div>
        <div class="muted" id="seen"></div>
        <ol id="stops"></ol>
    </div>

    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin=""></script>
    <script>
        const dataUrl = @json($dataUrl) + window.location.search;
        const myStop = Number(new URLSearchParams(window.location.search).get('stop')) || null;
        const icon = (cls) => L.divIcon({ className: '', html: `<div class="${cls}"></div>`, iconSize: [22, 22], iconAnchor: [11, 11] });

        const esc = (text) => String(text ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

        let map = null, van = null, fitted = false;
        const stopMarkers = new Map();

        // The page still works without the map: the stop list and "stops away" need no tiles.
        try {
            map = L.map('map', { zoomControl: false, attributionControl: true }).setView([5.6037, -0.1870], 12);
            L.tileLayer('https://tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap contributors' }).addTo(map);
        } catch (e) { document.getElementById('map').style.display = 'none'; }

        function headline(d) {
            if (d.status === 'ended') return 'This trip has ended.';
            if (d.status !== 'running') return 'The van has not set off yet.';
            if (d.stops_away === null) return 'The van is on its way.';
            if (d.stops_away === 0) return 'The van is at your stop, or has passed it.';
            return d.stops_away === 1 ? 'The van is 1 stop away.' : `The van is ${d.stops_away} stops away.`;
        }

        function ago(iso) {
            const s = Math.max(0, Math.round((Date.now() - new Date(iso).getTime()) / 1000));
            return s < 60 ? 'just now' : s < 3600 ? `${Math.round(s / 60)} min ago` : `${Math.round(s / 3600)} h ago`;
        }

        function render(d) {
            document.getElementById('headline').textContent = headline(d);
            document.getElementById('seen').textContent = d.position
                ? (d.position.live ? 'Live position.' : `Last seen ${ago(d.position.at)}. The phones may have lost their network.`)
                : (d.status === 'running' ? 'No position from the van yet. The stop list still updates.' : '');

            document.getElementById('stops').innerHTML = d.stops.map(s =>
                `<li class="${s.reached ? 'reached' : ''} ${s.id === myStop ? 'mine' : ''}"><span class="dot"></span><span>${esc(s.name)}${s.id === myStop ? ' (your stop)' : ''}</span><span class="time">${esc(s.time)}</span></li>`
            ).join('');

            if (!map) return;

            const points = [];
            d.stops.filter(s => s.latitude !== null).forEach(s => {
                const at = [s.latitude, s.longitude];
                points.push(at);
                const cls = `stop ${s.reached ? 'reached' : ''} ${s.id === myStop ? 'mine' : ''}`;
                if (stopMarkers.has(s.id)) stopMarkers.get(s.id).setIcon(icon(cls));
                else stopMarkers.set(s.id, L.marker(at, { icon: icon(cls), title: s.name }).addTo(map).bindTooltip(esc(s.name)));
            });

            if (d.position) {
                const at = [d.position.latitude, d.position.longitude];
                points.push(at);
                const cls = d.position.live ? 'van' : 'van stale';
                if (van) van.setLatLng(at).setIcon(icon(cls)); else van = L.marker(at, { icon: icon(cls), zIndexOffset: 1000, title: 'The van' }).addTo(map);
            } else if (van) { map.removeLayer(van); van = null; }

            if (!fitted && points.length) { map.fitBounds(points, { padding: [40, 40], maxZoom: 16 }); fitted = true; }
            if (!points.length) document.getElementById('map').style.display = 'none';
        }

        async function tick() {
            try { render(await (await fetch(dataUrl, { headers: { Accept: 'application/json' } })).json()); }
            catch (e) { document.getElementById('seen').textContent = 'Can\'t reach TrustRide. Trying again.'; }
        }

        tick();
        setInterval(tick, 10000);
    </script>
</body>
</html>
