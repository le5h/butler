<?php

function sessionHash(string $input): string {
    $sum = 0;
    for ($i = 0; $i < strlen($input); $i += 3) {
        $sum += unpack('N', str_pad(substr($input, $i, 3), 4, "\x00", STR_PAD_LEFT))[1];
    }
    while ($sum > 0xFFFFFF) {
        $t = 0;
        while ($sum > 0) { $t += $sum & 0xFFFFFF; $sum >>= 24; }
        $sum = $t;
    }
    return sprintf('%06x', $sum);
}

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

class FileStorage {
    private string $eventDir;
    private string $sumDir;
    private int $minDur;
    private int $minInt;

    public function __construct(string $dir, array $config = []) {
        $dir = rtrim($dir, '/\\');
        $this->eventDir = $dir . '/events';
        $this->sumDir = $dir . '/summary';
        $this->minDur = (int)($config['quality_min_duration'] ?? 10);
        $this->minInt = (int)($config['quality_min_interactions'] ?? 1);
        foreach ([$dir, $this->eventDir, $this->sumDir] as $d) {
            if (!is_dir($d)) mkdir($d, 0777, true);
        }
    }

    private function ensureYearDir(string $date): void {
        $year = substr($date, 0, 4);
        foreach ([$this->eventDir, $this->sumDir] as $base) {
            $d = $base . '/' . $year;
            if (!is_dir($d)) mkdir($d, 0777, true);
        }
    }

    private function fileFor(string $date): string {
        return $this->eventDir . '/' . substr($date, 0, 4) . '/' . $date . '.log';
    }

    private function sumFor(string $date): string {
        return $this->sumDir . '/' . substr($date, 0, 4) . '/' . $date . '.json';
    }

    private function datesInRange(string $range, ?string $from = null): array {
        $now = new DateTime();
        if ($from !== null) {
            $start = new DateTime($from);
            $end = match ($range) {
                'day' => (clone $start)->modify('+1 day'),
                'week' => (clone $start)->modify('+7 days'),
                'month' => (clone $start)->modify('+30 days'),
                default => clone $now,
            };
            if ($start > $end) return [];
        } else {
            if ($range === 'day') return [date('Y-m-d')];
            $end = clone $now;
            $start = clone $end;
            switch ($range) {
                case 'week': $start->modify('-6 days'); break;
                case 'month': $start->modify('-29 days'); break;
                case 'all': return [];
            }
        }
        $dates = [];
        $endInclude = (clone $end)->modify('+1 day');
        foreach (new DatePeriod($start, new DateInterval('P1D'), $endInclude) as $d) {
            $dates[] = $d->format('Y-m-d');
        }
        return $dates;
    }

    private function readLines(string $file): array {
        $records = [];
        $fh = new SplFileObject($file, 'r');
        while (!$fh->eof()) {
            $line = trim($fh->fgets());
            if ($line === '') continue;
            $rec = json_decode($line, true);
            if ($rec && !empty($rec['id'])) {
                $records[$rec['id']] = array_merge($records[$rec['id']] ?? [], $rec);
            }
        }
        return array_values($records);
    }

    private function buildSummary(string $date): array {
        $file = $this->fileFor($date);
        if (!file_exists($file)) return [];
        $this->ensureYearDir($date);
        $records = $this->readLines($file);
        $summary = ['total' => count($records), 'durations' => [], 'interactions' => [],
                     'lang' => [], 'os' => [], 'quality' => [0, 0, 0, 0],
                     'totalDur' => 0, 'hours' => array_fill(0, 24, ['c' => 0, 'd' => 0.0])];
        foreach ($records as $r) {
            $d = (float)($r['duration'] ?? 0);
            $n = (int)($r['interactions'] ?? 0);
            if (!empty($r['duration'])) {
                $summary['durations'][] = $d;
                $summary['totalDur'] += $d;
            }
            if (isset($r['interactions'])) $summary['interactions'][] = $n;
            if (!empty($r['lang'])) $summary['lang'][$r['lang']] = ($summary['lang'][$r['lang']] ?? 0) + 1;
            if (!empty($r['os'])) $summary['os'][$r['os']] = ($summary['os'][$r['os']] ?? 0) + 1;
            $qi = $d <= $this->minDur ? ($n >= $this->minInt ? 1 : 3) : ($n >= $this->minInt ? 0 : 2);
            $summary['quality'][$qi]++;
            $h = (int)date('G', $r['timestamp']);
            $summary['hours'][$h]['c']++;
            if (!empty($r['duration'])) $summary['hours'][$h]['d'] += $d;
        }
        file_put_contents($this->sumFor($date), json_encode($summary), LOCK_EX);
        return $summary;
    }

