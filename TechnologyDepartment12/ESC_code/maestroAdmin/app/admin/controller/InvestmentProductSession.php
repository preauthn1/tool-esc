<?php

namespace app\admin\controller;

use app\common\model\InvestmentProductSessionModel;
use think\facade\Cache;
use think\facade\View;
use think\response\Json;

class InvestmentProductSession extends Base
{
    /**
     * 任务时段列表
     */
    public function index()
    {
        if (request()->isAjax()) {
            $where = [];
            $investmentProductSessionModel = new InvestmentProductSessionModel();

            $total = $investmentProductSessionModel->getinvestmentProductSessionCount($where);
            $list = $investmentProductSessionModel->getinvestmentProductSessionList($where);
            foreach ($list as &$item) {
                $item['status_text'] = InvestmentProductSessionModel::$statusMap[$item['status']];
                $item['operate'] = showOperate([
                    '编辑' => [
                        'auth' => 'investmentProductSession/edit',
                        'href' => url('InvestmentProductSession/edit', ['id' => $item['id']])->build(),
                        'btnStyle' => 'primary',
                        'icon' => 'fa fa-paste'
                    ],
                    '删除' => [
                        'auth' => 'investmentProductSession/del',
                        'href' => "javascript:del(" . $item['id'] . ")",
                        'btnStyle' => 'danger',
                        'icon' => 'fa fa-trash-o'
                    ]
                ]);
            }

            return json(['total' => $total, 'rows' => $list]);
        }

        return View::fetch();
    }

    /**
     * 切换任务时段状态
     */
    public function change_status(): Json
    {
        $id = input('param.id/d');
        $investmentProductSessionModel = new InvestmentProductSessionModel();

        $result = $investmentProductSessionModel->toggleStatus($id);
        if ($result !== false) {
            insert_operation_log('任务时段:修改状态', ['id' => $id]);
            $this->clearCache();
            return json(msg(1, [], lang('修改成功')));
        }
        return json(msg(-1, [], lang('修改失败')));
    }

    /**
     * 添加/编辑任务时段
     */
    public function edit()
    {
        $id = input('param.id/d', 0);
        $investmentProductSessionModel = new InvestmentProductSessionModel();

        if (request()->isPost()) {
            $param = input('post.');
            $session = $param['session'];
            $session_arr = explode(' - ', $session);
            $start_time = $session_arr[0];
            $end_time = $session_arr[1];
            if ($start_time == $end_time) {
                $this->error(lang('开始时间和结束时间不能相同'));
            }
            $data = [
                'start_time' => $start_time,
                'end_time' => $end_time,
            ];

            if ($id > 0) {
                $result = $investmentProductSessionModel->updateinvestmentProductSession($data, $id);
            } else {
                $result = $investmentProductSessionModel->addinvestmentProductSession($data);
            }

            if (false === $result) {
                $this->error(lang('操作失败'));
            }
            insert_operation_log('任务时段:' . ($id > 0 ? '修改' : '添加'), $data);
            $this->clearCache();
            $this->success(lang('提交成功'), '', 'close');
        }

        $info = [];
        if ($id > 0) {
            $info = $investmentProductSessionModel->getOneinvestmentProductSession($id);
        } else {
            $level_id = input('param.level_id/d');
            $info = [
                'level_id' => $level_id,
                'start_time' => date('H:i:s'),
                'end_time' => date('H:i:s'),
            ];
        }

        View::assign('info', $info);
        return View::fetch();
    }

    /**
     * 删除任务时段
     */
    public function del()
    {
        $id = input('param.id/d');
        $investmentProductSessionModel = new InvestmentProductSessionModel();
        $result = $investmentProductSessionModel->deleteinvestmentProductSession($id);

        if ($result !== false) {
            insert_operation_log('任务时段:删除', ['id' => $id]);
            $this->clearCache();
            return json(msg(1, [], lang('删除成功')));
        }
        return json(msg(-1, [], lang('删除失败')));
    }

    public function clearCache()
    {
        $redis = Cache::store('redis');
        $redis_key = config("redis_cache_key.investment_product_session_list");
        $redis->delete($redis_key);
    }
}
