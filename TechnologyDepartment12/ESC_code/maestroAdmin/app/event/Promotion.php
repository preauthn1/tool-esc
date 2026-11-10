<?php

namespace app\event;

use app\services\FacebookService;
use app\services\KwaiService;
use app\services\TikTokService;

class Promotion
{
    public static array $event_name = [
        'kwai'     => [
            'register'      => 'EVENT_COMPLETE_REGISTRATION', // 注册事件
            'login'         => '', // 登录事件
            'content_view'  => 'EVENT_CONTENT_VIEW', // 内容浏览事件
            'first_deposit' => 'EVENT_FIRST_DEPOSIT', // 首充事件
        ],
        'tiktok'   => [
            'register'      => 'CompleteRegistration', // 注册事件
            'login'         => '', // 登录事件
            'content_view'  => 'ViewContent', // 内容浏览事件
            'first_deposit' => 'CompletePayment', // 首充事件
        ],
        'facebook' => [
            'register'      => 'CompleteRegistration', // 注册事件
            'login'         => '', // 登录事件
            'content_view'  => 'PageView', // 内容浏览事件
            'first_deposit' => 'Purchase', // 首充事件
        ],
    ];

    public static function handle($user, $event_name, $event_data = [], $test_event_code = '')
    {
        switch ($user['platform']) {
            case 'kwai':
                KwaiService::event($user, $test_event_code ?: self::$event_name[$user['platform']][$event_name],
                    $event_data);
                break;
            case 'tiktok':
                TikTokService::event($user, $test_event_code ?: self::$event_name[$user['platform']][$event_name],
                    $event_data);
                break;
            case 'facebook':
                FacebookService::event($user, self::$event_name[$user['platform']][$event_name], $event_data, $test_event_code);
                break;
        }
    }
}
