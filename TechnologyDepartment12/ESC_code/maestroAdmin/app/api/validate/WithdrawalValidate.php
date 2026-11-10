<?php

namespace app\api\validate;

use app\admin\model\ConfigModel;

class WithdrawalValidate extends BaseValidate
{
    protected $rule = [
        'money' => 'require|number|gt:0|currency|checkAmount'
    ];

    protected $message = [
        'money.require'  => '请输入金额',
        'money.currency' => '金额格式错误',
        'money.gt'       => '请输入正确的金额',
    ];


    protected $scene = [

    ];

    public function checkAmount($value, $rule, $data = []): bool|string
    {
        $withdrawal_100_multiple = ConfigModel::getConfigValue('withdrawal_100_multiple');
        if ($withdrawal_100_multiple) {
            // 检查金额是否是100的倍数
            $value = round($value);
            if ($value % 100 != 0) {
                return '出金金额必须为100的整数倍，如 ¥600、¥700、¥800 等。';
            }
        }
        return true;
    }
}