    private function getSummary(string $date): array {
        $sumFile = $this->sumFor($date);
        $dataFile = $this->fileFor($date);
        if (!file_exists($dataFile)) return [];
        $mtime = @filemtime($dataFile);
        if ($mtime && file_exists($sumFile) && @filemtime($sumFile) >= $mtime) {
            $cached = @file_get_contents($sumFile);
            if ($cached !== false) return json_decode($cached, true) ?? [];
        }
        return $this->buildSummary($date);
    }

    private function summariesInRange(string $range, ?string $from = null): array {
        $dates = $this->datesInRange($range, $from);
        if (empty($dates)) {
            if ($from !== null) return [];
            $files = glob($this->sumDir . '/*/*.json');
            sort($files);
            $summaries = [];
            foreach ($files as $f) {
                $date = basename($f, '.json');
                $s = $this->getSummary($date);
                if (!empty($s)) $summaries[$date] = $s;
            }
            return $summaries;
        }
        $summaries = [];
        foreach ($dates as $d) {
            $s = $this->getSummary($d);
            if (!empty($s)) $summaries[$d] = $s;
        }
        return $summaries;
    }

    public function newVisit(array $data, ?string $sessionHash = null): string {
        $date = date('Y-m-d');
        $this->ensureYearDir($date);
        $hash = $sessionHash ?? bin2hex(random_bytes(4));
        $id = str_replace('.', '', microtime(true)) . '-' . $hash;
        $data['id'] = $id;
        $data['timestamp'] = time();
        $line = json_encode(array_filter($data, fn($v) => $v !== ''), JSON_UNESCAPED_SLASHES) . "\n";
        file_put_contents($this->fileFor($date), $line, FILE_APPEND | LOCK_EX);
        return $id;
    }

    public function updateVisit(string $id, array $data): bool {
        $date = date('Y-m-d', (int)substr($id, 0, 10));
        $this->ensureYearDir($date);
        $data['id'] = $id;
        $line = json_encode(array_filter($data, fn($v) => $v !== ''), JSON_UNESCAPED_SLASHES) . "\n";
        $file = $this->fileFor($date);
        if (!file_exists($file)) return false;
        file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
        return true;
    }

    public function getVisits(string $range, int $page, int $perPage, ?string $from = null): array {
        $dates = $this->datesInRange($range, $from);
        if (empty($dates)) {
            if ($from !== null) return [];
            $files = glob($this->eventDir . '/*/*.log');
            rsort($files);
        } else {
            $files = [];
            foreach (array_reverse($dates) as $d) {
                $f = $this->fileFor($d);
                if (file_exists($f)) $files[] = $f;
            }
        }
        $records = [];
        $needed = $page * $perPage;
        foreach ($files as $file) {
            foreach ($this->readLines($file) as $r) {
                $records[$r['id']] = array_merge($records[$r['id']] ?? [], $r);
            }
            if (count($records) >= $needed) break;
        }
        if (empty($records)) return [];
        $records = array_values($records);
        usort($records, fn($a, $b) => ($b['timestamp'] ?? 0) - ($a['timestamp'] ?? 0));
        return array_slice($records, ($page - 1) * $perPage, $perPage);
    }

