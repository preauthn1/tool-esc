<?php

namespace app\common\model;

use think\Model;

class ShareModel extends Model
{
    const ACCOUNT_TYPE_BASE = 1;//基础账号
    const ACCOUNT_TYPE_TYJ = 3;//彩金账号
    const ACCOUNT_TYPE_BROKERAGE = 5;//佣金账号

    static $ACCOUNT_TYPE_NAME = [
        self::ACCOUNT_TYPE_BASE => "基础账号",
        self::ACCOUNT_TYPE_TYJ => "彩金账号",
        self::ACCOUNT_TYPE_BROKERAGE => "佣金账号",
    ];
    // 确定链接表名
    protected $name = 'share';

    /**
     * 获取首页分享活动
     * @param int $num
     * @return array
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getIndexList($num = 2, $lang = '')
    {
        $list = self::where('status', 1)->order('sort ASC')->limit($num)->select();
        $list_item = [];
        foreach ($list as $value) {
            $info = self::getInfoField($value['id'], $lang);
            $list_item[] = [
                'id' => $value['id'],
                'img' => $value['img'],
                'title' => $info['title'],
                'money' => number_format($value['money'], 2, '.', ','),
            ];
        }
        return $list_item;
    }

    /**
     * 获取标题字段名称
     * @param $share_id
     * @param $lang
     * @param string $field
     * @return array|bool|\PDOStatement|string|string[]|Model
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getInfoField($share_id, $lang, $field = 'title,step,content')
    {
        $map = [
            'share_id' => $share_id,
            'lang' => $lang,
        ];
        $temp = ShareInfoModel::where($map)->field($field)->find();
        if (empty($temp)) {
            return [
                'title' => '',
                'step' => '',
                'content' => '',
            ];
        }
        return $temp;
    }
}
