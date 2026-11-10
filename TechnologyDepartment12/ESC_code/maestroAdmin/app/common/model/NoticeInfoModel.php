<?php

namespace app\common\model;

use think\Model;

class NoticeInfoModel extends Model
{
    // 确定链接表名
    protected $name = 'notice_info';

    /**
     * 更新数据
     * @param $share_id
     * @param $all_data
     */
    public static function addInfo($share_id, $all_data)
    {
        $up_data = [];
        $add_data = [];
        $has = self::where('notice_id', $share_id)->column('lang', 'id');
        foreach ($all_data as $v) {
            if (in_array($v['lang'], $has)) {
                $up_data[] = $v;
            } else {
                $v['update_time'] = time();
                $add_data[] = $v;
            }
        }
        foreach ($up_data as $v) {
            $map = [
                'notice_id' => $share_id,
                'lang' => $v['lang']
            ];
            $up_data = [
                'content' => $v['content'],
                'create_time' => $v['create_time'],
                'update_time' => time()
            ];
            self::where($map)->update($up_data);
        }
        self::insertAll($add_data);
    }
}
