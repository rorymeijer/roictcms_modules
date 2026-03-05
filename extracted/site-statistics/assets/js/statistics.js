/**
 * Site Statistieken – statistics.js
 * Initialiseert Chart.js-grafieken en laadt dashboarddata via AJAX.
 */

/* ── Kleurenpalet ────────────────────────────────────────────── */
var PALETTE = [
    '#4f46e5','#10b981','#f59e0b','#ef4444','#8b5cf6',
    '#06b6d4','#ec4899','#14b8a6','#f97316','#84cc16'
];
var PALETTE_LIGHT = PALETTE.map(function(c){ return c + '33'; });

/* ── Chart.js-standaardinstellingen ──────────────────────────── */
Chart.defaults.font.family = "'Inter','Segoe UI',system-ui,sans-serif";
Chart.defaults.font.size   = 12;
Chart.defaults.plugins.legend.position = 'bottom';
Chart.defaults.plugins.tooltip.padding = 10;
Chart.defaults.plugins.tooltip.cornerRadius = 8;
Chart.defaults.plugins.tooltip.backgroundColor = 'rgba(17,24,39,.9)';
Chart.defaults.animation.duration = 400;

/* ── Hulpfuncties ────────────────────────────────────────────── */
function api(action, extra) {
    var url = STATS_API + '?action=' + action + '&range=' + (STATS_RANGE || 30);
    if (extra) url += '&' + extra;
    return fetch(url).then(function(r){ return r.json(); });
}

function fmtDuration(seconds) {
    if (!seconds || seconds <= 0) return '0s';
    var m = Math.floor(seconds / 60);
    var s = Math.floor(seconds % 60);
    return m > 0 ? m + 'm ' + s + 's' : s + 's';
}

function fmtPct(val, prev) {
    if (!prev || prev === 0) return '';
    var diff = ((val - prev) / prev * 100).toFixed(0);
    if (diff > 0) return '<span class="stat-delta up">▲ ' + diff + '% t.o.v. gisteren</span>';
    if (diff < 0) return '<span class="stat-delta down">▼ ' + Math.abs(diff) + '% t.o.v. gisteren</span>';
    return '<span class="stat-delta same">= gelijk aan gisteren</span>';
}

function truncUrl(url, max) {
    max = max || 50;
    try { var u = new URL(url); url = u.pathname + u.search; } catch(e) {}
    return url.length > max ? url.slice(0, max) + '…' : url;
}

function makeDonut(id, labels, data) {
    var ctx = document.getElementById(id);
    if (!ctx) return null;
    return new Chart(ctx, {
        type: 'doughnut',
        data: {
            labels: labels,
            datasets: [{
                data: data,
                backgroundColor: PALETTE.slice(0, data.length),
                borderWidth: 2,
                borderColor: '#fff',
                hoverOffset: 6
            }]
        },
        options: {
            cutout: '62%',
            plugins: {
                legend: { position: 'right', labels: { boxWidth: 12, padding: 14 } },
                tooltip: {
                    callbacks: {
                        label: function(ctx) {
                            var total = ctx.dataset.data.reduce(function(a,b){return a+b;},0);
                            var pct   = total ? (ctx.parsed / total * 100).toFixed(1) : 0;
                            return ' ' + ctx.label + ': ' + ctx.parsed.toLocaleString('nl-NL') + ' (' + pct + '%)';
                        }
                    }
                }
            }
        }
    });
}

function makeBar(id, labels, data, labelText, color) {
    var ctx = document.getElementById(id);
    if (!ctx) return null;
    return new Chart(ctx, {
        type: 'bar',
        data: {
            labels: labels,
            datasets: [{
                label: labelText || 'Bezoeken',
                data: data,
                backgroundColor: color || '#4f46e5',
                borderRadius: 4,
                borderSkipped: false
            }]
        },
        options: {
            indexAxis: labels.length > 8 ? 'x' : 'y',
            responsive: true,
            plugins: { legend: { display: false } },
            scales: {
                x: { grid: { display: false } },
                y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,.06)' } }
            }
        }
    });
}

