<?php

namespace app\api\validate;

use app\common\model\UserModel;

class TransferMoneyValidate extends BaseValidate
{
    protected $rule = [
        'money'       => 'require|number|gt:0|currency',
        'password'    => 'require|checkPaypwd',
        'paypwd'      => 'require|checkTransferMoneyStatus',
        'invite_code' => 'require|checkUserByInviteCode'
//        'user_id' => 'require|checkUserByUserId'
    ];

    protected $message = [
        'money.require'                     => '请输入金额',
        'money.number'                      => '金额格式错误',
        'money.currency'                    => '金额格式错误',
        'money.gt'                          => '请输入正确的金额',
        'password.require'                  => '请输入安全密码',
        'password.checkPaypwd'              => '安全密码错误',
        'paypwd.require'                    => '未设置安全密码',
        'invite_code.require'               => '请输入邀请码',
        'invite_code.checkUserByInviteCode' => '邀请码错误',
//        'user_id.require'               => '请输入用户ID',
//        'user_id.checkUserByUserId' => '用户ID错误',
    ];


    protected $scene = [

    ];


    public function checkPaypwd($value, $rule, $data): bool
    {
        $password_hash = md5($value.config('app.salt'));   // 密码
        if ($data['paypwd'] != $password_hash) {
            return false;
        }
        return true;
    }

    public function checkUserByInviteCode($invite_code, $rule, $data)
    {
        $user_info = UserModel::getOneUser($invite_code, 'invite_code',
            'id,invite_code,invite_code_status,transfer_money_status');
        if ($user_info) {
            if (!$user_info['invite_code_status']) {
                return '邀请码已失效';
            }
            if ($user_info->id == $data['user_id']) {
                return '无法转账';
            }
            return true;
        }
        return false;
    }

    public function checkUserByUserId($user_id, $rule, $data)
    {
        $user_info = UserModel::getOneUser($user_id, 'id',
            'id,invite_code,invite_code_status,transfer_money_status');
        if ($user_info) {
            if (!$user_info['status']) {
                return '邀请码已失效';
            }
            if ($user_info->id == $data['user_id']) {
                return '无法转账';
            }
            return true;
        }
        return false;
    }

    public function checkTransferMoneyStatus($value, $rule, $data)
    {
        $user_info = UserModel::getOneUser($data['user_id'], 'id',
            'id,transfer_money_status');
        if ($user_info) {
            if (!$user_info['transfer_money_status']) {
                return '仅限官方合伙人有权限';
            }
            return true;
        }
        return false;
    }
}
