<?php
namespace app\admin\transform;

use app\common\model\VideoModel;

class VideoTransform extends Transform
{
    public function transform($items): array
    {
        $status = config('app.status');
        $items['operate'] = showOperate($this->makeButton($items['id']));
        $items['status'] = $status[$items['status']];
        $items['pic'] = show_img(toMedia($items['pic'], '/upload/', 1), true);
        $items['video_url'] = show_img(toMedia($items['video_url'], '/upload/', 1), true);
        $items['duration'] = $this->secondToTime($items['duration']);
        $items['cate_str'] = VideoModel::$cateMap[$items['cate']];

        return $items;
    }

    // 秒转 分格式 00:00:00
    private function secondToTime($second): string
    {
        $h = floor($second / 3600);
        $m = floor(($second % 3600) / 60);
        $s = $second % 60;
        if ($h == 0) {
            return sprintf("%02d:%02d", $m, $s);
        } else {
            return sprintf("%02d:%02d:%02d", $h, $m, $s);
        }
    }

    /**
     * 拼装操作按钮
     * @param $id
     * @return array
     */
    private function makeButton($id): array
    {
        return [
            '编辑' => [
                'auth' => 'video/add',
                'href' => url('video/add', ['id' => $id]),
                'btnStyle' => 'primary',
                'icon' => 'fa fa-paste'
            ],
            '删除' => [
                'auth' => 'video/del',
                'href' => "javascript:del(" . $id . ")",
                'btnStyle' => 'danger',
                'icon' => 'fa fa-trash-o'
            ]
        ];
    }
}
