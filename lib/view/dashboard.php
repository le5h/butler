<?php if (!defined('BUTLER_APP')) exit; ?>
<div class="range-bar">
<nav class="range-nav">
<?php foreach (['day','week','month','all'] as $r):
    $active = $r === $range ? ' active' : '';
    $href = '?stats&range=' . $r;
    if ($r === 'week') $href .= '&from=' . $defFromWeek;
    if ($r === 'month') $href .= '&from=' . $defFromMonth;
    ?>
<a href="<?=$href?>" class="<?=$active?>"><?=ucfirst($r)?></a>
<?php endforeach; ?>
</nav>
<?php if ($range !== 'all'): ?>
<div class="range-date-group">
<button class="range-btn" onclick="adjDate(-1)" type="button">&minus;</button>
<input type="date" class="range-date" id="range-date" value="<?=$from?:date('Y-m-d')?>" onchange="location='?stats&range=<?=$range?>&from='+this.value" title="Start date">
<button class="range-btn" onclick="adjDate(1)" type="button">&plus;</button>
<?php if ($from): ?><a href="?stats&range=<?=$range?>" class="range-date-clear">&times;</a><?php endif; ?>
</div>
<?php endif; ?>
</div>

<div class="report"><?=htmlspecialchars($report)?></div>

<div class="summary">
<div class="summary-card"><div class="num"><?=$stats['total_visits']?></div><div class="label">Visits</div></div>
<div class="summary-card"><div class="num"><?=$stats['typ_duration']?>s</div><div class="label">Typical Duration</div></div>
<div class="summary-card"><div class="num"><?=$stats['typ_interactions']?></div><div class="label">Typical Interactions</div></div>
<div class="summary-card"><div class="num"><?=htmlspecialchars($stats['top_language'] ?: '-')?></div><div class="label">Most Common Language</div></div>
<div class="summary-card"><div class="num"><?=htmlspecialchars($stats['top_os'] ?: '-')?></div><div class="label">Most Common OS</div></div>
</div>

<div class="quality-bar-wrap">
<?php $q = $stats['quality']; $qt = array_sum($q) ?: 1; ?>
<div class="quality-bar">
<?php foreach (['bad','poor','okay','super'] as $t): $pct = round($q[$t] / $qt * 100, 1); if ($pct < 0.1) continue; ?>
<span class="qb qb-<?=$t?>" style="width:<?=$pct?>%"></span>
<?php endforeach; ?>
</div>
<div class="quality-legend">
<?php foreach (['bad' => 'Bad','poor' => 'Poor','okay' => 'Okay','super' => 'Super'] as $k => $l): ?>
<span class="ql"><span class="ql-dot qb-<?=$k?>"></span><?=$l?> <?=$q[$k]?></span>
<?php endforeach; ?>
</div>
</div>

<div class="chart-wrap">
<canvas id="chart" height="250"></canvas>
</div>

<div class="table-wrap">
<table>
<thead><tr>
<th>Time</th><th>Page</th><th>Duration</th><th>Interactions</th><th>Quality</th><th>OS</th><th>Language</th><th>Timezone</th><th>Location</th><th>Subnet</th><th>ID</th>
</tr></thead>
<tbody>
<?php foreach ($visits as $v):
    $time = isset($v['timestamp']) ? date('Y-m-d H:i', $v['timestamp']) : '-';
    $dur = isset($v['duration']) ? round($v['duration'], 1) . 's' : '-';
    $intr = $v['interactions'] ?? 0;
    $qDur = (float)($v['duration'] ?? 0);
    $qCls = $qDur <= $minDur ? ($intr >= $minInt ? 'q-okay' : 'q-bad') : ($intr >= $minInt ? 'q-super' : 'q-poor');
    $qLbl = $qDur <= $minDur ? ($intr >= $minInt ? 'okay' : 'bad') : ($intr >= $minInt ? 'super' : 'poor');
    $lang = htmlspecialchars($v['lang'] ?? '-');
    $ip = htmlspecialchars($v['ip'] ?? '-');
    $geo = htmlspecialchars($v['geo'] ?? '-');
    $tz = htmlspecialchars($v['timezone'] ?? '-');
    $os = htmlspecialchars($v['os'] ?? '-');
    $url = htmlspecialchars($v['page'] ?? '-');
    $idFull = $v['id'] ?? '';
    $idColor = '#' . substr(str_replace('-', '', $idFull), -6);
?>
<tr><td data-label="Time"><?=$time?></td><td data-label="Page"><?=$url?></td><td data-label="Duration"><?=$dur?></td><td data-label="Interactions"><?=$intr?></td><td data-label="Quality"><span class="q-badge <?=$qCls?>"><?=$qLbl?></span></td><td data-label="OS"><?=$os?></td><td data-label="Language"><?=$lang?></td><td data-label="Timezone"><?=$tz?></td><td data-label="Location"><?=$geo?></td><td data-label="Subnet"><?=$ip?></td><td data-label="ID" title="<?=htmlspecialchars($idFull)?>"><span class="id-circle" style="background:<?=$idColor?>"></span></td></tr>
<?php endforeach; ?>
<?php if (empty($visits)): ?><tr class="empty"><td colspan="11">No visits recorded yet. Once your tracker is live, data will appear here.</td></tr><?php endif; ?>
</tbody>
</table>
</div>

<div class="pagination">
<a href="<?=$queryBase?>&page=1" class="<?=(int)$page<=1?'disabled':''?>">&laquo;</a>
<?php $p = (int)$page; $tp = (int)$totalPages; for ($i = max(1, $p-2); $i <= min($tp, $p+2); $i++): ?>
<a href="<?=$queryBase?>&page=<?=$i?>" class="<?=$i===$p?'active':''?>"><?=$i?></a>
<?php endfor; ?>
<a href="<?=$queryBase?>&page=<?=$tp?>" class="<?=$p>=$tp?'disabled':''?>">&raquo;</a>
</div>

<div class="toolbar">
<a href="<?=$queryBase?>&export=csv">Export CSV</a>
<a href="<?=$queryBase?>&export=json">Export JSON</a>
</div>

<script>
var chartData = <?=json_encode($chartData)?>;
var ctx = document.getElementById('chart').getContext('2d');
var chart = new Chart(ctx, {
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
        maintainAspectRatio: false,
        plugins: { legend: { position: 'top' } },
        scales: {
            y: { beginAtZero: true, position: 'left', title: { display: true, text: 'Visits' } },
            y1: { beginAtZero: true, position: 'right', grid: { drawOnChartArea: false }, title: { display: true, text: 'Seconds' } }
        }
    }
});
window.addEventListener('resize', function(){ chart.resize(); });
function adjDate(n){var e=document.getElementById('range-date'),d=new Date(e.value),r='<?=$range?>';d.setDate(d.getDate()+n);location='?stats&range='+r+'&from='+d.toISOString().slice(0,10)}
</script>