    public function getVisitCount(string $range, ?string $from = null): int {
        $total = 0;
        foreach ($this->summariesInRange($range, $from) as $s) $total += $s['total'];
        return $total;
    }

    public function getStats(string $range, ?string $from = null): array {
        $summaries = $this->summariesInRange($range, $from);
        if (empty($summaries)) {
            return ['total_visits' => 0, 'typ_duration' => 0, 'typ_interactions' => 0,
                    'top_language' => '', 'top_os' => '',
                    'quality' => ['super' => 0, 'okay' => 0, 'poor' => 0, 'bad' => 0]];
        }
        $total = 0;
        $durations = [];
        $interactions = [];
        $lang = [];
        $os = [];
        $quality = [0, 0, 0, 0];
        $qKeys = ['super', 'okay', 'poor', 'bad'];
        foreach ($summaries as $s) {
            $total += $s['total'];
            $durations = array_merge($durations, $s['durations']);
            $interactions = array_merge($interactions, $s['interactions']);
            foreach ($s['lang'] as $k => $v) $lang[$k] = ($lang[$k] ?? 0) + $v;
            foreach ($s['os'] as $k => $v) $os[$k] = ($os[$k] ?? 0) + $v;
            foreach ($s['quality'] as $i => $v) $quality[$i] += $v;
        }
        $topKey = fn($c) => $c ? array_key_first(arsort($c) ? $c : []) : '';
        $q = array_combine($qKeys, $quality);
        return [
            'total_visits' => $total,
            'typ_duration' => round(trimmedMean($durations), 1),
            'typ_interactions' => round(trimmedMean($interactions), 1),
            'top_language' => $topKey($lang),
            'top_os' => $topKey($os),
            'quality' => $q,
        ];
    }

    public function getAggregatedStats(string $range, ?string $from = null): array {
        if ($range === 'day') {
            $sums = array_fill(0, 24, 0.0);
            $buckets = array_fill(0, 24, ['count' => 0, 'avg' => 0]);
            foreach ($this->summariesInRange($range, $from) as $s) {
                foreach ($s['hours'] as $h => $hb) {
                    $buckets[$h]['count'] += $hb['c'];
                    $sums[$h] += $hb['d'];
                }
            }
            foreach ($buckets as $h => &$b) {
                $b['avg'] = $b['count'] > 0 ? round($sums[$h] / $b['count'], 1) : 0;
            }
            return fillChartGaps($buckets, $range, $from);
        }
        $buckets = [];
        foreach ($this->summariesInRange($range, $from) as $date => $s) {
            $buckets[$date] = [
                'count' => $s['total'],
                'avg' => $s['total'] > 0 ? round($s['totalDur'] / $s['total'], 1) : 0,
            ];
        }
        return fillChartGaps($buckets, $range, $from);
    }

    public function cleanup(int $retentionDays): int {
        if ($retentionDays <= 0) return 0;
        $cutoff = time() - $retentionDays * 86400;
        $removed = 0;
        foreach (glob($this->eventDir . '/*/*.log') as $file) {
            $ts = strtotime(basename($file, '.log'));
            if ($ts !== false && $ts < $cutoff) {
                unlink($file);
                $sf = $this->sumFor(basename($file, '.log'));
                if (file_exists($sf)) unlink($sf);
                $removed++;
            }
        }
        return $removed;
    }
}

class SqliteStorage {
    private \PDO $pdo;
    private string $dir;
    private int $minDur;
    private int $minInt;

