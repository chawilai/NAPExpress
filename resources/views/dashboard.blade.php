<!DOCTYPE html>
<html lang="th">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>AutoNAP Dashboard</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.ably.com/lib/ably.min-1.js"></script>
    <style>
        @keyframes pulse-dot { 0%, 100% { opacity: 1; } 50% { opacity: 0.4; } }
        .pulse-dot { animation: pulse-dot 1.5s infinite; }
        @keyframes slide-in { from { opacity: 0; transform: translateY(4px); } to { opacity: 1; transform: translateY(0); } }
        .log-entry { animation: slide-in 0.2s ease-out; }
    </style>
</head>
<body class="bg-gray-50 min-h-screen">
    <div class="max-w-6xl mx-auto px-4 py-6">

        {{-- Header --}}
        <div class="flex items-center justify-between mb-6">
            <div>
                <h1 class="text-2xl font-bold text-gray-900">AutoNAP Dashboard</h1>
                <p class="text-sm text-gray-500 mt-1">Real-time monitoring</p>
            </div>
            <div class="flex items-center gap-3">
                <span id="connection-status" class="text-xs text-gray-400">Connecting...</span>
                <span id="clock" class="text-sm font-mono text-gray-600"></span>
            </div>
        </div>

        {{-- Workers --}}
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
            <div id="worker-0" class="bg-white rounded-xl border border-gray-200 p-5">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Worker 1</h3>
                    <span class="worker-badge px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">Idle</span>
                </div>
                <div class="worker-content text-center py-6 text-gray-300 text-sm">No active job</div>
            </div>
            <div id="worker-1" class="bg-white rounded-xl border border-gray-200 p-5">
                <div class="flex items-center justify-between mb-3">
                    <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide">Worker 2</h3>
                    <span class="worker-badge px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500">Idle</span>
                </div>
                <div class="worker-content text-center py-6 text-gray-300 text-sm">No active job</div>
            </div>
        </div>

        {{-- Queue --}}
        <div id="queue-section" class="bg-amber-50 border border-amber-200 rounded-xl p-5 mb-6 hidden">
            <h3 class="text-sm font-semibold text-amber-700 uppercase tracking-wide mb-2">Queue</h3>
            <div id="queue-content"></div>
        </div>

        {{-- Stats --}}
        <div class="grid grid-cols-2 md:grid-cols-5 gap-3 mb-6">
            <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                <div id="stat-jobs" class="text-2xl font-bold text-gray-800">-</div>
                <div class="text-xs text-gray-500 mt-1">Total Jobs</div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                <div id="stat-records" class="text-2xl font-bold text-gray-800">-</div>
                <div class="text-xs text-gray-500 mt-1">Total Records</div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                <div id="stat-success" class="text-2xl font-bold text-green-600">-</div>
                <div class="text-xs text-gray-500 mt-1">Success</div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                <div id="stat-failed" class="text-2xl font-bold text-red-500">-</div>
                <div class="text-xs text-gray-500 mt-1">Failed</div>
            </div>
            <div class="bg-white rounded-xl border border-gray-200 p-4 text-center">
                <div id="stat-avg" class="text-2xl font-bold text-teal-600">-</div>
                <div class="text-xs text-gray-500 mt-1">Avg sec/record</div>
            </div>
        </div>

        {{-- Today --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 mb-6">
            <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">Today</h3>
            <div class="grid grid-cols-4 gap-4 text-center">
                <div>
                    <span id="today-jobs" class="text-xl font-bold text-gray-800">-</span>
                    <div class="text-xs text-gray-500">Jobs</div>
                </div>
                <div>
                    <span id="today-records" class="text-xl font-bold text-gray-800">-</span>
                    <div class="text-xs text-gray-500">Records</div>
                </div>
                <div>
                    <span id="today-success" class="text-xl font-bold text-green-600">-</span>
                    <div class="text-xs text-gray-500">Success</div>
                </div>
                <div>
                    <span id="today-failed" class="text-xl font-bold text-red-500">-</span>
                    <div class="text-xs text-gray-500">Failed</div>
                </div>
            </div>
        </div>

        {{-- By Site --}}
        <div class="bg-white rounded-xl border border-gray-200 p-5 mb-6">
            <h3 class="text-sm font-semibold text-gray-500 uppercase tracking-wide mb-3">By Site</h3>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="text-left text-gray-500 border-b">
                            <th class="pb-2 font-medium">Site</th>
                            <th class="pb-2 font-medium">Type</th>
                            <th class="pb-2 font-medium text-right">Jobs</th>
                            <th class="pb-2 font-medium text-right">Records</th>
                            <th class="pb-2 font-medium text-right">Success</th>
                            <th class="pb-2 font-medium text-right">Failed</th>
                            <th class="pb-2 font-medium text-right">Rate</th>
                        </tr>
                    </thead>
                    <tbody id="site-table"></tbody>
                </table>
            </div>
        </div>

        {{-- Live Log --}}
        <div class="bg-gray-900 rounded-xl p-5">
            <div class="flex items-center justify-between mb-3">
                <h3 class="text-sm font-semibold text-gray-400 uppercase tracking-wide">Live Log</h3>
                <button onclick="document.getElementById('log-container').innerHTML=''" class="text-xs text-gray-500 hover:text-gray-300">Clear</button>
            </div>
            <div id="log-container" class="font-mono text-xs text-gray-300 max-h-64 overflow-y-auto space-y-0.5"></div>
            <div id="log-empty" class="text-center text-gray-600 text-xs py-4">Waiting for events...</div>
        </div>
    </div>

    <script>
        const ABLY_KEY = @json($ablyKey);
        const API_URL = '/api/dashboard';

        // State
        let ably = null;
        let subscribedChannels = {};
        let workerData = [{}, {}];
        let progressMap = {}; // jobId → { index, total }

        // ============================================================
        // Clock
        // ============================================================
        function updateClock() {
            document.getElementById('clock').textContent = new Date().toLocaleTimeString('th-TH');
        }
        setInterval(updateClock, 1000);
        updateClock();

        // ============================================================
        // Ably
        // ============================================================
        function initAbly() {
            if (!ABLY_KEY) {
                document.getElementById('connection-status').textContent = 'No Ably key';
                return;
            }
            ably = new Ably.Realtime({ key: ABLY_KEY });
            ably.connection.on('connected', () => {
                document.getElementById('connection-status').innerHTML =
                    '<span class="inline-block w-2 h-2 bg-green-400 rounded-full pulse-dot mr-1"></span>Connected';
            });
            ably.connection.on('disconnected', () => {
                document.getElementById('connection-status').innerHTML =
                    '<span class="inline-block w-2 h-2 bg-red-400 rounded-full mr-1"></span>Disconnected';
            });
        }

        function subscribeChannel(channelName, jobId) {
            if (!ably || subscribedChannels[channelName]) return;

            const channel = ably.channels.get(channelName);
            channel.subscribe((msg) => {
                const data = msg.data || {};
                const event = msg.name;

                // Update progress tracking
                if (data.index && data.total) {
                    progressMap[jobId] = { index: data.index, total: data.total };
                    updateWorkerProgress(jobId);
                }

                // Log
                addLog(event, data.message || JSON.stringify(data), channelName);
            });

            subscribedChannels[channelName] = channel;
        }

        function unsubscribeAll() {
            for (const [name, channel] of Object.entries(subscribedChannels)) {
                channel.unsubscribe();
            }
            subscribedChannels = {};
        }

        // ============================================================
        // API Fetch
        // ============================================================
        async function fetchDashboard() {
            try {
                const res = await fetch(API_URL);
                const data = await res.json();
                renderWorkers(data.workers);
                renderQueue(data.queue);
                renderStats(data.stats);

                // Subscribe to active job channels
                for (const w of data.workers) {
                    if (w.status === 'active' && w.ably_channel) {
                        subscribeChannel(w.ably_channel, w.job_id);
                    }
                }
            } catch (e) {
                console.error('Dashboard fetch failed:', e);
            }
        }

        // ============================================================
        // Render Workers
        // ============================================================
        function renderWorkers(workers) {
            workers.forEach((w, i) => {
                const el = document.getElementById(`worker-${i}`);
                if (!el) return;

                const badge = el.querySelector('.worker-badge');
                const content = el.querySelector('.worker-content');

                if (w.status === 'idle') {
                    badge.className = 'worker-badge px-2.5 py-0.5 rounded-full text-xs font-medium bg-gray-100 text-gray-500';
                    badge.textContent = 'Idle';
                    content.innerHTML = '<div class="text-center py-6 text-gray-300 text-sm">No active job</div>';
                    el.className = 'bg-white rounded-xl border border-gray-200 p-5';
                } else {
                    const elapsed = w.elapsed_seconds || 0;
                    const progress = progressMap[w.job_id] || {};
                    const current = progress.index || 0;
                    const total = progress.total || w.total || 0;
                    const pct = total > 0 ? Math.round((current / total) * 100) : 0;
                    const avgSec = current > 0 ? (elapsed / current).toFixed(1) : '-';
                    const remaining = current > 0 && total > 0 ? Math.ceil(((total - current) * elapsed) / current / 60) : '-';

                    badge.className = 'worker-badge px-2.5 py-0.5 rounded-full text-xs font-medium bg-green-100 text-green-700';
                    badge.textContent = 'Active';
                    el.className = 'bg-white rounded-xl border-2 border-green-300 p-5 shadow-sm';

                    content.innerHTML = `
                        <div class="space-y-3">
                            <div class="flex items-center justify-between">
                                <span class="font-semibold text-gray-900">${w.site}</span>
                                <span class="px-2 py-0.5 rounded text-xs font-bold ${w.form_type === 'VCT' ? 'bg-blue-100 text-blue-700' : 'bg-purple-100 text-purple-700'}">${w.form_type}</span>
                            </div>
                            <div class="w-full bg-gray-100 rounded-full h-2.5">
                                <div class="bg-teal-500 h-2.5 rounded-full transition-all duration-500" style="width: ${pct}%"></div>
                            </div>
                            <div class="flex justify-between text-xs text-gray-500">
                                <span>${current} / ${total} (${pct}%)</span>
                                <span>${formatDuration(elapsed)}</span>
                            </div>
                            <div class="grid grid-cols-3 gap-2 text-center text-xs">
                                <div class="bg-gray-50 rounded p-2">
                                    <div class="font-bold text-gray-700">${avgSec}s</div>
                                    <div class="text-gray-400">avg/record</div>
                                </div>
                                <div class="bg-gray-50 rounded p-2">
                                    <div class="font-bold text-gray-700">${total}</div>
                                    <div class="text-gray-400">total</div>
                                </div>
                                <div class="bg-gray-50 rounded p-2">
                                    <div class="font-bold text-gray-700">${remaining === '-' ? '-' : '~' + remaining + 'm'}</div>
                                    <div class="text-gray-400">remaining</div>
                                </div>
                            </div>
                            <div class="text-xs text-gray-400 font-mono truncate">${w.job_id}</div>
                        </div>
                    `;

                    workerData[i] = w;
                }
            });
        }

        function updateWorkerProgress(jobId) {
            // Re-render workers with latest progress
            workerData.forEach((w, i) => {
                if (w.job_id === jobId) {
                    const el = document.getElementById(`worker-${i}`);
                    if (el && w.status === 'active') {
                        // Trigger re-render with updated progress
                        const progress = progressMap[jobId] || {};
                        const elapsed = w.elapsed_seconds + Math.round((Date.now() - (w._fetchTime || Date.now())) / 1000);
                        w.elapsed_seconds = elapsed;
                        w._fetchTime = Date.now();
                        renderWorkers(workerData.map((wd, idx) => idx === i ? { ...wd, elapsed_seconds: elapsed } : wd));
                    }
                }
            });
        }

        // ============================================================
        // Render Queue
        // ============================================================
        function renderQueue(queue) {
            const section = document.getElementById('queue-section');
            const content = document.getElementById('queue-content');

            if (queue.waiting > 0) {
                section.classList.remove('hidden');
                let html = `<p class="text-sm text-amber-800 mb-2">${queue.waiting} job(s) waiting</p>`;
                if (queue.jobs?.length) {
                    html += '<div class="space-y-1">';
                    for (const j of queue.jobs) {
                        html += `<div class="text-xs text-amber-700 bg-amber-100 rounded px-3 py-1.5">${j.site} &mdash; ${j.form_type} &mdash; ${j.total} records</div>`;
                    }
                    html += '</div>';
                }
                content.innerHTML = html;
            } else {
                section.classList.add('hidden');
            }
        }

        // ============================================================
        // Render Stats
        // ============================================================
        function renderStats(stats) {
            const o = stats.overall;
            document.getElementById('stat-jobs').textContent = o.total_jobs.toLocaleString();
            document.getElementById('stat-records').textContent = o.total_records.toLocaleString();
            document.getElementById('stat-success').textContent = o.total_success.toLocaleString();
            document.getElementById('stat-failed').textContent = o.total_failed.toLocaleString();
            document.getElementById('stat-avg').textContent = o.avg_seconds_per_record || '-';

            const t = stats.today;
            document.getElementById('today-jobs').textContent = t.jobs;
            document.getElementById('today-records').textContent = t.records;
            document.getElementById('today-success').textContent = t.success;
            document.getElementById('today-failed').textContent = t.failed;

            // Site table
            const tbody = document.getElementById('site-table');
            tbody.innerHTML = stats.by_site.map(s => {
                const rate = s.records > 0 ? Math.round((s.success / s.records) * 100) : 0;
                const rateColor = rate >= 80 ? 'text-green-600' : rate >= 50 ? 'text-amber-600' : 'text-red-600';
                return `
                    <tr class="border-b border-gray-50 hover:bg-gray-50">
                        <td class="py-2 font-medium text-gray-900">${s.site}</td>
                        <td class="py-2"><span class="px-2 py-0.5 rounded text-xs font-bold ${s.form_type === 'VCT' ? 'bg-blue-50 text-blue-600' : 'bg-purple-50 text-purple-600'}">${s.form_type}</span></td>
                        <td class="py-2 text-right text-gray-700">${s.jobs}</td>
                        <td class="py-2 text-right text-gray-700">${s.records}</td>
                        <td class="py-2 text-right text-green-600">${s.success}</td>
                        <td class="py-2 text-right text-red-500">${s.failed}</td>
                        <td class="py-2 text-right font-medium ${rateColor}">${rate}%</td>
                    </tr>
                `;
            }).join('');
        }

        // ============================================================
        // Live Log
        // ============================================================
        function addLog(event, message, channel) {
            const container = document.getElementById('log-container');
            const empty = document.getElementById('log-empty');
            if (empty) empty.style.display = 'none';

            const time = new Date().toLocaleTimeString('th-TH');
            const colors = {
                'job:record:success': 'text-green-400',
                'job:record:failed': 'text-red-400',
                'job:complete': 'text-teal-400',
                'job:login:success': 'text-green-400',
                'job:login:failed': 'text-red-400',
                'job:thaid:qr': 'text-yellow-400',
            };
            const color = colors[event] || 'text-gray-400';

            const div = document.createElement('div');
            div.className = `log-entry ${color}`;
            div.innerHTML = `<span class="text-gray-600">[${time}]</span> <span class="text-gray-500">${channel}</span> ${message}`;

            container.appendChild(div);
            container.scrollTop = container.scrollHeight;

            // Keep max 200 entries
            while (container.children.length > 200) {
                container.removeChild(container.firstChild);
            }
        }

        // ============================================================
        // Utils
        // ============================================================
        function formatDuration(seconds) {
            const m = Math.floor(seconds / 60);
            const s = seconds % 60;
            return m > 0 ? `${m}m ${s}s` : `${s}s`;
        }

        // ============================================================
        // Init
        // ============================================================
        initAbly();
        fetchDashboard();
        // Refresh API data every 15 seconds
        setInterval(fetchDashboard, 15000);
    </script>
</body>
</html>