/* ════════════════════════════════════════════════════════════════
   TAB: DASHBOARD
════════════════════════════════════════════════════════════════ */
if (typeof STATS_TAB !== 'undefined' && STATS_TAB === 'dashboard') {

    // ── Samenvattingskaarten ──────────────────────────────────
    api('summary').then(function(d) {
        var t = d.today, y = d.yesterday;

        function setCard(id, value, delta) {
            var el = document.getElementById(id);
            if (!el) return;
            el.querySelector('.stat-value').innerHTML = value;
            var deltaEl = el.querySelector('.stat-delta');
            if (deltaEl) deltaEl.innerHTML = delta || '';
        }

        setCard('cardVisitors',
            (t.visitors || 0).toLocaleString('nl-NL'),
            fmtPct(t.visitors, y.visitors));

        setCard('cardPageviews',
            (t.pageviews || 0).toLocaleString('nl-NL'),
            fmtPct(t.pageviews, y.pageviews));

        setCard('cardBounce',
            d.bounce_rate + '%',
            '');

        setCard('cardDuration',
            fmtDuration(d.avg_duration),
            '');

        setCard('cardNew',
            (d.new_visitors || 0).toLocaleString('nl-NL'),
            '');
    });

    // ── Tijdlijndiagram ───────────────────────────────────────
    var timelineChart = null;
    api('timeline').then(function(d) {
        var ctx = document.getElementById('timelineChart');
        if (!ctx) return;
        timelineChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: d.labels,
                datasets: d.datasets.map(function(ds, i) {
                    return {
                        label: ds.label,
                        data: ds.data,
                        borderColor: ds.color,
                        backgroundColor: ds.color + '18',
                        borderWidth: 2.5,
                        pointRadius: d.labels.length > 60 ? 0 : 3,
                        pointHoverRadius: 5,
                        fill: true,
                        tension: 0.35
                    };
                })
            },
            options: {
                responsive: true,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'top', align: 'end' },
                    tooltip: {
                        callbacks: {
                            label: function(ctx) {
                                return ' ' + ctx.dataset.label + ': ' + ctx.parsed.y.toLocaleString('nl-NL');
                            }
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false }, ticks: { maxTicksLimit: 10 } },
                    y: { beginAtZero: true, grid: { color: 'rgba(0,0,0,.06)' }, ticks: {
                        callback: function(v){ return v.toLocaleString('nl-NL'); }
                    }}
                }
            }
        });
    });

    window.toggleStacked = function() {
        if (!timelineChart) return;
        var stacked = document.getElementById('chartStacked').checked;
        timelineChart.data.datasets.forEach(function(ds){ ds.fill = stacked; });
        timelineChart.options.scales.y.stacked = stacked;
        timelineChart.update();
    };

    // ── Verkeerbronnen (donut klein) ──────────────────────────
    api('sources').then(function(d) {
        makeDonut('sourcesChart', d.labels, d.data);
    });

    // ── Uurverdeling ──────────────────────────────────────────
    api('hourly').then(function(d) {
        makeBar('hourlyChart', d.labels, d.data, 'Weergaven', '#6366f1');
    });

    // ── Top pagina's tabel ────────────────────────────────────
    api('pages', 'limit=10').then(function(rows) {
        var tbody = document.querySelector('#topPagesTable tbody');
        if (!tbody) return;
        if (!rows.length) { tbody.innerHTML = '<tr><td colspan="4" class="text-center text-muted py-3">Geen data</td></tr>'; return; }
        var max = rows[0].views;
        tbody.innerHTML = rows.map(function(r, i) {
            var pct = max ? Math.round(r.views / max * 100) : 0;
            return '<tr>'
                + '<td class="ps-3 text-muted small">' + (i+1) + '</td>'
                + '<td>'
                +   '<div class="fw-semibold small text-truncate" style="max-width:280px" title="' + r.url + '">' + truncUrl(r.url) + '</div>'
                +   '<div class="stats-bar mt-1"><div class="stats-bar-fill" style="width:' + pct + '%"></div></div>'
                + '</td>'
                + '<td class="text-end">' + parseInt(r.views).toLocaleString('nl-NL') + '</td>'
                + '<td class="text-end pe-3">' + parseInt(r.unique_visitors).toLocaleString('nl-NL') + '</td>'
                + '</tr>';
        }).join('');
    });
}

