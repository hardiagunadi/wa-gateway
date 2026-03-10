<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Process;

class Pm2Service
{
    private string $outLogFile;
    private string $errorLogFile;

    public function __construct(
        private readonly string $appName,
        private readonly string $configFile,
        private readonly string $workingDir,
        private readonly string $pm2Binary = 'pm2',
        private readonly string $runAsUser = '',
    ) {
        $this->outLogFile   = $workingDir . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'pm2-out.log';
        $this->errorLogFile = $workingDir . DIRECTORY_SEPARATOR . 'logs' . DIRECTORY_SEPARATOR . 'pm2-error.log';
    }

    public function start(): void
    {
        $raw    = $this->getRawStatus();
        $status = $raw['pm2_env']['status'] ?? null;

        if ($status === 'online') {
            throw new RuntimeException("Server '{$this->appName}' sudah berjalan.");
        }

        if ($raw !== null) {
            // Registered but stopped/errored → restart by name
            $this->runPm2(['start', $this->appName]);
        } else {
            // Not registered yet → boot via ecosystem config
            $this->runPm2(['start', $this->configFile]);
        }
    }

    public function stop(): void
    {
        $raw = $this->getRawStatus();
        if ($raw === null) {
            throw new RuntimeException("Server '{$this->appName}' tidak ditemukan di PM2.");
        }
        $this->runPm2(['stop', $this->appName]);
    }

    public function restart(): void
    {
        $raw = $this->getRawStatus();
        if ($raw !== null) {
            $this->runPm2(['restart', $this->appName]);
        } else {
            // Not registered yet → start via ecosystem config
            $this->runPm2(['start', $this->configFile]);
        }
    }

    public function status(): array
    {
        $raw      = $this->getRawStatus();
        $pmEnv    = is_array($raw) ? ($raw['pm2_env'] ?? []) : [];
        $pm2State = $pmEnv['status'] ?? null;
        $running  = $pm2State === 'online';

        $uptimeStr = null;
        if ($running && isset($pmEnv['pm_uptime'])) {
            $sec       = (int) round((time() * 1000 - (int) $pmEnv['pm_uptime']) / 1000);
            $h         = intdiv($sec, 3600);
            $m         = intdiv($sec % 3600, 60);
            $s         = $sec % 60;
            $uptimeStr = $h > 0 ? "{$h}h {$m}m" : ($m > 0 ? "{$m}m {$s}s" : "{$s}s");
        }

        $memBytes = $raw['monit']['memory'] ?? null;
        $memStr   = $memBytes !== null ? round($memBytes / 1024 / 1024, 1) . ' MB' : null;

        return [
            'running'    => $running,
            'pm2Status'  => $pm2State ?? 'not registered',
            'pid'        => $raw['pid'] ?? null,
            'restarts'   => $pmEnv['restart_time'] ?? 0,
            'uptime'     => $uptimeStr,
            'memory'     => $memStr,
            'cpu'        => isset($raw['monit']['cpu']) ? $raw['monit']['cpu'] . '%' : null,
            'name'       => $this->appName,
            'workingDir' => $this->workingDir,
            'logFile'    => $this->outLogFile,
            'errorLog'   => $this->errorLogFile,
        ];
    }

    public function getLogFile(): string
    {
        return $this->outLogFile;
    }

    private function getRawStatus(): ?array
    {
        try {
            $process = Process::fromShellCommandline(
                $this->buildCommand(['jlist']) . ' 2>/dev/null',
                $this->workingDir,
            );
            $process->setTimeout(10);
            $process->run();

            $output = trim($process->getOutput());
            if ($output === '') {
                return null;
            }

            $list = json_decode($output, true);
            if (!is_array($list)) {
                return null;
            }

            foreach ($list as $app) {
                if (($app['name'] ?? '') === $this->appName) {
                    return $app;
                }
            }
        } catch (\Throwable) {
            // PM2 tidak terinstal atau error lain
        }

        return null;
    }

    private function runPm2(array $args): void
    {
        $cmd = $this->buildCommand($args);

        $process = Process::fromShellCommandline($cmd, $this->workingDir);
        $process->setTimeout(30);
        $process->run();

        if (!$process->isSuccessful()) {
            $err = trim($process->getErrorOutput() ?: $process->getOutput());
            throw new RuntimeException("PM2 command gagal: {$err}");
        }
    }

    private function buildCommand(array $args): string
    {
        $escapedArgs = array_map('escapeshellarg', $args);
        $pm2Cmd = escapeshellarg($this->pm2Binary) . ' ' . implode(' ', $escapedArgs);

        if ($this->runAsUser !== '' && $this->runAsUser !== posix_getpwuid(posix_geteuid())['name']) {
            return 'sudo -n -u ' . escapeshellarg($this->runAsUser) . ' ' . $pm2Cmd;
        }

        return $pm2Cmd;
    }
}
