<?php

use Illuminate\Http\Request;

return [
    'proxies' => array_values(array_filter(array_map(
        'trim',
        explode(',', (string) env('TRUSTED_PROXIES', '127.0.0.1,::1')),
    ))),
    'headers' => Request::HEADER_X_FORWARDED_PROTO,
];
