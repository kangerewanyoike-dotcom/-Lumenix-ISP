<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Router;
use Symfony\Component\Process\Process;

final class RadclientRouterConnection implements RouterConnection
{
    public function __construct(private readonly RadiusDisconnectRequest $request) {}

    public function disconnect(Router $router, Customer $customer): bool
    {
        if (blank($router->radius_secret)) {
            throw new \RuntimeException('The router RADIUS shared secret is not configured.');
        }
        $secretFile = tempnam(sys_get_temp_dir(), 'lumenix-radius-');
        if ($secretFile === false) {
            throw new \RuntimeException('Could not create a temporary RADIUS secret file.');
        }

        try {
            if (file_put_contents($secretFile, $router->radius_secret) === false) {
                throw new \RuntimeException('Could not write the temporary RADIUS secret file.');
            }
            @chmod($secretFile, 0600);
            $process = new Process([
                config('radius.radclient_binary', 'radclient'), '-S', $secretFile, '-r', '3', '-t', '2',
                $router->endpoint.':'.config('radius.disconnect_port', 1700), 'disconnect',
            ]);
            $process->setInput($this->request->payload($router, $customer));
            $process->setTimeout(10);
            $process->run();

            return $process->isSuccessful();
        } finally {
            @unlink($secretFile);
        }
    }
}
