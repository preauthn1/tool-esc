<?php

namespace app\api\listener;
use app\common\model\ConfigModel;
class RegionLimit {

    public function handle() {
        $model = new ConfigModel();
        $config = $model->getConfig();
        $ip = getip();
        if (isset($config['open_region_limit']) && $config['open_region_limit'] == 1) {
            $info = ip2location_arr($ip, [1, 2, 3, 4], 'en');
            $field = $config['region_limit_field'];
            $deny_region_limit_tips = !empty($config['deny_region_limit_tips']) ? $config['deny_region_limit_tips'] : 'access denied';
            if (isset($info[$field]) && !empty($info[$field]) && $info[$field] != '') {
                $region = $info[$field];
                $allow_region_limit = array_filter(array_unique(explode(',', trim($config['allow_region_limit']))));
                $deny_region_limit = array_filter(array_unique(explode(',', trim($config['deny_region_limit']))));
                if (isArrayAndNotEmpty($deny_region_limit) && in_array($region, $deny_region_limit)) {
                    die($deny_region_limit_tips);
                } elseif (isArrayAndNotEmpty($allow_region_limit) && !in_array($region, $allow_region_limit)) {
                    die($deny_region_limit_tips);
                }
            }
        }
    }

}
