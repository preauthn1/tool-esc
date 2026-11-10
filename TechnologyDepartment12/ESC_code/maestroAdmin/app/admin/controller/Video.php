<?php

namespace app\admin\controller;

use app\admin\transform\VideoTransform;
use app\common\model\VideoModel;
use app\common\repository\VideoRepository;
use think\facade\View;
use think\response\Json;

class Video extends Base
{

    public function index(VideoTransform $transform, VideoRepository $repository)
    {
        if (request()->isAjax()) {
            $param = $this->request->param();
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            $limit = $param['pageSize'];
            $page = $param['pageNumber'];
            $where = [];
            if (isset($param['name']) && $param['name'] != '') {
                $where[] = ['name', 'LIKE', "%{$param['name']}%"];
            }
            if (isset($param['director']) && $param['director'] != '') {
                $where[] = ['director', 'LIKE', "%{$param['director']}%"];
            }
            if (isset($param['status']) && $param['status'] != '') {
                $where[] = ['status', '=', $param['status']];
            }
            if (isset($param['cate']) && $param['cate'] != '') {
                $where[] = ['cate', '=', $param['cate']];
            }
            $result = $repository->with([
                'level' => function ($query) {
                    $query->field('id,level_id,level_name');
                }
            ])->order(['id' => 'desc'])->where($where)->paginate(['page' => $page, 'list_rows' => $limit]);
            $data = $transform->transformPages($result->toArray());
            return json($data);
        }
        View::assign('cate_type', VideoModel::$cateMap);
        return View::fetch();
    }

    public function add(VideoRepository $repository)
    {
        $video_id = $this->request->param("id/d", 0);
        if (request()->isPost()) {
            //id: 384
            //cate: 4
            //pic: https://broag.s3.sa-east-1.amazonaws.com/image/20250313/252ac216c2090d8962b5453c83e4221c.png
            //file:
            //name: Baixo risco e escalabilidade ilimitada
            //video_url: https://broag.s3.dualstack.sa-east-1.amazonaws.com/video/flash-2.mp4
            //description: Diferente dos modelos tradicionais, que exigem grandes investimentos iniciais e oferecem pouca flexibilidade, a PubMatic proporciona uma oportunidade de crescimento sem barreiras financeiras.
            //duration: 1
            //sort: 0
            //watch_times: 89564
            //status: 1
            $param['id'] = $this->request->param("id/d", 0);
            $param['cate'] = $this->request->param("cate/d", 0);
            $param['pic'] = $this->request->param("pic/s", '');
            $param['name'] = $this->request->param("name/s", '');
            $param['sub_name'] = $this->request->param("sub_name/s", '');
            $param['video_url'] = $this->request->param("video_url/s", '');
            $param['description'] = $this->request->param("description/s", '');
            $param['duration'] = $this->request->param("duration/d", 0);
            $param['sort'] = $this->request->param("sort/d", 0);
            $param['watch_times'] = $this->request->param("watch_times/d", 0);
            $param['status'] = $this->request->param("status/d", 1);
            $param['remark'] = $this->request->param("remark/s", '');
            $result = $this->validate($param, 'VideoValidate');
            if (true !== $result) {
                return json(msg(-1, '', lang($result)));
            }
            $model = new VideoModel();
            if ($video_id > 0) {
                $result = $model->where('id', $video_id)->update($param);
            } else {
                $result = $model->save($param);
            }
            if (false === $result) {
                $this->error($model->getError());
            } else {
                insert_operation_log('视频管理:' . ($video_id > 0 ? '修改' : '添加'), $param);
            }
            $url = url('index')->build();
            $this->clearCache();
            $this->success(lang('提交成功'), $url, 'close');
        }

        $info = $repository->findWhere([['id', '=', $video_id]]);
        if (empty($info)) {
            //id: 383
            //cate: 4
            //pic: https://maestr.s3.sa-east-1.amazonaws.com//image/20250712/f0ec6ba97e3d5ddd595dbe2fe60ace42.png
            //file:
            //name: Como a Maestro Democratiza a Publicidade om Dois Tipos de Agentes
            //video_url: https://maestr.s3.dualstack.sa-east-1.amazonaws.com/video/2025-07-12/30be4b50aabae44325d157eadf80d6b4bda26840.mp4
            //description: 11111
            //duration: 2
            //sort: 0
            //watch_times: 98521
            //status: 1
            $info = [
                'id' => '',
                'cate' => '',
                'pic' => '',
                'name' => '',
                'video_url' => '',
                'description' => '',
                'duration' => '',
                'sort' => '',
                'watch_times' => '',
                'status' => 1,
            ];
        }
        View::assign("status", config('app.status'));
        View::assign("info", $info);
        View::assign('cate_type', VideoModel::$cateMap);
        View::assign('base_url', config('app.aws_s3.url'));
        return View::fetch();
    }

    public function del()
    {
        $id = $this->request->param('id/d');
        $info = VideoModel::where('id', $id)->find();
        if ($info) {
            $info->delete();
        }
        $this->clearCache();
        $this->success(lang('删除成功'), url('index')->build());
    }

    // 变更视频状态
    public function checkStatus($id, $status, $type = 0): Json
    {
        $video = new VideoModel();
        if ($type == 1) {
            $ids = implode(',', $id);
            $ret = $video->updateStatus(['status' => $status], ['id' => ['in', $ids]]);
        } else {
            $ret = $video->updateStatus(['status' => $status], ['id' => $id]);
        }
        return json($ret);
    }

    /**
     * 清空缓存
     */
    public function clearCache()
    {
        $redis = \think\facade\Cache::store('redis');
        $redis_key = config("redis_cache_key.index_video_list");
        $redis->delete($redis_key);
        $redis_key = config("redis_cache_key.index_cate_video");
        $redis->delete($redis_key);
    }
}
