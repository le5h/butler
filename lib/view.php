<?php

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

function renderViewDashboard(string $report, string $range, array $stats, array $chartData, array $visits, int $page, int $totalPages, string $queryBase, string $from = '', string $defFromWeek = '', string $defFromMonth = ''): void {
    global $config;
    $minDur = (int)($config['quality_min_duration'] ?? 10);
    $minInt = (int)($config['quality_min_interactions'] ?? 1);
    require __DIR__ . '/view/dashboard.php';
}

function serveView() {
    global $config;
    if (!checkAuth('view')) return;

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

    $queryBase = "?view&range=$range" . ($from ? "&from=$from" : '');

    $report = butlerReport($stats, $range);

    $now = new DateTime();
    $defFromWeek = (clone $now)->modify('monday this week')->format('Y-m-d');
    $defFromMonth = (clone $now)->modify('first day of this month')->format('Y-m-d');

    require_once __DIR__ . '/common.php';

    header('Content-Type: text/html; charset=utf-8');
    
    renderHead("Stats - $range");
    renderChartJs();
    renderTop('view');
    renderViewDashboard($report, $range, $stats, $chartData, $visits, $page, $totalPages, $queryBase, $from, $defFromWeek, $defFromMonth);
    renderFooter();
}