<?php

namespace app\api\controller;

use think\response\Json;

class App extends Base
{
    public function download($type = 'android'): Json
    {
        switch ($type) {
            case 'ios':
                $url = toMedia($this->configInfo['ios_url']);
                break;
            default:
                $url = toMedia($this->configInfo['android_url']);
        }
        return json(msg(0, ['url' => $url], ''));
    }


}
