<?php

namespace app\common\model;

use app\admin\model\MemberModel;
use think\Model;
use think\model\relation\HasMany;
use think\model\relation\HasOne;
use think\facade\Request;

class MemberLogModel extends Model
{

    protected $autoWriteTimestamp = true;

    // 确定链接表名
    protected $name = 'home_user_log';

    const LOGIN = 0; // 登录
    const REGISTER = 1; // 注册
    const RECHARGE = 2; // 充值
    const WITHDRAW = 3; // 提现
    const BIND_CARD = 4; // 绑定钱包
    const CHANGE_PASSWORD = 5; // 修改密码
    const CHANGE_PAY_PASSWORD = 6; // 修改支付密码
    const SET_PAY_PASSWORD = 7; // 设置安全密码
    const DELETE_ACCOUNT = 8; // 注销账号
    const UPGRADE_LEVEL = 9; // 解锁广告库存

    public static array $typeMap = [
        self::LOGIN               => '登录',
        self::REGISTER            => '注册',
        self::RECHARGE            => '充值',
        self::WITHDRAW            => '提现',
        self::BIND_CARD           => '绑定钱包',
        self::CHANGE_PASSWORD     => '修改密码',
        self::CHANGE_PAY_PASSWORD => '修改支付密码',
        self::SET_PAY_PASSWORD    => '设置安全密码',
//        self::DELETE_ACCOUNT      => '注销账号',
        self::UPGRADE_LEVEL      => '解锁广告库存',
    ];

    public function user(): HasOne
    {
        return $this->hasOne(MemberModel::class, 'id', 'user_id')->field('id,user_name');
    }

    /**
     * 记录用户操作日志
     * @param $userid
     * @param  int  $type
     */
    public static function insertLoginLog($userid, int $type = 0)
    {
        $ip = getip();
        $uuid = Request::instance()->header('uuid', '');
        $login_log = [
            'user_id' => $userid,
            'ip'      => $ip,
            'type'    => $type,
            'area'    => ip2location($ip),
            'device'  => getClientBrowserInfo(),
            'uuid'    => $uuid, // 设备唯一标识
        ];
        self::create($login_log);

        // 判断这个用户id和ip是否已经保存
        $member_ip = MemberIpModel::where('user_id', $userid)->where('ip', $ip)->find();
        if ($member_ip) {
            $member_ip->update_time = time();
            $member_ip->save();
        } else {
            MemberIpModel::create([
                'user_id' => $userid,
                'ip'      => $ip
            ]);
        }
    }


    public static function getUserLatestLog($user_id)
    {
        // 获取最新的用户设备id
        return MemberLogModel::field('max(id), user_id, uuid')->where('user_id', $user_id)->find();
    }

    public function userIp(): HasMany
    {
        return $this->hasMany(MemberIpModel::class, 'ip', 'ip');
    }
}
