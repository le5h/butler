<?php

require_once __DIR__ . '/storage.php';
require_once __DIR__ . '/auth.php';

function fillChartGaps(array $buckets, string $range, ?string $from = null): array {
    if ($range === 'day') {
        $labels = $counts = $durations = [];
        for ($h = 0; $h < 24; $h++) {
            $labels[] = sprintf('%02d:00', $h);
            $b = $buckets[$h] ?? ['count' => 0, 'avg' => 0];
            $counts[] = $b['count'];
            $durations[] = $b['avg'];
        }
        return compact('labels', 'counts', 'durations');
    }
    if ($range === 'all') {
        if (empty($buckets)) return ['labels' => [], 'counts' => [], 'durations' => []];
        ksort($buckets);
        $keys = array_keys($buckets);
        $start = new DateTime(min($keys));
        $end = (new DateTime(max($keys)))->modify('+1 day');
        $labels = $counts = $durations = [];
        foreach (new DatePeriod($start, new DateInterval('P1D'), $end) as $d) {
            $date = $d->format('Y-m-d');
            $labels[] = $d->format('M j');
            $b = $buckets[$date] ?? ['count' => 0, 'avg' => 0];
            $counts[] = $b['count'];
            $durations[] = $b['avg'];
        }
        return compact('labels', 'counts', 'durations');
    }
    if ($from !== null) {
        $start = new DateTime($from);
        $end = (clone $start)->modify($range === 'month' ? '+30 days' : '+7 days');
    } else {
        $end = new DateTime();
        $start = (clone $end)->modify($range === 'month' ? '-29 days' : '-6 days');
    }
    $labels = $counts = $durations = [];
    $endInc = (clone $end)->modify('+1 day');
    foreach (new DatePeriod($start, new DateInterval('P1D'), $endInc) as $d) {
        $date = $d->format('Y-m-d');
        $labels[] = $d->format('M j');
        $b = $buckets[$date] ?? ['count' => 0, 'avg' => 0];
        $counts[] = $b['count'];
        $durations[] = $b['avg'];
    }
    return compact('labels', 'counts', 'durations');
}

function trimmedMean(array $vals): float {
    $n = count($vals);
    if ($n === 0) return 0;
    sort($vals);
    $trim = max(1, (int)ceil($n * 0.05));
    $keep = array_slice($vals, $trim, $n - 2 * $trim);
    return empty($keep) ? (float)$vals[(int)floor($n / 2)] : array_sum($keep) / count($keep);
}

function butlerReport(array $stats, string $range): string {
    $hour = (int)date('G');
    $e = $hour < 12 ? "\u{2615}" : ($hour < 18 ? "\u{1F324}" : "\u{1F319}");
    $t = $hour < 12 ? 'Good morning' : ($hour < 18 ? 'Good afternoon' : 'Good evening');
    $n = $stats['total_visits'];
    $s = (int)round($stats['typ_duration']);
    $d = $s >= 60 ? floor($s / 60) . 'm ' . ($s % 60) . 's' : $s . 's';
    $p = $range === 'day' ? 'today' : ($range === 'week' ? 'this week' : ($range === 'month' ? 'this month' : 'all time'));
    $bad = $stats['quality']['bad'] ?? 0;
    if ($n === 0) return "$e $t, sir. Nothing to report $p.";
    if ($n === 1) return "$e $t, sir. One visit $p" . ($s > 0 ? ", stayed $d." : ".");
    $m = "$e $t, sir. $n visits $p, stay around $d";
    if ($bad > 0) $m .= ", $bad declined \u{1F6AB}";
    if ($s > 60) return "$m \u{1F44D}.";
    if ($n > 50) return "$m. Busy \u{1F4C8}.";
    return "$m.";
}

function renderDashboard(string $report, string $range, array $stats, array $chartData, array $visits, int $page, int $totalPages, string $queryBase, string $from = '', string $defFromWeek = '', string $defFromMonth = ''): void {
    global $config;
    $minDur = (int)($config['quality_min_duration'] ?? 10);
    $minInt = (int)($config['quality_min_interactions'] ?? 1);
    require __DIR__ . '/view/dashboard.php';
}

function serveStats() {
    global $config;
    if (!checkAuth('stats')) return;

    $storage = createStorage($config);
    if ($config['retention_days'] > 0) {
        $storage->cleanup($config['retention_days']);
    }
    $range = $_GET['range'] ?? 'day';
    $from = $_GET['from'] ?? '';
    $page = max(1, (int)($_GET['page'] ?? 1));
    $perPage = 20;

    $export = $_GET['export'] ?? '';
    $exportLimit = (int)($config['export_limit'] ?? 10000);
    if ($export === 'csv' || $export === 'json') {
        $visits = $storage->getVisits($range, 1, $exportLimit, $from ?: null);
        if ($export === 'json') {
            header('Content-Type: application/json; charset=utf-8');
            header('Content-Disposition: attachment; filename="stats-' . $range . '-' . date('Y-m-d') . '.json"');
            echo json_encode($visits, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        } else {
            header('Content-Type: text/csv; charset=utf-8');
            header('Content-Disposition: attachment; filename="stats-' . $range . '-' . date('Y-m-d') . '.csv"');
            $out = fopen('php://output', 'w');
            fputcsv($out, ['ID', 'Timestamp', 'Duration', 'Interactions', 'Language', 'Timezone', 'IP', 'Location', 'OS', 'Referrer', 'Page'], ',', '"', '');
            foreach ($visits as $v) {
                fputcsv($out, [
                    $v['id'] ?? '',
                    date('Y-m-d H:i:s', $v['timestamp'] ?? 0),
                    $v['duration'] ?? '',
                    $v['interactions'] ?? '',
                    $v['lang'] ?? '',
                    $v['timezone'] ?? '',
                    $v['ip'] ?? '',
                    $v['geo'] ?? '',
                    $v['os'] ?? '',
                    $v['referrer'] ?? '',
                    $v['page'] ?? '',
                ], ',', '"', '');
            }
            fclose($out);
        }
        return;
    }

    $fromParam = $from ?: null;
    $total = $storage->getVisitCount($range, $fromParam);
    $stats = $storage->getStats($range, $fromParam);
    $visits = $storage->getVisits($range, $page, $perPage, $fromParam);
    $totalPages = max(1, (int)ceil($total / $perPage));
    $chartData = $storage->getAggregatedStats($range, $fromParam);

    $queryBase = "?stats&range=$range" . ($from ? "&from=$from" : '');

    $report = butlerReport($stats, $range);

    $now = new DateTime();
    $defFromWeek = (clone $now)->modify('monday this week')->format('Y-m-d');
    $defFromMonth = (clone $now)->modify('first day of this month')->format('Y-m-d');

    require_once __DIR__ . '/layout.php';

    header('Content-Type: text/html; charset=utf-8');
    
    renderHead("Stats - $range", 'stats');

    renderDashboard($report, $range, $stats, $chartData, $visits, $page, $totalPages, $queryBase, $from, $defFromWeek, $defFromMonth);

    renderFooter(true);
}
