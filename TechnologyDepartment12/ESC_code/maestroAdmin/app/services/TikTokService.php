<?php

namespace app\services;

use app\common\model\PromotionModel;
use think\facade\Log;

class TikTokService
{
    public static function event($user, $event_name, $event_data = [])
    {
        try {
            $pixel_id = $user['pix'] ? $user['pix'] : PromotionModel::getInfoDefaultPix('tiktok');
            $access_token = PromotionModel::getAccessToken($pixel_id, 'tiktok');
            self::send($pixel_id, $access_token, $event_name, $event_data);
        } catch (\Exception $e) {
            Log::write($e->getMessage(), "tiktok");
            Log::write(lang('获取像素配置失败'), "tiktok");
            return;
        }
    }

    /**
     * 发送事件
     * @param $clickid
     * @param $pixel_id
     * @param $access_token
     * @param $event_name
     * @param array $event_data
     * @return bool
     */
    public static function send($pixel_id, $access_token, $event_name, $event_data = [])
    {
        //curl --location --request POST 'https://business-api.tiktok.com/open_api/v1.3/event/track/'     --header 'Access-Token: <<ACCESS TOKEN>>'     --header 'Content-Type: application/json'     --data-raw '{
        //    "event_source": "web",
        //    "event_source_id": "CR6PHPBC77UFSKJEKQS0",
        //    "data": [
        //        {
        //            "event": "CompletePayment",
        //            "event_time": 1724756811,
        //            "user": {},
        //            "properties": {
        //                "value": 100
        //            },
        //            "page": {}
        //        }
        //    ]
        //}'
        $url = "https://business-api.tiktok.com/open_api/v1.3/event/track/";
        $headers = [
            "Access-Token: $access_token",
            'Content-Type: application/json',
        ];
        $data = [
            'event_source' => 'web',
            'event_source_id' => $pixel_id,
            'data' => [
                [
                    'event' => $event_name,
                    'event_time' => time(),
                    'properties' => $event_data,
                ]
            ]
        ];
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers); //设置header
        $content = curl_exec($ch);
        curl_close($ch);
        $temp = json_decode($content, true);
        if ($temp['code'] == 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * post 请求 file_get_contents 模式
     * @param $url
     * @param $data
     * @param int $timeout
     * @param array $header
     * @return false|string
     */
    public static function postRequestJson($url, $data, $timeout = 60, $header = [])
    {
        if (empty($header)) {
            $header[] = 'Content-type:application/json';
        }
        $query = json_encode($data, JSON_UNESCAPED_UNICODE);
//        $query = http_build_query($data);
//        print_r($query);
        $options['http'] = array(
            'timeout' => $timeout,
            'method' => "POST",
            'header' => $header,
            'content' => $query
        );
        $context = stream_context_create($options);
        print_r($context);
        return file_get_contents($url, false, $context);
    }
}
