<?php

namespace app\admin\controller;

use app\admin\model\ConfigModel;
use app\admin\transform\InvestmentProductSettlementTimeTransform;
use app\common\model\InvestmentProductSettlementTimeModel;
use think\db\exception\DbException;
use think\facade\Cache;
use think\facade\View;
use think\response\Json;

/**
 * 投资产品结算时间点
 * Class InvestmentProduct
 * @package app\admin\controller
 */
class InvestmentProductSettlementTime extends Base
{
    /**
     * 结算时间点列表
     * @return mixed
     * @throws DbException
     */
    public function index()
    {
        $title = lang('收益结算设置');
        $investment_product_settlement_cycle = ConfigModel::getConfigValue('investment_product_settlement_cycle');
        View::assign('title', $title);
        View::assign('investment_product_settlement_cycle', $investment_product_settlement_cycle);
        if ($this->request->isAjax()) {
            $param = input('param.');
            $page = input('page/d') ? input('page/d') : 1;   // 页数
            $pageSize = input('pageSize/d') ? input('pageSize/d') : 10;  // 每页条数

            $where = [];
            if (!empty($param['product_name'])) {
                $where[] = ['product_name', 'like', '%'.$param['product_name'].'%'];
            }
            $selectResult = InvestmentProductSettlementTimeModel::where($where)
                ->order('settlement_time')
                ->paginate(['page' => $page, 'list_rows' => $pageSize]);
            $data = (new InvestmentProductSettlementTimeTransform())->transformPages($selectResult->toArray());
            return json($data);
        }
        return View::fetch();
    }

    /**
     * 添加结算时间点
     * @return mixed
     */
    public function edit()
    {
        $id = $this->request->param("id/d");
        $model = new InvestmentProductSettlementTimeModel();
        if ($this->request->isPost()) {
            $param = input('post.');


            if ($id > 0) {
                $result = $model->where('id', $id)->update($param);
            } else {
                unset($param['id']);
                // 判断是否存在同名结算时间点
                $existing = $model->where('settlement_time', $param['settlement_time'])
                    ->find();
                if ($existing) {
                    $this->error(lang('该结算时间点已存在，请选择其他时间点'));
                }
                $result = $model->save($param);
            }
            if (false === $result) {
                $this->error($model->getError());
            } else {
                insert_operation_log('收益结算时间点:'.($id > 0 ? '修改' : '添加'), $param);
            }
            $this->success(lang('提交成功'), '', 'close');

        }
        $info = $model->where('id', $id)->find();
        View::assign("info", $info);
        return View::fetch();
    }


    /**
     * 删除结算时间点
     * @return Json
     */
    public function del()
    {
        $id = input('param.id');
        $info = InvestmentProductSettlementTimeModel::find($id);
        if ($info) {
            insert_operation_log('收益结算时间点:删除', $info->toArray());
            $info->delete();
        }

        return json(msg(1, url('index')->build(), lang('删除成功')));
    }

    /**
     * 修改结算时间点状态
     * @return Json
     */
    public function change_status(): Json
    {
        $id = input('param.id/d');
        $taskSessionModel = new InvestmentProductSettlementTimeModel();

        $result = $taskSessionModel->toggleStatus($id);
        if ($result !== false) {
            insert_operation_log('收益结算时间点:修改状态', ['id' => $id]);
            $this->clearCache();
            return json(msg(1, [], lang('修改成功')));
        }
        return json(msg(-1, [], lang('修改失败')));
    }

    public function clearCache()
    {
        $redis = Cache::store('redis');
        $redis_key = config("redis_cache_key.investment_product_settlement_time_list");
        $redis->delete($redis_key);
    }

}
