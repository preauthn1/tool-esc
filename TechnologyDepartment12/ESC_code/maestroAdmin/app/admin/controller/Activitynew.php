<?php

namespace app\admin\controller;

use app\admin\transform\ActivityNewTransform;
use app\common\model\ActivitynewModel;
use app\common\repository\ActivityNewPasswordRepository;
use app\common\repository\ActivityNewRechargeBonusRepository;
use think\facade\Cache;
use think\facade\View;

class Activitynew extends Base
{

    public function index()
    {
        if (request()->isAjax()) {
            $param = $this->request->param();
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            $limit = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;
            $where = [];
            if (isset($param['title']) && $param['title'] != '') {
                $where[] = ['title', 'LIKE', "%{$param['title']}%"];
            }
            $model = new ActivitynewModel();
            $result = $model->where($where)->limit($offset, $limit)->order(['id' => 'DESC'])->select()->toArray();
            $total = $model->where($where)->limit($offset, $limit)->count();
            $activity_new_type = config("my.activity_new_type");
            $activity_reward_type = config("my.activity_reward_type");
            foreach ($result as $key => $vo) {
                $result[$key]['operate'] = showOperate($this->makeButton($vo['id']));
                $result[$key]['status'] = $vo['status'] == 1 ? '启用' : '停用';
                $result[$key]['reward_type'] = $activity_reward_type[$vo['reward_type']];
                $result[$key]['type_str'] = $activity_new_type[$vo['type']];
                $result[$key]['thumb'] = $vo['thumb'] ? '<img src="' . toMedia($vo['thumb']) . '" width="40px" height="40px">' : '-';
                $result[$key]['start_time'] = date("Y-m-d H:i:s", $vo['start_time']);
                $result[$key]['end_time'] = date("Y-m-d H:i:s", $vo['end_time']);
            }
            $return['total'] = $total;  //总数据
            $return['rows'] = $result;
            return json($return);
        }
        return View::fetch();
    }

    public function add()
    {
        $id = $this->request->param("id/d");
        if (request()->isPost()) {
            $param = input('post.');
            unset($param['file']);
            $model = new ActivitynewModel();
            $param['start_time'] = strtotime($param['start_time']);
            $param['end_time'] = strtotime($param['end_time']);
            if ($param['start_time'] >= $param['end_time']) {
                $this->error(lang('请输入正确的开始和结束时间'));
            }
            $password = input('passwords', '');
            $recharge_bonuses = input('recharge_bonuses', '');
            unset($param['passwords']);
            unset($param['recharge_bonuses']);

            if (!isset($param['status'])) {
                $param['status'] = 0;
            }
            if ($param['type'] == 9) {
                $experience_reward = $param['experience_reward'];
                $param['reward'] = $experience_reward;
            }
            unset($param['experience_reward']);
            if ($id > 0) {
                $result = $model->where('id', $id)->update($param);
                $this->clearCache('activity_info', $id);
            } else {
                unset($param['id']);
                $map = [
                    'type' => 7
                ];
                if ($model->where($map)->find()) {
                    $this->error(lang('请勿重复添加'));
                }
                $result = ActivitynewModel::create($param);
                $id = $result->id;
            }
            if (false === $result) {
                $this->error($model->getError());
            }
            // 类型 8 和 9 都需要保存 password 和 recharge_bonuses
            if ($password && in_array($param['type'], [8, 9])) {
                (new ActivityNewPasswordRepository())->createOrUpdatePassword($id, $password);
            }
            if ($recharge_bonuses && in_array($param['type'], [8, 9])) {
                (new ActivityNewRechargeBonusRepository())->createOrUpdateRechargeBonus($id, $recharge_bonuses);
            }
            $url = url('index')->build();
            $this->clearCache('activity_list', '');
            $this->success(lang('提交成功'), $url);
        }
        $info = ActivitynewModel::where('id', $id)->find();
        if ($info) {
            $info = (new ActivityNewTransform())->transform($info->toArray());
        }
        $activity_new_type = [
            '7' => '邀请有效用户',
            '8' => '充值送金',
            '9' => '体验金',
        ];
        $user_type = config("my.activity_new_user_type");
        $activity_trigger_type = config("my.activity_new_user_type");
        $activity_reward_type = [
            '1' => '货币'
        ];
        View::assign("info", $info);
        View::assign("activity_new_type", $activity_new_type);
        View::assign("activity_reward_type", $activity_reward_type);
        View::assign("user_type", $user_type);
        View::assign("activity_trigger_type", $activity_trigger_type);
        View::assign('base_url', config('app.aws_s3.url'));

        return View::fetch();
    }

    // 删除用户
    public function del()
    {
        $id = input('param.id');
        $info = ActivitynewModel::where('id', $id)->find();
        if ($info) {
            $info->delete();
            $this->clearCache('activity_info', $id);
        }
        return json(msg(1, url('index')->build(), lang('删除成功')));
    }

    /**
     * 拼装操作按钮
     * @param $id
     * @return array
     */
    private function makeButton($id)
    {
        return [
            '编辑' => [
                'auth' => 'activitynew/add',
                'href' => url('add', ['id' => $id])->build(),
                'btnStyle' => 'primary',
                'icon' => 'fa fa-paste'
            ],
            '删除' => [
                'auth' => 'activitynew/del',
                'href' => "javascript:del(" . $id . ")",
                'btnStyle' => 'danger',
                'icon' => 'fa fa-trash-o'
            ]
        ];
    }

    /**
     * 清空缓存
     */
    public function clearCache($key, $value)
    {
        $redis = Cache::store('redis');
        $redis_key = config("redis_cache_key." . $key) . $value;
        $redis->delete($redis_key);
    }
}
