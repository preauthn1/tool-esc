<?php
// 事件定义文件
return [
    'bind' => [
    ],

    'listen' => [
        'RechargeSuccess'            => ['app\listener\RechargeSuccess'],
        'MatchFinanceProductSuccess' => ['app\listener\MatchFinanceProductSuccess'],
        'AppInit'                    => [
            'app\api\listener\CheckLang'
        ],
        'HttpRun'                    => [],
        'HttpEnd'                    => [],
        'LogLevel'                   => [],
        'LogWrite'                   => [],
    ],

    'subscribe' => [
    ],
];
