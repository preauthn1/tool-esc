<?php
return [
    'listen' => [
        'RouteLoaded' => [
            '\app\api\listener\RegionLimit',
            '\app\api\listener\CheckDomain',
            '\app\api\listener\CORS',
        ],
        // 更多事件监听
    ],
];
