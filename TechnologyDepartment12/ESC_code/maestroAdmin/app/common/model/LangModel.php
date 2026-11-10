<?php

namespace app\common\model;

use think\Model;

class LangModel extends Model
{

    protected $autoWriteTimestamp = 'timestamp';
    // 确定链接表名
    protected $name = 'lang';

    /**
     * 获取语言名称
     * @param $lang
     * @return mixed|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getLangTitle($lang)
    {
        $map = [
            'lang' => $lang
        ];
        $temp = self::where($map)->field('title')->find();
        if (empty($temp)) {
            return "";
        }
        return $temp['title'];
    }

    /**
     * @param $lang
     * @return array|bool|\PDOStatement|string|\think\Collection
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function isEnabled($lang)
    {
        $map = [
            'status' => 1,
            'lang' => $lang
        ];
        $temp = self::where($map)->field('lang,title')->select();
        if (empty($temp)) {
            return [];
        }
        return $temp;
    }
}
