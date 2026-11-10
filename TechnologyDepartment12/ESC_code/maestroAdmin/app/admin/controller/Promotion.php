<?php

// +----------------------------------------------------------------------
// | snake
// +----------------------------------------------------------------------
// | Copyright (c) 2016~2022 http://baiyf.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: NickBai <1902822973@qq.com>
// +----------------------------------------------------------------------

namespace app\admin\controller;

use app\admin\model\MemberModel;
use app\common\model\PromotionModel;
use think\facade\View;

class Promotion extends Base
{

    // 推广链接
    public function index()
    {
        $default_type = [
            0 => '否',
            1 => '是',
        ];
        if (request()->isAjax()) {
            $param = input('param.');
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            $limit = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;
            $where = [];
            if (!empty($param['account_type'])) {
                $where[] = ['account_type', '', $param['account_type']];
            }
            $model = new PromotionModel();
            $result = $model->where($where)->limit($offset, $limit)->order(['id' => 'DESC'])->select();
            $total = $model->where($where)->limit($offset, $limit)->count();
            foreach ($result as $key => $vo) {
                $result[$key]['default_str'] = $default_type[$vo['is_default']];
                $result[$key]['operate'] = showOperate($this->makeButton($vo['id']));
            }
            $return['total'] = $total;  // 总数据
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
            $model = new PromotionModel();
            if ($param['is_default'] == 1) {
                $model->where(['is_default' => 1])->update(['is_default' => 0]);
            }
            if ($id > 0) {
                $param['update_time'] = time();
                if (empty($param['user_name'])) {
                    $this->error('请输入用户名');
                }
                $user = MemberModel::where(['user_name' => $param['user_name']])->find();
                if (empty($user)) {
                    $this->error('用户不存在');
                }
                //https://www.nbcfandango.com/#/?ic=92100&pid=41
                $host = "https://" . str_replace('admin', 'www', $_SERVER['HTTP_HOST']);
                $param['url'] = $host . '/#/?ic=' . $user['invite_code'] . "&pid=" . $param['id'];
                $result = $model->where(['id' => $id])->update($param);
            } else {
                unset($param['id']);
                $param['create_time'] = time();
                $param['update_time'] = time();
                if (empty($param['user_name'])) {
                    $this->error('请输入用户名');
                }
                $param['url'] = '';
                $user = MemberModel::where(['user_name' => $param['user_name']])->find();
                if (empty($user)) {
                    $this->error('用户不存在');
                }
                $host = "https://" . str_replace('admin', 'www', $_SERVER['HTTP_HOST']);
                $result = $model->save($param);
                $param['id'] = $model->getLastInsID();
                $url = $host . '/#/?ic=' . $user['invite_code'] . "&pid=" . $param['id'];
                $model->where('id', $param['id'])->update(['url' => $url]);
            }
            if (false === $result) {
                $this->error($model->getError());
            }
            $this->success(lang('提交成功'), '', 'close');
        }
        $info = PromotionModel::where('id', $id)->find();
        if (empty($info)) {
            //id: 41
            //platform: tiktok
            //name: 测试
            //user_name: test001@qq.com
            //pix: CR6PHPBC77UFSKJEKQS0
            //test_event_code:
            //REDACTED
            //is_default: 0
            $info = [
                'id' => '',
                'platform' => '',
                'name' => '',
                'user_name' => '',
                'pix' => '',
                'url' => '',
                'test_event_code' => '',
                'access_token' => '',
                'is_default' => 0,
            ];
        }
        $default_type = [
            0 => '否',
            1 => '是',
        ];
        View::assign("info", $info);
        View::assign("default_type", $default_type);
        return View::fetch('add');

    }

    /**
     * 删除任务
     * @param $id
     */
    public function del($id)
    {
        $ids = implode(',', $id);
        $plan_model = new PromotionModel();
        $plan_model->where('id', 'in', $ids)->delete();
        $this->success(lang('删除成功'), url('index')->build());
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
                'auth' => 'share/add',
                'href' => url('add', ['id' => $id]),
                'btnStyle' => 'primary',
                'icon' => 'fa fa-paste'
            ]
        ];
    }
}
