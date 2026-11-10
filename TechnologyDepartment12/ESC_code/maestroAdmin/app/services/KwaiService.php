<?php

namespace app\services;

use app\common\model\PromotionModel;
use think\facade\Log;

class KwaiService
{
    public static function event($user, $event_name, $event_data = [])
    {
        try {
            $pixel_id = $user['pix'] ? $user['pix'] : PromotionModel::getInfoDefaultPix('kwai');
            $access_token = PromotionModel::getAccessToken($pixel_id, 'kwai');
            self::send($user['click_id'], $pixel_id, $access_token, $event_name, $event_data);
        } catch (\Exception $e) {
            Log::write($e->getMessage(), "kwai");
            Log::write(lang('获取像素配置失败'), "kwai");
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
    public static function send($clickid, $pixel_id, $access_token, $event_name, $event_data = [])
    {
        $url = "http://www.adsnebula.com/log/common/api";
        //accept: application/json;charset=utf-8
        $headers = [
            'Accept: application/json;charset=utf-8',
            'Content-Type: application/json',
        ];
        //"access_token": "YUr9HVpJhC2iDtwF/Pws2A==",
        //    "clickid": "r70jeGg9vCtw3zMcjsKEPQ==",
        //    "event_name": "EVENT_INITIATED_CHECKOUT",
        //    "is_attributed": 1,
        //    "mmpcode": "PL",
        //    "pixelId": "Zp6x5j5sUuaSdnCgsJl7hA",
        //    "pixelSdkVersion": "9.9.9",
        //    "properties": "{\"content_id\":\"18711551\",\"content_type\":\"product\",\"content_name\":\"Pacote de Presente de Eletrodomésticos\"}",
        //    "testFlag": false,
        //    "third_party": "shopline",
        //    "trackFlag": true
        $data = [
            'access_token' => $access_token,
            'clickid' => $clickid,
            'event_name' => $event_name,
            'is_attributed' => 1,
            'mmpcode' => 'PL',
            'pixelId' => $pixel_id,
            'pixelSdkVersion' => '9.9.9',
            'properties' => json_encode($event_data),
            'testFlag' => false,
            'third_party' => 'shopline',
            'trackFlag' => true
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
//        print_r($temp);
        if ($temp['result'] == 1) {
            return true;
        } else {
            return false;
        }
    }
}
