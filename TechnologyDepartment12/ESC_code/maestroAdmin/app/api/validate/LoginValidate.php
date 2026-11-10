<?php

namespace app\api\validate;

use app\common\model\ConfigModel;
use think\Validate;

class LoginValidate extends Validate
{
    protected $rule = [
        'email'      => 'email',
        'mobile'     => 'require|number|min:8|max:20',
        'phone_code' => 'require|checkPhoneCode',
        'login_type' => 'require',
        'password'   => 'require'
    ];

    protected $message = [
        'mobile.min' => '请输入正确的手机号',
        'mobile.max' => '手机号过长，请输入正确的手机号'
    ];


    protected $scene = [
        'email' => ['email', 'password'],
        'mobile' => ['mobile','phone_code', 'password'],
    ];

    public function checkPhoneCode($value, $rule, $data)
    {
        $phone_codes = ConfigModel::$phone_codes;
        if (!in_array($value, $phone_codes)) {
            return lang('请选择正确的区号');
        }
        return true;
    }
}
