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

    require_once __DIR__ . '/common.php';
    header('Content-Type: text/html; charset=utf-8');
    renderHead("Stats - $range");
    renderChartJs();
    renderTop('view');
    ?>

<nav class="range-nav">
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

<div class="table-wrap">
<table>
<thead><tr>
<th>ID</th><th>Time</th><th>Duration</th><th>Interactions</th><th>Language</th><th>Subnet</th><th>Location</th><th>OS</th><th>Page</th>
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
<?php if (empty($visits)): ?><tr class="empty"><td colspan="9">No visits yet</td></tr><?php endif; ?>
</tbody>
</table>
</div>

<div class="pagination">
<a href="<?=$queryBase?>&page=1" class="<?=$page<=1?'disabled':''?>">&laquo;</a>
<?php for ($i = max(1, $page-2); $i <= min($totalPages, $page+2); $i++): ?>
<a href="<?=$queryBase?>&page=<?=$i?>" class="<?=$i===$page?'active':''?>"><?=$i?></a>
<?php endfor; ?>
<a href="<?=$queryBase?>&page=<?=$totalPages?>" class="<?=$page>=$totalPages?'disabled':''?>">&raquo;</a>
</div>

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

<?php
    renderFooter();
}