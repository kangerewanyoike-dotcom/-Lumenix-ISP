<?php

namespace App\Observers;

use App\Models\Router;
use Illuminate\Support\Facades\DB;

final class RouterObserver
{
    public function saved(Router $router): void
    {
        DB::table('nas')->where('description', 'lumenix-router-'.$router->id)->delete();
        if ($router->connection_mode === 'vpn' && $router->status === 'active' && $router->endpoint && $router->radius_secret) {
            DB::table('nas')->insert([
                'nasname' => $router->endpoint, 'shortname' => $router->name, 'type' => 'mikrotik',
                'secret' => $router->radius_secret, 'description' => 'lumenix-router-'.$router->id,
            ]);
        }
    }

    public function deleted(Router $router): void
    {
        DB::table('nas')->where('description', 'lumenix-router-'.$router->id)->delete();
    }
}
