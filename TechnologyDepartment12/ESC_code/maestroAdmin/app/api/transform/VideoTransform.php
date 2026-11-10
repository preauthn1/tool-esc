<?php
namespace app\api\transform;

use app\admin\transform\Transform;
use app\common\model\VideoModel;

class VideoTransform extends Transform
{
    public function transform($items): array
    {
        $items['pic'] = toMedia($items['pic'], '/upload/', 1);
        $items['video_url'] = toMedia($items['video_url']);
        $items['duration_time'] = $this->secondToTime($items['duration']);
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
}
