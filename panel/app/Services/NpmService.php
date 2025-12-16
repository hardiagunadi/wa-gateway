<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

class NpmService
{
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
        if ($this->isRunning()) {
            throw new RuntimeException('Server already running.');
        }

        if (!is_dir($this->workingDir)) {
            throw new RuntimeException('Working directory not found: ' . $this->workingDir);
        }

        $process = Process::fromShellCommandline($this->command, $this->workingDir);
        $process->setTimeout(null);

        // Capture output to a log file for debugging.
        $process->start(function ($type, $buffer) {
            file_put_contents($this->logFile, $buffer, FILE_APPEND);
        });

        // Give the process a moment to boot and get a PID.
        usleep(400000);

        $pid = $process->getPid();
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

        if (file_exists($this->pidFile)) {
            unlink($this->pidFile);
        }
    }

    public function status(): array
    {
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

        return trim($process->getOutput()) !== '';
    }

    private function isWindows(): bool
    {
        return str_starts_with(strtolower(PHP_OS_FAMILY), 'windows');
    }
}
