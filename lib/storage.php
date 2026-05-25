<?php

abstract class Storage
{
    abstract public function newVisit(array $data): string;
    abstract public function updateVisit(string $id, array $data): bool;
    abstract public function getVisits(string $range, int $page, int $perPage): array;
    abstract public function getVisitCount(string $range): int;
    abstract public function getStats(string $range): array;
    abstract public function getAggregatedStats(string $range): array;
}

class FileStorage extends Storage
{
    private string $dir;

    public function __construct(string $dir)
    {
        $this->dir = rtrim($dir, '/\\');
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0777, true);
        }
    }

    private function fileFor(string $date): string
    {
        return $this->dir . '/' . $date . '.txt';
    }

    private function dateRange(string $range): array
    {
        $dates = [];
        $end = new DateTime();
        $start = clone $end;
        switch ($range) {
            case 'day':
                $start->modify('-1 day');
                break;
            case 'week':
                $start->modify('-7 days');
                break;
            case 'month':
                $start->modify('-30 days');
                break;
            case 'all':
                return []; // empty means all
        }
        $period = new DatePeriod($start, new DateInterval('P1D'), $end);
        foreach ($period as $d) {
            $dates[] = $d->format('Y-m-d');
        }
        $dates[] = $end->format('Y-m-d');
        return $dates;
    }

    public function newVisit(array $data): string
    {
        $id = str_replace('.', '', microtime(true)) . '-' . bin2hex(random_bytes(4));
        $data['id'] = $id;
        $data['timestamp'] = time();
        $line = $id . "\t" . json_encode($data) . "\n";
        $file = $this->fileFor(date('Y-m-d'));
        file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
        return $id;
    }

    public function updateVisit(string $id, array $data): bool
    {
        $files = glob($this->dir . '/*.txt');
        sort($files);
        foreach ($files as $file) {
            $lines = file($file, FILE_IGNORE_NEW_LINES);
            $changed = false;
            foreach ($lines as $i => $line) {
                $parts = explode("\t", $line, 2);
                if ($parts[0] === $id) {
                    $record = json_decode($parts[1], true);
                    foreach ($data as $k => $v) {
                        $record[$k] = $v;
                    }
                    $lines[$i] = $id . "\t" . json_encode($record);
                    $changed = true;
                    break;
                }
            }
            if ($changed) {
                file_put_contents($file, implode("\n", $lines) . "\n");
                return true;
            }
        }
        return false;
    }

    private function readRecords(string $range): array
    {
        $records = [];
        $dates = $this->dateRange($range);

        if (empty($dates)) {
            $files = glob($this->dir . '/*.txt');
        } else {
            $files = [];
            foreach ($dates as $d) {
                $f = $this->fileFor($d);
                if (file_exists($f)) $files[] = $f;
            }
        }

        sort($files);
        foreach ($files as $file) {
            $lines = file($file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $parts = explode("\t", $line, 2);
                if (count($parts) === 2) {
                    $record = json_decode($parts[1], true);
                    if ($record) $records[] = $record;
                }
            }
        }
        return $records;
    }

    public function getVisits(string $range, int $page, int $perPage): array
    {
        $records = $this->readRecords($range);
        usort($records, fn($a, $b) => ($b['timestamp'] ?? 0) - ($a['timestamp'] ?? 0));
        $offset = ($page - 1) * $perPage;
        return array_slice($records, $offset, $perPage);
    }

    public function getVisitCount(string $range): int
    {
        return count($this->readRecords($range));
    }

    public function getStats(string $range): array
    {
        $records = $this->readRecords($range);
        $total = count($records);
        $sumDuration = 0;
        $countDuration = 0;
        foreach ($records as $r) {
            if (!empty($r['duration'])) {
                $sumDuration += (float)$r['duration'];
                $countDuration++;
            }
        }
        return [
            'total_visits' => $total,
            'avg_duration' => $countDuration > 0 ? round($sumDuration / $countDuration, 2) : 0,
        ];
    }

    public function getAggregatedStats(string $range): array
    {
        $records = $this->readRecords($range);

        if ($range === 'day') {
            $buckets = array_fill(0, 24, ['count' => 0, 'dur' => 0]);
            foreach ($records as $r) {
                $h = (int)date('G', $r['timestamp']);
                $buckets[$h]['count']++;
                if (!empty($r['duration'])) $buckets[$h]['dur'] += (float)$r['duration'];
            }
            $labels = $counts = $durations = [];
            for ($h = 0; $h < 24; $h++) {
                $labels[] = sprintf('%02d:00', $h);
                $counts[] = $buckets[$h]['count'];
                $durations[] = $buckets[$h]['count'] > 0 ? round($buckets[$h]['dur'] / $buckets[$h]['count'], 1) : 0;
            }
            return compact('labels', 'counts', 'durations');
        }

        $buckets = [];
        foreach ($records as $r) {
            $d = date('Y-m-d', $r['timestamp']);
            if (!isset($buckets[$d])) $buckets[$d] = ['count' => 0, 'dur' => 0];
            $buckets[$d]['count']++;
            if (!empty($r['duration'])) $buckets[$d]['dur'] += (float)$r['duration'];
        }

        if ($range === 'all') {
            ksort($buckets);
            $labels = $counts = $durations = [];
            foreach ($buckets as $d => $b) {
                $labels[] = date('M j', strtotime($d));
                $counts[] = $b['count'];
                $durations[] = $b['count'] > 0 ? round($b['dur'] / $b['count'], 1) : 0;
            }
            return compact('labels', 'counts', 'durations');
        }

        $end = new DateTime();
        $start = clone $end;
        $days = $range === 'month' ? 29 : 6;
        $start->modify("-{$days} days");

        $labels = $counts = $durations = [];
        $period = new DatePeriod($start, new DateInterval('P1D'), $end);
        foreach ($period as $d) {
            $date = $d->format('Y-m-d');
            $labels[] = $d->format('M j');
            $b = $buckets[$date] ?? ['count' => 0, 'dur' => 0];
            $counts[] = $b['count'];
            $durations[] = $b['count'] > 0 ? round($b['dur'] / $b['count'], 1) : 0;
        }
        $labels[] = $end->format('M j');
        $date = $end->format('Y-m-d');
        $b = $buckets[$date] ?? ['count' => 0, 'dur' => 0];
        $counts[] = $b['count'];
        $durations[] = $b['count'] > 0 ? round($b['dur'] / $b['count'], 1) : 0;

        return compact('labels', 'counts', 'durations');
    }
}

