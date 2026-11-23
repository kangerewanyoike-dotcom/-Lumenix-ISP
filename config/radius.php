<?php

return [
    'radclient_binary' => env('RADIUS_RADCLIENT_BINARY', 'radclient'),
    'disconnect_port' => (int) env('RADIUS_DISCONNECT_PORT', 1700),
];
