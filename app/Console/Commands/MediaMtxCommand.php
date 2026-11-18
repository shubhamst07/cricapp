<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Symfony\Component\Process\Process;

class MediaMtxCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'mediamtx:run 
                            {--daemon : Run MediaMTX as a background daemon}
                            {--stop : Stop the running MediaMTX process}
                            {--status : Check whether MediaMTX is running}
                            {--install : Auto-download the MediaMTX binary for this OS}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Manage and run the MediaMTX WebRTC (WHIP/WHEP) & HLS live streaming engine';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $binDir = base_path('bin');
        $binPath = base_path('bin/mediamtx');
        $configPath = base_path('mediamtx.yml');
        $pidFile = storage_path('logs/mediamtx.pid');
        $logFile = storage_path('logs/mediamtx.log');

        if ($this->option('status')) {
            return $this->checkStatus($pidFile);
        }

        if ($this->option('stop')) {
            return $this->stopProcess($pidFile);
        }

        if ($this->option('install') || !file_exists($binPath)) {
            $this->info('Downloading MediaMTX binary...');
            if (!$this->installBinary($binDir, $binPath)) {
                $this->error('Failed to install MediaMTX. Please check network connectivity.');
                return 1;
            }
        }

        if ($this->option('daemon')) {
            return $this->startDaemon($binPath, $configPath, $pidFile, $logFile);
        }

        $this->info("Starting MediaMTX WebRTC & HLS Server in foreground...");
        $this->info("Config: {$configPath}");
        $this->info("WebRTC WHIP/WHEP: http://0.0.0.0:" . config('services.mediamtx.webrtc_port', 8889));
        $this->info("HLS: http://0.0.0.0:" . config('services.mediamtx.hls_port', 8888));
        $this->info("RTMP: rtmp://0.0.0.0:" . config('services.mediamtx.rtmp_port', 1935));

        $proc = new Process([$binPath, $configPath]);
        $proc->setTimeout(null);
        $proc->run(function ($type, $buffer) {
            $this->output->write($buffer);
        });

        return $proc->getExitCode() ?? 0;
    }

    protected function checkStatus(string $pidFile): int
    {
        if (file_exists($pidFile)) {
            $pid = (int) trim(file_get_contents($pidFile));
            if ($pid > 0 && posix_kill($pid, 0)) {
                $this->info("MediaMTX is running (PID: {$pid})");
                return 0;
            }
            @unlink($pidFile);
        }

        // Fallback check by process name
        exec('pgrep -x mediamtx', $pids);
        if (!empty($pids)) {
            $this->info("MediaMTX is running (PID: " . implode(', ', $pids) . ")");
            return 0;
        }

        $this->warn('MediaMTX is not running.');
        return 1;
    }

    protected function stopProcess(string $pidFile): int
    {
        $killed = false;
        if (file_exists($pidFile)) {
            $pid = (int) trim(file_get_contents($pidFile));
            if ($pid > 0) {
                posix_kill($pid, SIGTERM);
                $this->info("Sent SIGTERM to MediaMTX (PID: {$pid})");
                $killed = true;
            }
            @unlink($pidFile);
        }

        exec('pkill -f bin/mediamtx 2>/dev/null', $out, $code);
        if ($code === 0 || $killed) {
            $this->info('MediaMTX stopped.');
            return 0;
        }

        $this->info('No running MediaMTX process found.');
        return 0;
    }

    protected function startDaemon(string $binPath, string $configPath, string $pidFile, string $logFile): int
    {
        if ($this->checkStatus($pidFile) === 0) {
            $this->warn('MediaMTX is already running.');
            return 0;
        }

        $cmd = sprintf(
            'nohup %s %s > %s 2>&1 & echo $! > %s',
            escapeshellarg($binPath),
            escapeshellarg($configPath),
            escapeshellarg($logFile),
            escapeshellarg($pidFile)
        );

        exec($cmd);
        sleep(1);

        if ($this->checkStatus($pidFile) === 0) {
            $this->info("MediaMTX started in background. Logs: {$logFile}");
            return 0;
        }

        $this->error('Failed to start MediaMTX background process. Check ' . $logFile);
        return 1;
    }

    protected function installBinary(string $binDir, string $binPath): bool
    {
        if (!is_dir($binDir)) {
            mkdir($binDir, 0755, true);
        }

        $arch = php_uname('m');
        $archMap = [
            'x86_64' => 'linux_amd64',
            'amd64' => 'linux_amd64',
            'aarch64' => 'linux_arm64v8',
            'arm64' => 'linux_arm64v8',
        ];

        $target = $archMap[$arch] ?? 'linux_amd64';
        $version = 'v1.21.4';
        $url = "https://github.com/bluenviron/mediamtx/releases/download/{$version}/mediamtx_{$version}_{$target}.tar.gz";

        $tempTar = sys_get_temp_dir() . '/mediamtx_' . time() . '.tar.gz';
        
        // Try curl first
        exec("curl -s -L -A 'Mozilla/5.0' " . escapeshellarg($url) . " -o " . escapeshellarg($tempTar), $out, $code);
        if ($code !== 0 || !file_exists($tempTar) || filesize($tempTar) < 10000) {
            $opts = [
                'http' => [
                    'method' => 'GET',
                    'header' => "User-Agent: Mozilla/5.0 (LiveCricket MediaMTX Installer)\r\n"
                ]
            ];
            $context = stream_context_create($opts);
            $content = @file_get_contents($url, false, $context);
            if ($content) {
                file_put_contents($tempTar, $content);
            }
        }

        if (!file_exists($tempTar) || filesize($tempTar) < 10000) {
            return false;
        }

        // Extract using tar
        exec("tar -xzf " . escapeshellarg($tempTar) . " -C " . escapeshellarg($binDir) . " mediamtx", $out, $code);
        @unlink($tempTar);

        if (file_exists($binPath)) {
            chmod($binPath, 0755);
            $this->info("MediaMTX {$version} installed successfully at {$binPath}");
            return true;
        }

        return false;
    }
}
