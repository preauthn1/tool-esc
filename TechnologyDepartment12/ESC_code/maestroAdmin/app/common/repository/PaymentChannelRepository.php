<?php

namespace app\common\repository;

use app\common\model\PaymentPlatformModel;


class PaymentChannelRepository extends Repository
{
    protected $model;

    public function __construct()
    {
        
        //绑定模型
        $this->model = new PaymentPlatformModel();
    }

}
