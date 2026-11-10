<?php

namespace app\common\model;

use think\Model;

class CustomerServiceModel extends Model
{

    // 确定链接表名
    protected $name = 'customer_service';
    protected $autoWriteTimestamp = true;

    public function getCustomerServiceByWhere($where, $offset, $limit) {
        return $this->where($where)->limit($offset, $limit)->order('sort desc')->select();
    }

    public function getAllCustomerService($where) {
        return $this->where($where)->count();
    }

    public function delCustomerService($id) {
        try{
            $param = self::get($id);
            $this->where('id', $id)->delete();
            if ($param) {
                insert_operation_log('客服:删除', "{$param['name']}");
            }
            return msg(1, '', '删除客服成功');

        }catch(\Exception $e){
            return msg(-1, '', $e->getMessage());
        }
    }

}