/* ════════════════════════════════════════════════════════════════
   TAB: REALTIME
════════════════════════════════════════════════════════════════ */
if (typeof STATS_TAB !== 'undefined' && STATS_TAB === 'realtime') {

    function loadRealtime() {
        api('realtime').then(function(d) {
            // Teller
            var el = document.getElementById('rtActiveCount');
            if (el) el.textContent = d.active;

            // Badge in de tabnav bijwerken
            var badge = document.getElementById('realtimeBadge');
            if (badge) badge.textContent = d.active > 0 ? d.active : '●';

            // Actieve pagina's
            var ptbody = document.querySelector('#rtPagesTable tbody');
            if (ptbody) {
                ptbody.innerHTML = d.per_page.length
                    ? d.per_page.map(function(r) {
                        return '<tr><td class="ps-3 small text-truncate" style="max-width:340px">'
                            + truncUrl(r.url, 60) + '<br><span class="text-muted" style="font-size:.75rem">'
                            + (r.page_title || '') + '</span></td>'
                            + '<td class="text-end pe-3"><span class="badge bg-success">' + r.cnt + '</span></td></tr>';
                    }).join('')
                    : '<tr><td colspan="2" class="text-center text-muted py-3">Niemand actief</td></tr>';
            }

            // Recente weergaven
            var rtbody = document.querySelector('#rtRecentTable tbody');
            if (rtbody) {
                rtbody.innerHTML = d.recent.length
                    ? d.recent.map(function(r) {
                        var t = new Date(r.created_at);
                        var ts = t.toLocaleTimeString('nl-NL', {hour:'2-digit', minute:'2-digit', second:'2-digit'});
                        return '<tr>'
                            + '<td class="ps-3 text-muted small font-monospace">' + ts + '</td>'
                            + '<td class="small text-truncate" style="max-width:280px"><code>' + truncUrl(r.url, 55) + '</code></td>'
                            + '<td class="small text-muted">' + (r.page_title || '–') + '</td>'
                            + '</tr>';
                    }).join('')
                    : '<tr><td colspan="3" class="text-center text-muted py-3">Geen recente weergaven</td></tr>';
            }
        });
    }

    loadRealtime();
    setInterval(loadRealtime, 30000);
}

/* ════════════════════════════════════════════════════════════════
   TAB: PAGINA'S
════════════════════════════════════════════════════════════════ */
if (typeof STATS_TAB !== 'undefined' && STATS_TAB === 'pages') {

    api('pages', 'limit=50').then(function(rows) {
        var tbody = document.querySelector('#allPagesTable tbody');
        if (!tbody) return;
        if (!rows.length) { tbody.innerHTML = '<tr><td colspan="6" class="text-center text-muted py-4">Geen data voor dit datumbereik</td></tr>'; return; }
        var max = rows[0].views;
        tbody.innerHTML = rows.map(function(r, i) {
            var pct = max ? Math.round(r.views / max * 100) : 0;
            return '<tr>'
                + '<td class="ps-3 text-muted small">' + (i+1) + '</td>'
                + '<td style="max-width:300px">'
                +   '<div class="small fw-semibold text-truncate" title="' + r.url + '">' + truncUrl(r.url, 55) + '</div>'
                +   '<div class="stats-bar mt-1"><div class="stats-bar-fill" style="width:' + pct + '%"></div></div>'
                + '</td>'
                + '<td class="small text-muted text-truncate" style="max-width:200px">' + (r.title || '–') + '</td>'
                + '<td class="text-end fw-semibold">' + parseInt(r.views).toLocaleString('nl-NL') + '</td>'
                + '<td class="text-end text-muted">' + parseInt(r.unique_visitors).toLocaleString('nl-NL') + '</td>'
                + '<td class="text-end pe-3 text-muted">' + parseInt(r.sessions).toLocaleString('nl-NL') + '</td>'
                + '</tr>';
        }).join('');
    });
}

/* ════════════════════════════════════════════════════════════════
   TAB: BRONNEN
════════════════════════════════════════════════════════════════ */
if (typeof STATS_TAB !== 'undefined' && STATS_TAB === 'sources') {

    api('sources').then(function(d) {
        makeDonut('sourcesChartBig', d.labels, d.data);
    });

    api('referrers').then(function(rows) {
        var tbody = document.querySelector('#referrersTable tbody');
        if (!tbody) return;
        var typeLabelMap = {search:'Zoekmachine', social:'Sociaal', referral:'Verwijzing'};
        tbody.innerHTML = rows.length
            ? rows.map(function(r) {
                return '<tr>'
                    + '<td class="ps-3">' + r.source + '</td>'
                    + '<td><span class="badge bg-secondary">' + (typeLabelMap[r.type] || r.type) + '</span></td>'
                    + '<td class="text-end pe-3">' + parseInt(r.cnt).toLocaleString('nl-NL') + '</td>'
                    + '</tr>';
            }).join('')
            : '<tr><td colspan="3" class="text-center text-muted py-3">Geen verwijzingen</td></tr>';
    });

    api('keywords').then(function(rows) {
        var tbody = document.querySelector('#keywordsTable tbody');
        if (!tbody) return;
        tbody.innerHTML = rows.length
            ? rows.map(function(r) {
                return '<tr>'
                    + '<td class="ps-3">' + r.keyword + '</td>'
                    + '<td class="text-end pe-3">' + parseInt(r.cnt).toLocaleString('nl-NL') + '</td>'
                    + '</tr>';
            }).join('')
            : '<tr><td colspan="2" class="text-center text-muted py-3">Geen zoekwoorden (mogelijk anonimisering van zoekmachines)</td></tr>';
    });
}

