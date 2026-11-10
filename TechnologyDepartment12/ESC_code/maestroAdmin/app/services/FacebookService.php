<?php

namespace app\services;

use app\common\model\PromotionModel;
use FacebookAds\Api;
use FacebookAds\Object\ServerSide\CustomData;
use FacebookAds\Object\ServerSide\Event;
use FacebookAds\Object\ServerSide\EventRequest;
use FacebookAds\Object\ServerSide\UserData;
use think\facade\Log;

class FacebookService
{
    public static function event($user, $event_name, $event_data = [], $test_event_code= '')
    {
        try {
            $pixel_id = $user['pix'] ? $user['pix'] : PromotionModel::getInfoDefaultPix();
            $access_token = PromotionModel::getAccessToken($pixel_id,'facebook');
        } catch (\Exception $e) {
            Log::write($e->getMessage(), "facebook");
            Log::write(lang('获取像素配置失败'), "facebook");
            return;
        }

        try {
            Api::init(null, null, $access_token);
            $user_data = (new UserData());
            $result = filter_var($user->user_name, FILTER_SANITIZE_EMAIL);
            if (filter_var($result, FILTER_VALIDATE_EMAIL)) {
                $user_data->setEmail($user->user_name);
            } else {
                $user_data->setPhone($user->mobile);
            }
            $custom_data = new CustomData();
            if ($event_name == 'first_deposit') {
                $custom_data = $custom_data->setValue($event_data['value']);
            }
            $event = (new Event())
                ->setEventName($event_name)
                ->setEventTime(time())
                ->setUserData($user_data)
                ->setCustomData($custom_data);
            $request = (new EventRequest($pixel_id))->setEvents(array($event));
            if ($test_event_code) {
                $request->setTestEventCode($test_event_code);
            }
            $response = $request->execute();
        } catch (\Exception $e) {
            Log::write(lang('发送事件失败'), "facebook");
            try {
                Log::write($e, "facebook");
            } catch (\Exception $e) {
                Log::write([
                    'msg' => $e->getMessage(),
                    'line' => $e->getLine(),
                    'file' => $e->getFile(),
                ], "facebook");
            }
            return;
        }
        Log::write(json_encode($response->getMessages()).'--'.$response->getFbTraceId(), "facebook");
    }
}
