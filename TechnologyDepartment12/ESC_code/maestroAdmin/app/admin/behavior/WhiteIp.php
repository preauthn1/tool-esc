<?php

namespace app\admin\behavior;
use app\common\model\ConfigModel;
class WhiteIp {

    public function handle() {
        $model = new ConfigModel();
        $config = $model->getConfig(false);
        $ips = array_filter(array_map(function($ip){return trim($ip);}, explode("\n", $config['admin_white_ip'])));
        $ip = getip();
        if (isArrayAndNotEmpty($ips) && !in_array($ip, $ips)) {
            echo "ip:{$ip},Not whitelisted!";die;
        }
    }

}