/* ════════════════════════════════════════════════════════════════
   TAB: TECHNISCH
════════════════════════════════════════════════════════════════ */
if (typeof STATS_TAB !== 'undefined' && STATS_TAB === 'tech') {

    api('devices').then(function(d)  { makeDonut('devicesChart',  d.labels, d.data); });
    api('browsers').then(function(d) { makeDonut('browsersChart', d.labels, d.data); });
    api('os').then(function(d) {
        // OS als horizontale bar
        var ctx = document.getElementById('osChart');
        if (!ctx) return;
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: d.labels,
                datasets: [{
                    label: 'Sessies',
                    data: d.data,
                    backgroundColor: PALETTE.slice(0, d.data.length),
                    borderRadius: 4,
                    borderSkipped: false
                }]
            },
            options: {
                indexAxis: 'y',
                responsive: true,
                plugins: { legend: { display: false } },
                scales: {
                    x: { beginAtZero: true, grid: { color: 'rgba(0,0,0,.06)' } },
                    y: { grid: { display: false } }
                }
            }
        });
    });

    api('languages').then(function(rows) {
        var tbody = document.querySelector('#languagesTable tbody');
        if (!tbody) return;
        tbody.innerHTML = rows.length
            ? rows.map(function(r) {
                return '<tr>'
                    + '<td class="ps-3"><code>' + r.language + '</code></td>'
                    + '<td class="text-end pe-3">' + parseInt(r.cnt).toLocaleString('nl-NL') + '</td>'
                    + '</tr>';
            }).join('')
            : '<tr><td colspan="2" class="text-center text-muted py-3">Geen data</td></tr>';
    });
}

/* ════════════════════════════════════════════════════════════════
   TAB: AVG & PRIVACY
════════════════════════════════════════════════════════════════ */
if (typeof STATS_TAB !== 'undefined' && STATS_TAB === 'privacy') {

    window.avgSearch = function() {
        var term = document.getElementById('avgSearchInput').value.trim();
        var out  = document.getElementById('avgSearchResults');
        if (term.length < 3) { out.innerHTML = '<div class="text-danger small">Minimaal 3 tekens invoeren.</div>'; return; }
        out.innerHTML = '<div class="text-muted small">Zoeken…</div>';

        fetch(STATS_API + '?action=avg_search&term=' + encodeURIComponent(term))
            .then(function(r){ return r.json(); })
            .then(function(rows) {
                if (!rows.length) { out.innerHTML = '<div class="text-muted small">Geen resultaten gevonden.</div>'; return; }
                out.innerHTML = rows.map(function(r) {
                    return '<div class="avg-result-item">'
                        + '<div class="d-flex justify-content-between align-items-start">'
                        + '<div>'
                        + '<div class="text-muted mb-1" style="font-size:.72rem">BEZOEKERHASH</div>'
                        + '<code>' + r.visitor_hash + '</code>'
                        + '</div>'
                        + '<button class="btn btn-outline-danger btn-sm use-hash-btn ms-2" '
                        + 'onclick="document.getElementById(\'deleteTermInput\').value=\'' + r.visitor_hash + '\'">'
                        + 'Gebruik</button>'
                        + '</div>'
                        + '<div class="mt-2 d-flex gap-3 text-muted" style="font-size:.76rem">'
                        + '<span><i class="bi bi-calendar3"></i> ' + r.first_seen + ' – ' + r.last_seen + '</span>'
                        + '<span><i class="bi bi-layout-text-window"></i> ' + r.pageviews + ' weergaven</span>'
                        + '<span><i class="bi bi-arrows-angle-expand"></i> ' + r.sessions + ' sessies</span>'
                        + '</div>'
                        + '</div>';
                }).join('');
            });
    };
}

/* ════════════════════════════════════════════════════════════════
   Globale hulpfuncties (alle tabs)
════════════════════════════════════════════════════════════════ */
window.copyField = function(id) {
    var el = document.getElementById(id);
    if (!el) return;
    navigator.clipboard.writeText(el.value).then(function() {
        showToast('Gekopieerd naar klembord!');
    });
};

function showToast(msg) {
    var t = document.getElementById('copyToast');
    if (!t) {
        t = document.createElement('div');
        t.id = 'copyToast';
        t.className = 'copy-toast';
        document.body.appendChild(t);
    }
    t.textContent = msg;
    t.classList.add('show');
    setTimeout(function(){ t.classList.remove('show'); }, 2000);
}
