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

use app\common\model\CustomerServiceModel;
use think\facade\View;
use think\response\Json;

class CustomerService extends Base
{

    //用户列表
    public function index()
    {
        if (request()->isAjax()) {
            $param = input('param.');
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            $limit = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;
            $where = [];
            if (!empty($param['name'])) {
                $where[] = ['name', 'like', '%' . $param['name'] . '%'];
            }
            $customer_service = new CustomerServiceModel();
            $selectResult = $customer_service->getCustomerServiceByWhere($where, $offset, $limit);
            // 拼装参数
            foreach ($selectResult as $key => $vo) {
                $selectResult[$key]['image'] = $vo['image'] ? '<img src="' . $vo['image'] . '" width="40px" height="40px">' : '';
                $selectResult[$key]['operate'] = showOperate($this->makeButton($vo['id']));
            }
            $return['total'] = $customer_service->getAllCustomerService($where);  //总数据
            $return['rows'] = $selectResult;
            return json($return);
        }
        return View::fetch();
    }

    public function add()
    {
        $id = $this->request->param("id/d");
        $model = new CustomerServiceModel();
        if (request()->isPost()) {
            $param = input('post.');
            unset($param['file']);
            if (!array_key_exists('status', $param)) {
                $param['status'] = 0;
            }

            if ($id > 0) {
                $result = $model->where('id', $id)->update($param);
            } else {
                $result = $model->save($param);
            }
            if (false === $result) {
                $this->error($model->getError());
            } else {
                insert_operation_log('客服設置:' . ($id > 0 ? '修改' : '添加'), $param);
            }
            $this->clearCache();
            $this->success(lang('提交成功'), url('index')->build(), 'close');
        }
        $info = $model->where('id', $id)->find();
        if (empty($info)) {
            // `id` int unsigned NOT NULL AUTO_INCREMENT,
            //  `image` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '图标',
            //  `link` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL DEFAULT '' COMMENT '链接',
            //  `name` varchar(255) CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NOT NULL COMMENT '名称',
            //  `sort` tinyint(1) NOT NULL DEFAULT '0' COMMENT '排序',
            //  `status` tinyint(1) NOT NULL DEFAULT '0' COMMENT '是否启用',
            $info = [
                'id' => '',
                'image' => '',
                'link' => '',
                'name' => '',
                'sort' => 0,
                'status' => 1,
            ];
        }
        View::assign("info", $info);
        return View::fetch();
    }

    // 删除客服
    public function customerServiceDel(): Json
    {
        $id = input('param.id');
        $customer_service = new CustomerServiceModel();
        $flag = $customer_service->delCustomerService($id);
        $this->clearCache();
        return json(msg($flag['code'], $flag['data'], lang($flag['msg'])));
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
                'auth' => 'customerService/add',
                'href' => url('customer_service/add', ['id' => $id])->build(),
                'btnStyle' => 'primary',
                'icon' => 'fa fa-paste'
            ],
//            '设置' => [
//                'auth' => 'level/setting',
//                'href' => url('level/setting', ['id' => $id])->build(),
//                'btnStyle' => 'info',
//                'icon' => 'fa fa-gear'
//            ],
            '删除' => [
                'auth' => 'customerService/customerServiceDel',
                'href' => "javascript:customerServiceDel(" . $id . ")",
                'btnStyle' => 'danger',
                'icon' => 'fa fa-trash-o'
            ]
        ];
    }

    /**
     * 清空缓存
     */
    public function clearCache()
    {
        $redis = \think\facade\Cache::store('redis');
        $redis_key = config("redis_cache_key.customer_service");
        $redis->delete($redis_key);
    }
}
