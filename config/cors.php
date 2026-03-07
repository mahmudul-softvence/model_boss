<?php

return [

    'paths' => ['api/*', 'public/storage/*'],

    'allowed_methods' => ['*'],

    'allowed_origins' => ['http://localhost:3000', 'https://your-react-app.com'],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    'max_age' => 0,

    'supports_credentials' => false,

];
