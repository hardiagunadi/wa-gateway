<?php

namespace App\Http\Controllers;

use App\Services\GatewayService;
use App\Services\NodeEnvManager;
use App\Services\NpmService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class GatewayController extends Controller
{
    public function index(Request $request): View
    {
        $gateway = $this->gateway();
        $sessions = [];
        $health = null;
        $apiError = null;

        try {
            $sessions = $gateway->listSessions();
            $health = $gateway->health();
        } catch (Throwable $e) {
            $apiError = $e->getMessage();
        }

        $envManager = new NodeEnvManager(config('gateway.node_env_path'));
        $nodeEnv = $envManager->read();

        $npm = $this->npm();

        return view('dashboard', [
            'sessions' => $sessions,
            'health' => $health,
            'apiError' => $apiError,
            'qrData' => session('qr'),
            'qrSession' => session('qrSession'),
            'statusMessage' => session('status'),
            'errorsBag' => $request->session()->get('errors'),
            'nodeEnv' => $nodeEnv,
            'npmStatus' => $npm->status(),
            'gatewayConfig' => [
                'base' => config('gateway.base_url'),
                'key' => config('gateway.api_key'),
            ],
        ]);
    }

    public function startSession(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'session' => ['required', 'string'],
        ]);

        try {
            $response = $this->gateway()->startSession($data['session']);
            $qr = $response['qr'] ?? null;

            return redirect()
                ->route('dashboard')
                ->with([
                    'status' => $qr ? 'Session dibuat, scan QR di bawah untuk menghubungkan WhatsApp.' : 'Session berhasil tersambung.',
                    'qr' => $qr,
                    'qrSession' => $data['session'],
                ]);
        } catch (Throwable $e) {
            return redirect()
                ->route('dashboard')
                ->withErrors(['session' => $e->getMessage()]);
        }
    }

    public function closeSession(string $session): RedirectResponse
    {
        try {
            $this->gateway()->logoutSession($session);

            return redirect()
                ->route('dashboard')
                ->with('status', "Session {$session} ditutup.");
        } catch (Throwable $e) {
            return redirect()
                ->route('dashboard')
                ->withErrors(['session' => $e->getMessage()]);
        }
    }

    public function updateWebhook(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'webhook_base_url' => ['nullable', 'string'],
            'gateway_key' => ['nullable', 'string'],
        ]);

        $envManager = new NodeEnvManager(config('gateway.node_env_path'));
        $updated = $envManager->update([
            'WEBHOOK_BASE_URL' => $data['webhook_base_url'] ?? '',
            'KEY' => $data['gateway_key'] ?? '',
        ]);

        return redirect()
            ->route('dashboard')
            ->with('status', 'Webhook dan API key disimpan pada .env server Node. Restart server agar perubahan aktif.')
            ->with('qr', session('qr'))
            ->with('qrSession', session('qrSession'))
            ->with('envSnapshot', $updated);
    }

    public function startServer(): RedirectResponse
    {
        try {
            $pid = $this->npm()->start();

            return redirect()
                ->route('dashboard')
                ->with('status', "NPM server dijalankan (PID {$pid}).");
        } catch (Throwable $e) {
            return redirect()
                ->route('dashboard')
                ->withErrors(['server' => $e->getMessage()]);
        }
    }

    public function stopServer(): RedirectResponse
    {
        try {
            $this->npm()->stop();

            return redirect()
                ->route('dashboard')
                ->with('status', 'NPM server dihentikan.');
        } catch (Throwable $e) {
            return redirect()
                ->route('dashboard')
                ->withErrors(['server' => $e->getMessage()]);
        }
    }

    private function gateway(): GatewayService
    {
        return new GatewayService(
            config('gateway.base_url'),
            config('gateway.api_key')
        );
    }

    private function npm(): NpmService
    {
        return new NpmService(
            config('gateway.npm.command'),
            config('gateway.npm.workdir')
        );
    }
}