class SqliteStorage extends Storage
{
    private \PDO $pdo;

    public function __construct(string $dir)
    {
        $dir = rtrim($dir, '/\\');
        if (!is_dir($dir)) {
            mkdir($dir, 0777, true);
        }
        $dbFile = $dir . '/stats.db';
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
            os TEXT DEFAULT '',
            referrer TEXT DEFAULT '',
            screen TEXT DEFAULT '',
            page TEXT DEFAULT ''
        )");
        try {
            $this->pdo->exec("ALTER TABLE visits ADD COLUMN geo TEXT DEFAULT ''");
        } catch (\PDOException $e) {
        }
    }

    public function newVisit(array $data): string
    {
        $id = str_replace('.', '', microtime(true)) . '-' . bin2hex(random_bytes(4));
        $stmt = $this->pdo->prepare("INSERT INTO visits (id, timestamp, lang, ip, geo, os, referrer, screen, page)
            VALUES (:id, :timestamp, :lang, :ip, :geo, :os, :referrer, :screen, :page)");
        $stmt->execute([
            ':id' => $id,
            ':timestamp' => time(),
            ':lang' => $data['lang'] ?? '',
            ':ip' => $data['ip'] ?? '',
            ':geo' => $data['geo'] ?? '',
            ':os' => $data['os'] ?? '',
            ':referrer' => $data['referrer'] ?? '',
            ':screen' => $data['screen'] ?? '',
            ':page' => $data['page'] ?? '',
        ]);
        return $id;
    }

    public function updateVisit(string $id, array $data): bool
    {
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

    public function getVisits(string $range, int $page, int $perPage): array
    {
        $where = $this->rangeWhere($range);
        $offset = ($page - 1) * $perPage;
        $stmt = $this->pdo->prepare("SELECT * FROM visits $where ORDER BY timestamp DESC LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getVisitCount(string $range): int
    {
        $where = $this->rangeWhere($range);
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM visits $where");
        return (int)$stmt->fetchColumn();
    }

    public function getStats(string $range): array
    {
        $where = $this->rangeWhere($range);
        $stmt = $this->pdo->query("SELECT COUNT(*) as total, AVG(duration) as avg_dur FROM visits $where");
        $row = $stmt->fetch(\PDO::FETCH_ASSOC);
        return [
            'total_visits' => (int)$row['total'],
            'avg_duration' => $row['avg_dur'] ? round((float)$row['avg_dur'], 2) : 0,
        ];
    }

    public function getAggregatedStats(string $range): array
    {
        $where = $this->rangeWhere($range);

        if ($range === 'day') {
            $sql = "SELECT CAST(strftime('%H', timestamp, 'unixepoch') AS INTEGER) as h,
                           COUNT(*) as cnt, AVG(duration) as avg_dur
                    FROM visits $where GROUP BY h ORDER BY h";
        } else {
            $sql = "SELECT strftime('%Y-%m-%d', timestamp, 'unixepoch') as d,
                           COUNT(*) as cnt, AVG(duration) as avg_dur
                    FROM visits $where GROUP BY d ORDER BY d";
        }

        $stmt = $this->pdo->query($sql);
        $rows = $stmt->fetchAll(\PDO::FETCH_ASSOC);

        if ($range === 'day') {
            $labels = $counts = $durations = [];
            for ($h = 0; $h < 24; $h++) {
                $labels[] = sprintf('%02d:00', $h);
                $counts[] = 0;
                $durations[] = 0;
            }
            foreach ($rows as $r) {
                $h = (int)$r['h'];
                $counts[$h] = (int)$r['cnt'];
                $avg = $r['avg_dur'];
                $durations[$h] = $avg ? round((float)$avg, 1) : 0;
            }
            return compact('labels', 'counts', 'durations');
        }

        $buckets = [];
        foreach ($rows as $r) {
            $buckets[$r['d']] = [
                'count' => (int)$r['cnt'],
                'dur' => $r['avg_dur'] ? round((float)$r['avg_dur'], 1) : 0,
            ];
        }

        if ($range === 'all') {
            ksort($buckets);
            $labels = $counts = $durations = [];
            foreach ($buckets as $d => $b) {
                $labels[] = date('M j', strtotime($d));
                $counts[] = $b['count'];
                $durations[] = $b['dur'];
            }
            return compact('labels', 'counts', 'durations');
        }

        $end = new DateTime();
        $start = clone $end;
        $days = $range === 'month' ? 29 : 6;
        $start->modify("-{$days} days");

        $labels = $counts = $durations = [];
        $period = new DatePeriod($start, new DateInterval('P1D'), $end);
        foreach ($period as $d) {
            $date = $d->format('Y-m-d');
            $labels[] = $d->format('M j');
            $b = $buckets[$date] ?? ['count' => 0, 'dur' => 0];
            $counts[] = $b['count'];
            $durations[] = $b['dur'];
        }
        $labels[] = $end->format('M j');
        $date = $end->format('Y-m-d');
        $b = $buckets[$date] ?? ['count' => 0, 'dur' => 0];
        $counts[] = $b['count'];
        $durations[] = $b['dur'];

        return compact('labels', 'counts', 'durations');
    }

    private function rangeWhere(string $range): string
    {
        $seconds = match ($range) {
            'day' => 86400,
            'week' => 604800,
            'month' => 2592000,
            default => 0,
        };
        if ($seconds === 0) return '';
        $ts = time() - $seconds;
        return "WHERE timestamp >= $ts";
    }
}

function createStorage(array $config): Storage
{
    $dir = __DIR__ . '/../data';
    try {
        return $config['storage'] === 'sqlite'
            ? new SqliteStorage($dir)
            : new FileStorage($dir);
    } catch (\Exception $e) {
        return new FileStorage($dir);
    }
}
