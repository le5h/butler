<?php

abstract class Storage {
    abstract public function newVisit(array $data): string;
    abstract public function updateVisit(string $id, array $data): bool;
    abstract public function getVisits(string $range, int $page, int $perPage): array;
    abstract public function getVisitCount(string $range): int;
    abstract public function getStats(string $range): array;
    abstract public function getAggregatedStats(string $range): array;
    abstract public function cleanup(int $retentionDays): int;

    protected static function fillChartGaps(array $buckets, string $range): array {
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
            ksort($buckets);
            $labels = $counts = $durations = [];
            foreach ($buckets as $key => $b) {
                $labels[] = date('M j', strtotime($key));
                $counts[] = $b['count'];
                $durations[] = $b['avg'];
            }
            return compact('labels', 'counts', 'durations');
        }

        $end = new DateTime();
        $start = clone $end;
        $start->modify($range === 'month' ? '-29 days' : '-6 days');

        $labels = $counts = $durations = [];
        $period = new DatePeriod($start, new DateInterval('P1D'), $end);
        foreach ($period as $d) {
            $date = $d->format('Y-m-d');
            $labels[] = $d->format('M j');
            $b = $buckets[$date] ?? ['count' => 0, 'avg' => 0];
            $counts[] = $b['count'];
            $durations[] = $b['avg'];
        }
        $labels[] = $end->format('M j');
        $b = $buckets[$end->format('Y-m-d')] ?? ['count' => 0, 'avg' => 0];
        $counts[] = $b['count'];
        $durations[] = $b['avg'];

        return compact('labels', 'counts', 'durations');
    }
}

class FileStorage extends Storage {
    private string $dir;
    private ?array $recordsCache = null;
    private string $cacheRange = '';

    public function __construct(string $dir) {
        $this->dir = rtrim($dir, '/\\');
        if (!is_dir($this->dir)) {
            mkdir($this->dir, 0777, true);
        }
    }

    private function fileFor(string $date): string {
        return $this->dir . '/' . $date . '.txt';
    }