    public function __construct(string $dir, array $config = []) {
        $this->dir = rtrim($dir, '/\\');
        $this->minDur = (int)($config['quality_min_duration'] ?? 10);
        $this->minInt = (int)($config['quality_min_interactions'] ?? 1);
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0777, true);
        }
        $dbFile = $this->dir . '/stats.db';
        $this->pdo = new \PDO("sqlite:$dbFile");
        $this->pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);
        $this->pdo->exec("CREATE TABLE IF NOT EXISTS visits (
            id TEXT PRIMARY KEY,
            timestamp INTEGER NOT NULL,
            duration REAL DEFAULT 0,
            interactions INTEGER DEFAULT 0,
            lang TEXT DEFAULT '',
            ip TEXT DEFAULT '',
            geo TEXT DEFAULT '',
            timezone TEXT DEFAULT '',
            os TEXT DEFAULT '',
            referrer TEXT DEFAULT '',
            page TEXT DEFAULT ''
        )");
        $this->pdo->exec("CREATE INDEX IF NOT EXISTS idx_visits_timestamp ON visits(timestamp)");
        $this->migrateSchema();
    }

    private function migrateSchema(): void {
        foreach (['geo', 'timezone'] as $col) {
            try {
                $this->pdo->query("SELECT $col FROM visits LIMIT 1");
            } catch (\PDOException $e) {
                $this->pdo->exec("ALTER TABLE visits ADD COLUMN $col TEXT DEFAULT ''");
            }
        }
    }

    public function newVisit(array $data, ?string $sessionHash = null): string {
        $hash = $sessionHash ?? bin2hex(random_bytes(4));
        $id = str_replace('.', '', microtime(true)) . '-' . $hash;
        $stmt = $this->pdo->prepare("INSERT INTO visits (id, timestamp, lang, ip, geo, timezone, os, referrer, page)
            VALUES (:id, :timestamp, :lang, :ip, :geo, :timezone, :os, :referrer, :page)");
        $stmt->execute([
            ':id' => $id,
            ':timestamp' => time(),
            ':lang' => $data['lang'] ?? '',
            ':ip' => $data['ip'] ?? '',
            ':geo' => $data['geo'] ?? '',
            ':timezone' => $data['timezone'] ?? '',
            ':os' => $data['os'] ?? '',
            ':referrer' => $data['referrer'] ?? '',
            ':page' => $data['page'] ?? '',
        ]);
        return $id;
    }

    public function updateVisit(string $id, array $data): bool {
        $fields = [];
        $params = [':id' => $id];
        foreach (['duration', 'interactions'] as $key) {
            if (isset($data[$key])) {
                $fields[] = "$key = :$key";
                $params[":$key"] = $data[$key];
            }
        }
        if (empty($fields)) return false;
        $sql = "UPDATE visits SET " . implode(', ', $fields) . " WHERE id = :id";
        $stmt = $this->pdo->prepare($sql);
        return $stmt->execute($params);
    }

    public function getVisits(string $range, int $page, int $perPage, ?string $from = null): array {
        $where = $this->rangeWhere($range, $from);
        $offset = ($page - 1) * $perPage;
        $stmt = $this->pdo->prepare("SELECT * FROM visits $where ORDER BY timestamp DESC LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getVisitCount(string $range, ?string $from = null): int {
        $where = $this->rangeWhere($range, $from);
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM visits $where");
        return (int)$stmt->fetchColumn();
    }

    public function getStats(string $range, ?string $from = null): array {
        $where = $this->rangeWhere($range, $from);
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM visits $where");
        $total = (int)$stmt->fetchColumn();

        $wDur = $where ? "$where AND duration > 0" : "WHERE duration > 0";
        $wAll = $where ? $where : '';
        $durations = $this->pdo->query("SELECT duration FROM visits $wDur ORDER BY duration")
            ->fetchAll(\PDO::FETCH_COLUMN);
        $interactions = $this->pdo->query("SELECT interactions FROM visits $wAll ORDER BY interactions")
            ->fetchAll(\PDO::FETCH_COLUMN);

        $wLang = $where ? "$where AND lang != ''" : "WHERE lang != ''";
        $wOs = $where ? "$where AND os != ''" : "WHERE os != ''";
        $topLang = $this->pdo->query("SELECT lang, COUNT(*) as c FROM visits $wLang GROUP BY lang ORDER BY c DESC LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
        $topOs = $this->pdo->query("SELECT os, COUNT(*) as c FROM visits $wOs GROUP BY os ORDER BY c DESC LIMIT 1")->fetch(\PDO::FETCH_ASSOC);

        $wQ = $where ? $where : '';
        $quality = ['super' => 0, 'okay' => 0, 'poor' => 0, 'bad' => 0];
        $qStmt = $this->pdo->prepare(
            "SELECT CASE WHEN duration <= :minDur AND interactions < :minInt THEN 'bad'
                         WHEN duration <= :minDur AND interactions >= :minInt THEN 'okay'
                         WHEN duration > :minDur AND interactions < :minInt THEN 'poor'
                         WHEN duration > :minDur AND interactions >= :minInt THEN 'super'
                    END as q, COUNT(*) as c
             FROM visits $wQ GROUP BY q"
        );
        $qStmt->execute([':minDur' => $this->minDur, ':minInt' => $this->minInt]);
        foreach ($qStmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $quality[$row['q']] = (int)$row['c'];
        }

        return [
            'total_visits' => $total,
            'typ_duration' => round(trimmedMean($durations), 1),
            'typ_interactions' => round(trimmedMean($interactions), 1),
            'top_language' => $topLang ? $topLang['lang'] : '',
            'top_os' => $topOs ? $topOs['os'] : '',
            'quality' => $quality,
        ];
    }

    public function getAggregatedStats(string $range, ?string $from = null): array {
        $where = $this->rangeWhere($range, $from);
        $groupExpr = $range === 'day'
            ? "CAST(strftime('%H', timestamp, 'unixepoch') AS INTEGER)"
            : "strftime('%Y-%m-%d', timestamp, 'unixepoch')";
        $rows = $this->pdo->query(
            "SELECT $groupExpr as lbl, COUNT(*) as cnt, AVG(duration) as avg_dur
             FROM visits $where GROUP BY lbl ORDER BY lbl"
        )->fetchAll(\PDO::FETCH_ASSOC);

        $buckets = [];
        foreach ($rows as $r) {
            $buckets[$r['lbl']] = [
                'count' => (int)$r['cnt'],
                'avg' => $r['avg_dur'] ? round((float)$r['avg_dur'], 1) : 0,
            ];
        }
        return fillChartGaps($buckets, $range, $from);
    }

    public function cleanup(int $retentionDays): int {
        if ($retentionDays <= 0) return 0;
        $cutoff = time() - $retentionDays * 86400;
        $stmt = $this->pdo->prepare("DELETE FROM visits WHERE timestamp < :cutoff");
        $stmt->execute([':cutoff' => $cutoff]);
        $deleted = $stmt->rowCount();
        if ($deleted > 0) $this->pdo->exec("VACUUM");
        return $deleted;
    }

    private function rangeWhere(string $range, ?string $from = null): string {
        $now = time();
        if ($from !== null) {
            $startTs = strtotime($from);
            if ($startTs === false) return 'WHERE 1=0';
            $endTs = match ($range) {
                'day' => $startTs + 86400,
                'week' => $startTs + 604800,
                'month' => $startTs + 2592000,
                default => $now,
            };
            if ($endTs > $now) $endTs = $now;
            return "WHERE timestamp >= $startTs AND timestamp < $endTs";
        }
        $ts = $range === 'day' ? strtotime('today') : match ($range) {
            'week' => $now - 604800,
            'month' => $now - 2592000,
            default => 0,
        };
        return $ts > 0 ? "WHERE timestamp >= $ts" : '';
    }
}

function createStorage(array $config): FileStorage|SqliteStorage {
    $dir = __DIR__ . '/../data';
    try {
        return $config['storage'] === 'sqlite'
            ? new SqliteStorage($dir, $config)
            : new FileStorage($dir, $config);
    } catch (\Exception $e) {
        return new FileStorage($dir, $config);
    }
}
