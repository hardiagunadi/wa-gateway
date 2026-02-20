<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

class NpmService
{
    private const LOG_ROTATE_BYTES = 5 * 1024 * 1024;

    private string $pidFile;
    private string $logFile;

    public function __construct(
        private readonly string $command,
        private readonly string $workingDir
    ) {
        $this->pidFile = storage_path('app/npm-server.pid');
        $this->logFile = storage_path('logs/npm-server.log');
    }

    public function start(): int
    {
        $this->cleanupStalePid();

        if ($this->isRunning()) {
            throw new RuntimeException('Server already running.');
        }

        if (!is_dir($this->workingDir)) {
            throw new RuntimeException('Working directory not found: ' . $this->workingDir);
        }

        if (!is_dir(dirname($this->logFile))) {
            mkdir(dirname($this->logFile), 0755, true);
        }

        $this->rotateLogIfNeeded();

        $command = $this->buildCommand();

        // Run detached so the PHP request lifecycle does not kill the process.
        $cmd = 'cd ' . escapeshellarg($this->workingDir) . ' && nohup ' . $command . ' >> ' . escapeshellarg($this->logFile) . ' 2>&1 & echo $!';

        $process = Process::fromShellCommandline($cmd);
        $process->setTimeout(10);
        $process->run();

        $pid = (int) trim($process->getOutput());
        if (!$pid || !$this->isPidRunning($pid)) {
            throw new RuntimeException('Failed to start npm server. Check npm configuration or logs.');
        }

        if (!is_dir(dirname($this->pidFile))) {
            mkdir(dirname($this->pidFile), 0755, true);
        }

        file_put_contents($this->pidFile, (string) $pid);

        return $pid;
    }

    public function stop(): void
    {
        $pid = $this->getPid();
        if (!$pid) {
            $this->cleanupStalePid();
            return;
        }

        if ($this->isWindows()) {
            $process = Process::fromShellCommandline(
                'powershell -NoProfile -Command "Stop-Process -Id ' . $pid . ' -Force"',
                $this->workingDir
            );
        } else {
            $process = Process::fromShellCommandline('kill ' . $pid, $this->workingDir);
        }

        $process->run();

        $this->cleanupStalePid();
    }

    public function status(): array
    {
        $this->cleanupStalePid();

        $pid = $this->getPid();
        $running = $pid ? $this->isPidRunning($pid) : false;

        return [
            'running' => $running,
            'pid' => $pid,
            'command' => $this->command,
            'workingDir' => $this->workingDir,
            'logFile' => $this->logFile,
        ];
    }

    public function getLogFile(): string
    {
        return $this->logFile;
    }

    private function isRunning(): bool
    {
        $pid = $this->getPid();
        return $pid ? $this->isPidRunning($pid) : false;
    }

    private function getPid(): ?int
    {
        if (!file_exists($this->pidFile)) {
            return null;
        }

        $content = trim((string) file_get_contents($this->pidFile));

        return $content !== '' ? (int) $content : null;
    }

    private function isPidRunning(int $pid): bool
    {
        if ($this->isWindows()) {
            $process = Process::fromShellCommandline(
                'powershell -NoProfile -Command "Get-Process -Id ' . $pid . ' -ErrorAction SilentlyContinue | Select-Object -ExpandProperty Id"',
                $this->workingDir
            );
        } else {
            $process = Process::fromShellCommandline('ps -p ' . $pid . ' -o pid=', $this->workingDir);
        }

        $process->run();

        $output = $process->getOutput();

        return (bool) preg_match('/^\\s*\\d+\\s*$/m', $output);
    }

    private function isWindows(): bool
    {
        return str_starts_with(strtolower(PHP_OS_FAMILY), 'windows');
    }

    private function buildCommand(): string
    {
        return ltrim($this->command);
    }

    private function rotateLogIfNeeded(): void
    {
        if (!is_file($this->logFile)) {
            return;
        }

        $size = filesize($this->logFile);
        if ($size === false || $size <= self::LOG_ROTATE_BYTES) {
            return;
        }

        $dir = dirname($this->logFile);
        $timestamp = date('Ymd-His');
        $rotated = $dir . DIRECTORY_SEPARATOR . 'npm-server-' . $timestamp . '.log';
        $suffix = 1;
        while (file_exists($rotated)) {
            $rotated = $dir . DIRECTORY_SEPARATOR . 'npm-server-' . $timestamp . '-' . $suffix . '.log';
            $suffix += 1;
        }

        if (!rename($this->logFile, $rotated)) {
            throw new RuntimeException('Failed to rotate npm server log.');
        }
    }

    /**
     * Remove stale PID files so UI reflects actual state.
     */
    private function cleanupStalePid(): void
    {
        $pid = $this->getPid();
        if ($pid && !$this->isPidRunning($pid) && file_exists($this->pidFile)) {
            unlink($this->pidFile);
        }
    }
}
