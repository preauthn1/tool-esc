<?php

namespace app\common\model;

use think\Model;

class PromotionModel extends Model
{
    // 确定链接表名
    protected $name = 'promotion';

    /**
     * 获取默认像素
     * @param string $platform
     * @return mixed|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getInfoDefaultPix($platform = 'facebook')
    {
        if (empty($platform)) {
            $platform = 'facebook';
        }
        $map = [
            'platform' => $platform,
            'is_default' => 1
        ];
        $temp = self::where($map)->find();
        if (empty($temp)) {
            return '878543217625154';
        }
        return $temp['pix'];
    }

    /**
     * 获取默认像素
     * @return mixed|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    /**
     * 获取默认像素
     * @param string $platform
     * @return mixed|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getInfoDefaultPf($platform = 'facebook')
    {
        if (empty($platform)) {
            $platform = 'facebook';
        }
        $map = [
            'platform' => $platform,
            'is_default' => 1
        ];
        $temp = self::where($map)->find();
        if (empty($temp)) {
            return 'facebook';
        }
        return $temp['platform'];
    }

    /**
     * 获取token
     * @param $pix
     * @return mixed|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getAccessToken($pix, $platform = 'facebook')
    {
        if (empty($pix)) {
            $pix = self::getInfoDefaultPix();
        }
        if (empty($platform)) {
            $platform = "facebook";
        }
        $map = [
            'platform' => $platform,
            'pix' => $pix
        ];
        $temp = self::where($map)->field('access_token')->find();
        if (empty($temp)) {
            return '';
        }
        return $temp['access_token'];
    }
}