    private function dateRange(string $range): array {
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

    public function newVisit(array $data): string {
        $this->recordsCache = null;
        $id = str_replace('.', '', microtime(true)) . '-' . bin2hex(random_bytes(4));
        $data['id'] = $id;
        $data['timestamp'] = time();
        $line = $id . "\t" . json_encode($data) . "\n";
        $file = $this->fileFor(date('Y-m-d'));
        file_put_contents($file, $line, FILE_APPEND | LOCK_EX);
        return $id;
    }

    public function updateVisit(string $id, array $data): bool {
        $this->recordsCache = null;
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

    private function readRecords(string $range): array {
        if ($this->recordsCache !== null && $this->cacheRange === $range) {
            return $this->recordsCache;
        }

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

        $this->recordsCache = $records;
        $this->cacheRange = $range;
        return $records;
    }

    public function getVisits(string $range, int $page, int $perPage): array {
        $records = $this->readRecords($range);
        usort($records, fn($a, $b) => ($b['timestamp'] ?? 0) - ($a['timestamp'] ?? 0));
        $offset = ($page - 1) * $perPage;
        return array_slice($records, $offset, $perPage);
    }

    public function getVisitCount(string $range): int {
        return count($this->readRecords($range));
    }

    public function getStats(string $range): array {
        $records = $this->readRecords($range);
        $total = count($records);
        $durations = [];
        $interactions = [];
        $langCount = [];
        $osCount = [];
        $quality = ['super' => 0, 'okay' => 0, 'poor' => 0, 'bad' => 0];
        foreach ($records as $r) {
            if (!empty($r['duration'])) $durations[] = (float)$r['duration'];
            if (isset($r['interactions'])) $interactions[] = (int)$r['interactions'];
            if (!empty($r['lang'])) $langCount[$r['lang']] = ($langCount[$r['lang']] ?? 0) + 1;
            if (!empty($r['os'])) $osCount[$r['os']] = ($osCount[$r['os']] ?? 0) + 1;
            $d = (float)($r['duration'] ?? 0);
            $n = (int)($r['interactions'] ?? 0);
            $quality[$d <= 10 ? ($n > 0 ? 'okay' : 'bad') : ($n > 0 ? 'super' : 'poor')]++;
        }
        $trimmedMean = function(array $vals): float {
            $n = count($vals);
            if ($n === 0) return 0;
            sort($vals);
            $trim = max(1, (int)ceil($n * 0.05));
            $keep = array_slice($vals, $trim, $n - 2 * $trim);
            return empty($keep) ? (float)$vals[(int)floor($n / 2)] : array_sum($keep) / count($keep);
        };
        $topKey = function(array $counts): string {
            if (empty($counts)) return '';
            arsort($counts);
            return array_key_first($counts);
        };
        return [
            'total_visits' => $total,
            'typ_duration' => round($trimmedMean($durations), 1),
            'typ_interactions' => round($trimmedMean($interactions), 1),
            'top_language' => $topKey($langCount),
            'top_os' => $topKey($osCount),
            'quality' => $quality,
        ];
    }

    public function getAggregatedStats(string $range): array {
        $records = $this->readRecords($range);
        $buckets = [];

        if ($range === 'day') {
            $sums = array_fill(0, 24, 0.0);
            $buckets = array_fill(0, 24, ['count' => 0, 'avg' => 0]);
            foreach ($records as $r) {
                $h = (int)date('G', $r['timestamp']);
                $buckets[$h]['count']++;
                if (!empty($r['duration'])) $sums[$h] += (float)$r['duration'];
            }
            foreach ($buckets as $h => &$b) {
                if ($b['count'] > 0) $b['avg'] = round($sums[$h] / $b['count'], 1);
            }
        } else {
            $sums = [];
            foreach ($records as $r) {
                $d = date('Y-m-d', $r['timestamp']);
                if (!isset($buckets[$d])) $buckets[$d] = ['count' => 0, 'avg' => 0];
                $buckets[$d]['count']++;
                if (!empty($r['duration'])) {
                    if (!isset($sums[$d])) $sums[$d] = 0.0;
                    $sums[$d] += (float)$r['duration'];
                }
            }
            foreach ($buckets as $d => &$b) {
                if ($b['count'] > 0) $b['avg'] = round(($sums[$d] ?? 0) / $b['count'], 1);
            }
        }

        return self::fillChartGaps($buckets, $range);
    }

    public function cleanup(int $retentionDays): int {
        if ($retentionDays <= 0) return 0;
        $cutoff = time() - $retentionDays * 86400;
        $removed = 0;
        $files = glob($this->dir . '/*.txt');
        foreach ($files as $file) {
            $basename = basename($file, '.txt');
            $ts = strtotime($basename);
            if ($ts !== false && $ts < $cutoff) {
                unlink($file);
                $removed++;
            }
        }
        $this->recordsCache = null;
        return $removed;
    }
}

class SqliteStorage extends Storage {
    private \PDO $pdo;
    private string $dir;

    public function __construct(string $dir) {
        $this->dir = rtrim($dir, '/\\');
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
            os TEXT DEFAULT '',
            referrer TEXT DEFAULT '',
            page TEXT DEFAULT ''
        )");
        $this->migrateSchema();
    }

    private function migrateSchema(): void {
        try {
            $stmt = $this->pdo->query("SELECT geo FROM visits LIMIT 1");
        } catch (\PDOException $e) {
            $this->pdo->exec("ALTER TABLE visits ADD COLUMN geo TEXT DEFAULT ''");
        }
    }

    public function newVisit(array $data): string {
        $id = str_replace('.', '', microtime(true)) . '-' . bin2hex(random_bytes(4));
        $stmt = $this->pdo->prepare("INSERT INTO visits (id, timestamp, lang, ip, geo, os, referrer, page)
            VALUES (:id, :timestamp, :lang, :ip, :geo, :os, :referrer, :page)");
        $stmt->execute([
            ':id' => $id,
            ':timestamp' => time(),
            ':lang' => $data['lang'] ?? '',
            ':ip' => $data['ip'] ?? '',
            ':geo' => $data['geo'] ?? '',
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

    public function getVisits(string $range, int $page, int $perPage): array {
        $where = $this->rangeWhere($range);
        $offset = ($page - 1) * $perPage;
        $stmt = $this->pdo->prepare("SELECT * FROM visits $where ORDER BY timestamp DESC LIMIT :limit OFFSET :offset");
        $stmt->bindValue(':limit', $perPage, \PDO::PARAM_INT);
        $stmt->bindValue(':offset', $offset, \PDO::PARAM_INT);
        $stmt->execute();
        return $stmt->fetchAll(\PDO::FETCH_ASSOC);
    }

    public function getVisitCount(string $range): int {
        $where = $this->rangeWhere($range);
        $stmt = $this->pdo->query("SELECT COUNT(*) FROM visits $where");
        return (int)$stmt->fetchColumn();
    }

    public function getStats(string $range): array {
        $where = $this->rangeWhere($range);
        $stmt = $this->pdo->query("SELECT COUNT(*) as total FROM visits $where");
        $total = (int)$stmt->fetchColumn();

        $wDur = $where ? "$where AND duration > 0" : "WHERE duration > 0";
        $wAll = $where ? $where : '';
        $durations = $this->pdo->query("SELECT duration FROM visits $wDur ORDER BY duration")
            ->fetchAll(\PDO::FETCH_COLUMN);
        $interactions = $this->pdo->query("SELECT interactions FROM visits $wAll ORDER BY interactions")
            ->fetchAll(\PDO::FETCH_COLUMN);

        $trimmedMean = function(array $vals): float {
            $n = count($vals);
            if ($n === 0) return 0;
            $trim = max(1, (int)ceil($n * 0.05));
            $keep = array_slice($vals, $trim, $n - 2 * $trim);
            return empty($keep) ? (float)$vals[(int)floor($n / 2)] : array_sum($keep) / count($keep);
        };

        $wLang = $where ? "$where AND lang != ''" : "WHERE lang != ''";
        $wOs = $where ? "$where AND os != ''" : "WHERE os != ''";
        $topLang = $this->pdo->query("SELECT lang, COUNT(*) as c FROM visits $wLang GROUP BY lang ORDER BY c DESC LIMIT 1")->fetch(\PDO::FETCH_ASSOC);
        $topOs = $this->pdo->query("SELECT os, COUNT(*) as c FROM visits $wOs GROUP BY os ORDER BY c DESC LIMIT 1")->fetch(\PDO::FETCH_ASSOC);

        $wQ = $where ? $where : '';
        $quality = ['super' => 0, 'okay' => 0, 'poor' => 0, 'bad' => 0];
        $qStmt = $this->pdo->query(
            "SELECT CASE WHEN duration <= 10 AND interactions = 0 THEN 'bad'
                         WHEN duration <= 10 AND interactions > 0 THEN 'okay'
                         WHEN duration > 10 AND interactions = 0 THEN 'poor'
                         WHEN duration > 10 AND interactions > 0 THEN 'super'
                    END as q, COUNT(*) as c
             FROM visits $wQ GROUP BY q"
        );
        foreach ($qStmt->fetchAll(\PDO::FETCH_ASSOC) as $row) {
            $quality[$row['q']] = (int)$row['c'];
        }

        return [
            'total_visits' => $total,
            'typ_duration' => round($trimmedMean($durations), 1),
            'typ_interactions' => round($trimmedMean($interactions), 1),
            'top_language' => $topLang ? $topLang['lang'] : '',
            'top_os' => $topOs ? $topOs['os'] : '',
            'quality' => $quality,
        ];
    }

    public function getAggregatedStats(string $range): array {
        $where = $this->rangeWhere($range);
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
        return self::fillChartGaps($buckets, $range);
    }

    public function cleanup(int $retentionDays): int {
        if ($retentionDays <= 0) return 0;
        $cutoff = time() - $retentionDays * 86400;
        $stmt = $this->pdo->prepare("DELETE FROM visits WHERE timestamp < :cutoff");
        $stmt->execute([':cutoff' => $cutoff]);
        $this->pdo->exec("VACUUM");
        return $stmt->rowCount();
    }

    private function rangeWhere(string $range): string {
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

function createStorage(array $config): Storage {
    $dir = __DIR__ . '/../data';
    try {
        return $config['storage'] === 'sqlite'
            ? new SqliteStorage($dir)
            : new FileStorage($dir);
    } catch (\Exception $e) {
        return new FileStorage($dir);
    }
}