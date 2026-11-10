<?php

namespace app\api\controller;

use app\api\transform\BankTransform;
use app\common\model\BankModel;
use think\facade\Lang;
use think\response\Json;

class Bank extends Base
{
    public function index(BankTransform $transform): Json
    {
        $banks = BankModel::where('status', 1)->select();
        $data = $transform->transformCollection($banks->toArray());
        return json(msg(1, $data, lang('success')));
    }

}
