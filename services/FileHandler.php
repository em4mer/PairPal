<?php
// services/FileHandler.php

abstract class FileHandler {
    protected string $filePath;
    protected int    $lockTimeout = 5;

    public function __construct(string $filePath) {
        $this->filePath = $filePath;
        $this->ensureFileExists();
    }

    private function ensureFileExists(): void {
        if (!file_exists($this->filePath)) {
            $dir = dirname($this->filePath);
            if (!is_dir($dir)) {
                mkdir($dir, 0775, true);
            }
            file_put_contents($this->filePath, '[]');
            chmod($this->filePath, 0664);
        }
    }

    protected function readAll(): array {
        if (!is_readable($this->filePath)) {
            error_log("FileHandler: cannot read {$this->filePath}");
            return [];
        }

        $fp = fopen($this->filePath, 'r');
        if (!$fp) return [];

        // Use blocking lock with timeout via retries
        $attempts = 0;
        $maxAttempts = $this->lockTimeout * 10; // 100ms intervals
        while (!flock($fp, LOCK_SH | LOCK_NB)) {
            if (++$attempts >= $maxAttempts) {
                fclose($fp);
                error_log("FileHandler: read lock timeout on {$this->filePath}");
                return [];
            }
            usleep(100000); // 100ms
        }

        $contents = stream_get_contents($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        if ($contents === false || trim($contents) === '') {
            return [];
        }

        $data = json_decode($contents, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log("FileHandler: JSON parse error in {$this->filePath}: " . json_last_error_msg());
            return [];
        }

        return is_array($data) ? $data : [];
    }

    protected function writeAll(array $data): bool {
        if (!is_writable($this->filePath) && !is_writable(dirname($this->filePath))) {
            error_log("FileHandler: cannot write to {$this->filePath}");
            return false;
        }

        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            error_log("FileHandler: JSON encode error for {$this->filePath}: " . json_last_error_msg());
            return false;
        }

        $fp = fopen($this->filePath, 'c+');
        if (!$fp) return false;

        $attempts    = 0;
        $maxAttempts = $this->lockTimeout * 10;
        while (!flock($fp, LOCK_EX | LOCK_NB)) {
            if (++$attempts >= $maxAttempts) {
                fclose($fp);
                error_log("FileHandler: write lock timeout on {$this->filePath}");
                return false;
            }
            usleep(100000);
        }

        ftruncate($fp, 0);
        rewind($fp);
        $written = fwrite($fp, $json);
        fflush($fp);
        flock($fp, LOCK_UN);
        fclose($fp);

        return $written !== false;
    }

    abstract public function findById(string $id): ?array;
    abstract public function save(array $record): bool;
    abstract public function delete(string $id): bool;
    abstract public function getAll(): array;
}
