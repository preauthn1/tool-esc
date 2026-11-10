<?php

namespace app\admin\controller;

use app\admin\model\CollectionDetailModel;
use app\admin\model\CollectionModel;
use app\admin\model\RechargeModel;
use app\common\model\ConfigModel;
use app\common\model\PaymentPlatformModel;
use think\Exception;
use think\facade\Log;
use think\facade\View;

class Collection extends Base
{
    public function index()
    {
        if (request()->isAjax()) {
            $param = input('param.');
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            $limit = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;
            $where = [];
            if (isset($param['name']) && $param['name'] != '') {
                $where[] = ['name', 'LIKE', "%{$param['name']}%"];
            }
            if (!empty($param['loop'])) {
                $where[] = ['loop', '', $param['loop']];
            }
            if (!empty($param['startTime']) && !empty($param['endTime'])) {
                $start_time = strtotime($param['startTime']." 00:00:00");
                $end_time = strtotime($param['endTime']." 23:59:59");
                $where[] = ['create_time', 'BETWEEN', [$start_time, $end_time]];
            }
            if (!empty($param['searchText'])) {
                $where[] = ['batch_no', 'like', '%'.$param['searchText'].'%'];
            }
            $model = new CollectionModel();
            $result = $model
                ->with('detail')
                ->where($where)
                ->limit($offset, $limit)
                ->order(['id' => 'DESC'])
                ->select();
            $total = $model->where($where)
                ->limit($offset, $limit)
                ->count();
            foreach ($result as $key => $vo) {
//                $result[$key]['status_str'] = $vo['status'] ? '已完成' : '处理中';
                $result[$key]['create_time_str'] = date("d/m/Y H:i:s", strtotime($vo['create_time']));
                $result[$key]['finish_time_str'] = $vo['finish_time'] ? date("d/m/Y H:i:s", $vo['finish_time']) : null;
            }
            $return['total'] = $total;  // 总数据
            $return['rows'] = $result;
            return json($return);
        }
        return View::fetch();
    }

    /**
     * 发起归集
     * @param $type
     */
    public function swapCollect($type)
    {
        $redis = \think\facade\Cache::store('redis');
        $ret = $redis->get('swap_collect'.$type);
        if (empty($ret)) {
            $redis->set('swap_collect'.$type, 1, 60 * 3);
        } else {
            $this->error(lang("正在归集中，请稍后"));
        }
        try {
            if (in_array($type, ['TRC20', 'TRX'])) {
                // 获取配置的归集地址
                $address = ConfigModel::getConfigValue('collection_wallet');
            } else {
                if (in_array($type, ['BEP20', 'BNB'])) {
                    // 获取配置的归集地址
                    $address = ConfigModel::getConfigValue('bep20_collection_wallet');
                } else {
                    $this->error(lang("参数有误"));
                }
            }

            if (!$address) {
                $this->error(lang("未配置归集地址"));
            }
            $res = PaymentPlatformModel::swapCollect($address, 0, $type);

            $data = $res['data'];
            // 记录
            CollectionModel::create([
                'batch_no'    => $data['batchNo'],
                'chain'       => $data['chain'],
                'coin'        => $data['coin'],
                'count'       => $data['count'],
                'pending'     => $data['count'],
                'merchant_no' => $data['merchantNo'],
                'create_time' => time(),
                'update_time' => time(),
            ]);
            $this->success(lang('归集成功'));
        } catch (Exception $e) {
            Log::write($e->getMessage()."\n".$e->getTraceAsString(), "error");
            $this->error(lang('归集失败原因：').lang($e->getMessage()));
        }
    }

    /**
     * 归集记录 查询归集
     * @param $id
     * @return int[]|mixed
     */
    public function swapRecord($id)
    {
        $collection = CollectionModel::find($id);
        if (!$collection) {
            $this->error(lang('归集记录不存在'));
        }
        $count = $collection->count;
        // 归集结果
        try {
            $res = PaymentPlatformModel::swapRecord($collection, 1, $count);
        } catch (Exception $e) {
            $this->error(lang('查询归集结果失败，原因：').lang($e->getMessage()));
        }
        $success = 0;
        $fail = 0;
        $money = 0;

        $data = $res['data']['records'];
        $detail = [];
        foreach ($data as $item) {
            switch ($item['status']) {
                case 3:
                    $success++;
                    $money += $item['amount'];
                    // 设置充值订单为已归集
                    RechargeModel::where('hash', $item['hash'])->update(['collection' => 1]);
                    break;
                case 4:
                    $fail++;
                    break;
                default:
            }
            $detail[] = [
                'collection_id' => $collection->id,
                'amount'        => $item['amount'],
                'chain'         => $item['chain'],
                'coin'          => $item['coinName'],
                'from_address'  => $item['fromAddress'],
                'to_address'    => $item['toAddress'],
                'hash'          => $item['hash'],
                'status'        => $item['status'],
                'create_time'   => time(),
            ];
        }

        $pending = $count - $success - $fail;
        $collection->success = $success;
        $collection->pending = $pending;
        $collection->fail = $fail;
        $collection->money = $money;
        if ($success + $fail == $collection->count) {
            // 查询如果写入过就不再写入
            $collection_detail = CollectionDetailModel::where('collection_id', $collection->id)->find();
            if (!$collection_detail) {
                CollectionDetailModel::insertAll($detail);
            }
            $collection->finish_time = time();
            $collection->status = 1; // 已完成
            $collection->save();
            $this->success(lang('查询成功').' '.lang('已完成：').$success.' '.lang('失败：').$fail);
        }
        $collection->save();
        $this->success(lang('查询成功').' '.lang('已完成：').$success.' '.lang('处理中：').$pending.' '.lang('失败：').$fail);

    }

}
