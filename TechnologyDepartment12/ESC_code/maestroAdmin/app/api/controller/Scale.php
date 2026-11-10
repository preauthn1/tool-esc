<?php

namespace app\api\controller;

use app\common\model\ConfigModel;
use app\BaseController;
use think\response\Json;

/**
 * 平台规模配置接口
 */
class Scale extends BaseController
{
  /**
   * 获取平台规模配置
   * @return Json
   */
  public function index()
  {
    // 获取配置数据
    $config_model = new ConfigModel();
    $config = $config_model->getConfig();

    // 获取平台规模数据
    $platform_scale_data = [];
    if (isset($config['platform_scale_data']) && !empty($config['platform_scale_data'])) {
      $platform_scale_data = json_decode($config['platform_scale_data'], true);
    } else {
      // 如果没有配置数据，返回默认空结构
      $platform_scale_data = [
        'daily_impressions' =>0,
        'daily_data_volume' => 0,
        'office_locations' => 0,
        'advertiser_bid_amount' => 0,
        'direct_publishers' => 0,
        'data_centers' => 0
      ];
    }

      return json(msg(0, $platform_scale_data, ''));
  }
}
