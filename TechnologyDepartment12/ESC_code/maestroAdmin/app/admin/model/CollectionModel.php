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

namespace app\admin\model;

use app\common\model\PaymentPlatformModel;
use app\exception\UpayException;
use think\Exception;
use think\Model;
use think\model\relation\HasMany;

class CollectionModel extends Model
{

    // 确定链接表名
    protected $name = 'collection';

    public function detail(): HasMany
    {
        return $this->hasMany(CollectionDetailModel::class, 'collection_id', 'id');
    }

    public static function swapRecord($recharge_no, $collection)
    {
        try {
            $count = $collection->count;
            $res = PaymentPlatformModel::swapRecord($collection, 1, $count);
        } catch (Exception $e) {
            throw new UpayException(lang('查询归集结果失败，原因：').lang($e->getMessage()));
        }
        $data = $res['data']['records'][0];
        $collection_detail = CollectionDetailModel::where('recharge_no', $recharge_no)->find();
        $collection_detail_data = [
            'collection_id' => $collection->id,
            'amount'        => $data['amount'],
            'chain'         => $data['chain'],
            'coin'          => $data['coinName'],
            'from_address'  => $data['fromAddress'],
            'to_address'    => $data['toAddress'],
            'hash'          => $data['hash'],
            'status'        => $data['status'], // 1-处理中 3-成功 4-失败
            'recharge_no'   => $recharge_no,
        ];
        if (!$collection_detail) {
            $collection_detail_data['create_time'] = time();
            CollectionDetailModel::create($collection_detail_data);
        } else {
            // 更新
            CollectionDetailModel::where('collection_id', $collection->id)->update($collection_detail_data);
        }
        return $data;
    }


    /**
     * @param $data array
     * @param $user_address string 用户钱包地址
     * @param $address string 归集地址
     * @param $recharge_no string 充值单号
     * @return void
     */
    public static function singleSwap(array $data, string $user_address, string $address, string $recharge_no)
    {
        // 新增归集记录
        $collection = CollectionModel::create([
            'batch_no'    => $data['batchNo'],
            'chain'       => $data['chain'],
            'coin'        => $data['coin'],
            'count'       => $data['count'],
            'pending'     => $data['count'],
            'merchant_no' => $data['merchantNo'],
            'create_time' => time(),
            'update_time' => time(),
        ]);

        $collection->detail()->save([
            'collection_id' => $collection->id,
            'amount'        => 0,
            'chain'         => $data['chain'],
            'coin'          => $data['coin'],
            'from_address'  => $user_address,
            'to_address'    => $address,
            'hash'          => '',
            'status'        => 1, // 1-处理中 3-成功 4-失败
            'create_time' => time(),
            'recharge_no' => $recharge_no,
        ]);
    }
}
