<?php

namespace app\common\model;

use think\Model;

class ShareInfoModel extends Model
{
    // 确定链接表名
    protected $name = 'share_info';

    /**
     * 更新数据
     * @param $share_id
     * @param $all_data
     */
    public static function addInfo($share_id, $all_data)
    {
        $up_data = [];
        $add_data = [];
        $has = self::where('share_id', $share_id)->column('lang', 'id');
        foreach ($all_data as $v) {
            if (in_array($v['lang'], $has)) {
                $up_data[] = $v;
            } else {
                $v['create_time'] = time();
                $v['update_time'] = time();
                $add_data[] = $v;
            }
        }
        foreach ($up_data as $v) {
            $map = [
                'share_id' => $share_id,
                'lang' => $v['lang']
            ];
            $up_data = [
                'title' => $v['title'],
                'step' => $v['step'],
                'content' => $v['content'],
                'update_time' => time()
            ];
            self::where($map)->update($up_data);
        }
        self::insertAll($add_data);
    }
}
