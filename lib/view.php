<?php

function serveView()
{
    global $config;
    if (!checkAuth('view')) return;

    $storage = createStorage($config);
    $range = $_GET['range'] ?? 'day';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 20;

    $export = $_GET['export'] ?? '';
    if ($export === 'csv' || $export === 'json') {
        $visits = $storage->getVisits($range, 1, 999999);
        if ($export === 'json') {
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="stats-' . $range . '-' . date('Y-m-d') . '.json"');
            echo json_encode($visits, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } else {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="stats-' . $range . '-' . date('Y-m-d') . '.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Timestamp', 'Duration', 'Interactions', 'Language', 'IP', 'Location', 'OS', 'Referrer', 'Page']);
            foreach ($visits as $v) {
                fputcsv($out, [
                    $v['id'] ?? '',
                    date('Y-m-d H:i:s', $v['timestamp'] ?? 0),
                    $v['duration'] ?? '',
                    $v['interactions'] ?? '',
                    $v['lang'] ?? '',
                    $v['ip'] ?? '',
                    $v['geo'] ?? '',
                    $v['os'] ?? '',
                    $v['referrer'] ?? '',
                    $v['page'] ?? '',
                ]);
            }
            fclose($out);
        }
        return;
    }

    $total = $storage->getVisitCount($range);
    $stats = $storage->getStats($range);
    $visits = $storage->getVisits($range, $page, $perPage);
    $totalPages = max(1, (int)ceil($total / $perPage));

    $chartData = $storage->getAggregatedStats($range);

    $queryBase = "?view&range=$range";

    header('Content-Type: text/html; charset=utf-8');
    ?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Stats - <?=htmlspecialchars($range)?></title>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4"></script>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:-apple-system,BlinkMacSystemFont,sans-serif;background:#f5f5f5;color:#333;padding:20px}
h1{margin-bottom:8px;font-size:1.5rem}
.subtitle{margin-bottom:16px;font-size:.85rem;color:#666}
nav{margin-bottom:20px}
nav a{display:inline-block;padding:8px 16px;margin-right:4px;background:#fff;border:1px solid #ddd;text-decoration:none;color:#333;border-radius:4px;font-size:.9rem}
nav a.active{background:#0066cc;color:#fff;border-color:#0066cc}
.chart-wrap{background:#fff;border-radius:8px;padding:16px;margin-bottom:20px}
.summary{display:flex;gap:16px;margin-bottom:20px}
.summary-card{background:#fff;border-radius:8px;padding:20px;flex:1;text-align:center;box-shadow:0 1px 3px rgba(0,0,0,.08)}
.summary-card .num{font-size:2rem;font-weight:700;color:#0066cc}
.summary-card .label{font-size:.85rem;color:#666;margin-top:4px}
.toolbar{display:flex;gap:8px;margin-bottom:16px;align-items:center}
.toolbar a{display:inline-block;padding:6px 14px;background:#fff;border:1px solid #ddd;text-decoration:none;color:#333;border-radius:4px;font-size:.85rem}
.toolbar a:hover{background:#f0f0f0}
table{width:100%;border-collapse:collapse;background:#fff;border-radius:8px;overflow:hidden;box-shadow:0 1px 3px rgba(0,0,0,.08)}
th,td{padding:10px 12px;text-align:left;border-bottom:1px solid #eee;font-size:.85rem}
th{background:#fafafa;font-weight:600;color:#555}
tr:hover{background:#f8f9ff}
.pagination{margin-top:16px;text-align:center}
.pagination a{display:inline-block;padding:6px 12px;margin:0 2px;background:#fff;border:1px solid #ddd;text-decoration:none;color:#333;border-radius:4px;font-size:.85rem}
.pagination a.active{background:#0066cc;color:#fff;border-color:#0066cc}
.pagination a.disabled{opacity:.4;pointer-events:none}
footer{margin-top:32px;font-size:.8rem;color:#999;text-align:center}
</style>
</head>
<body>

<h1>local-stats</h1>
<div class="subtitle">Self-hosted analytics &middot; <a href="?settings">Settings</a> &middot; <a href="?logout">Logout</a></div>

<nav>
<?php foreach (['day','week','month','all'] as $r):
    $active = $r === $range ? ' active' : ''; ?>
<a href="?view&range=<?=$r?>" class="<?=$active?>"><?=ucfirst($r)?></a>
<?php endforeach; ?>
</nav>

<div class="chart-wrap">
<canvas id="chart" height="250"></canvas>
</div>

<div class="summary">
<div class="summary-card"><div class="num"><?=$stats['total_visits']?></div><div class="label">Total Visits</div></div>
<div class="summary-card"><div class="num"><?=$stats['avg_duration']?>s</div><div class="label">Avg Duration</div></div>
</div>

<div class="toolbar">
<a href="<?=$queryBase?>&export=csv">Export CSV</a>
<a href="<?=$queryBase?>&export=json">Export JSON</a>
</div>

<table>
<thead><tr>
<th>ID</th><th>Time</th><th>Duration</th><th>Interactions</th><th>Language</th><th>IP</th><th>Location</th><th>OS</th><th>Page</th>
</tr></thead>
<tbody>
<?php foreach ($visits as $v):
    $time = isset($v['timestamp']) ? date('Y-m-d H:i', $v['timestamp']) : '-';
    $dur = isset($v['duration']) ? round($v['duration'], 1) . 's' : '-';
    $intr = $v['interactions'] ?? 0;
    $lang = htmlspecialchars($v['lang'] ?? '-');
    $ip = htmlspecialchars($v['ip'] ?? '-');
    $geo = htmlspecialchars($v['geo'] ?? '-');
    $os = htmlspecialchars($v['os'] ?? '-');
    $page = htmlspecialchars($v['page'] ?? '-');
    $id = htmlspecialchars(substr($v['id'] ?? '', 0, 8));
?>
<tr><td title="<?=htmlspecialchars($v['id']??'')?>"><?=$id?></td><td><?=$time?></td><td><?=$dur?></td><td><?=$intr?></td><td><?=$lang?></td><td><?=$ip?></td><td><?=$geo?></td><td><?=$os?></td><td><?=$page?></td></tr>
<?php endforeach; ?>
<?php if (empty($visits)): ?><tr><td colspan="9" style="text-align:center;color:#999;padding:32px">No visits yet</td></tr><?php endif; ?>
</tbody>
</table>

<div class="pagination">
<a href="<?=$queryBase?>&page=1" class="<?=$page<=1?'disabled':''?>">&laquo;</a>
<?php for ($i = max(1, $page-2); $i <= min($totalPages, $page+2); $i++): ?>
<a href="<?=$queryBase?>&page=<?=$i?>" class="<?=$i===$page?'active':''?>"><?=$i?></a>
<?php endfor; ?>
<a href="<?=$queryBase?>&page=<?=$totalPages?>" class="<?=$page>=$totalPages?'disabled':''?>">&raquo;</a>
</div>

<footer>local-stats &mdash; self-hosted analytics</footer>

<script>
var chartData = <?=json_encode($chartData)?>;
var ctx = document.getElementById('chart').getContext('2d');
new Chart(ctx, {
    type: 'bar',
    data: {
        labels: chartData.labels,
        datasets: [{
            label: 'Visits',
            data: chartData.counts,
            backgroundColor: 'rgba(0,102,204,0.6)',
            borderColor: 'rgba(0,102,204,1)',
            borderWidth: 1,
            yAxisID: 'y',
            order: 1
        }, {
            label: 'Avg Duration (s)',
            data: chartData.durations,
            backgroundColor: 'rgba(255,159,64,0.6)',
            borderColor: 'rgba(255,159,64,1)',
            borderWidth: 1,
            yAxisID: 'y1',
            order: 2
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { position: 'top' } },
        scales: {
            y: { beginAtZero: true, position: 'left', title: { display: true, text: 'Visits' } },
            y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'Seconds' } }
        }
    }
});
</script>

</body>
</html>
<?php
}