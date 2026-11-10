<?php


namespace app\admin\controller;

use app\admin\model\AdvertisSpaceModel as Model;
use think\facade\View;
use think\facade\Db;

/**
 * Class AdvertisSpace
 * @package app\admin\controller
 * 广告位配置
 */
class AdvertisSpace extends Base
{

    /**
     * @return string|\think\response\Json
     * 广告位列表
     */
    public function index()
    {
        if (request()->isAjax()) {
            $param = input('param.');
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            $limit = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;
            $where = [];
            if (!empty($param['searchText'])) {
                $where[] = ['advertis_name', 'like', '%' . $param['searchText'] . '%'];
            }
            $model = new Model();
            $selectResult = $model->getAdverByWhere($where, $offset, $limit);
            foreach ($selectResult as $key => $vo) {
                $selectResult[$key]['operate'] = showOperate($this->makeButton($vo['id']));
            }
//            $langs = getLangsColumn();
//            $status = config('app.status');
            $return['total'] = $model->getAllAdver($where);  //总数据
            $return['rows'] = $selectResult;
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
            $name_en = [
                '横幅广告' => 'Banner',
                '开屏广告' => 'Splash',
                '插屏广告' => 'Interstitial',
                '视频广告' => 'Video',
                '原生/信息流' => 'Native'
            ];
            $model = new Model;
            if ($id > 0) {
                if (isset($name_en[$param['advertis_name']])) {
                    $param['advertis_en_name'] = $name_en[$param['advertis_name']];
                } else {
                    $param['advertis_en_name'] = '';
                }
                $result = $model->where('id', $id)->update($param);
            } else {
                if (isset($name_en[$param['advertis_name']])) {
                    $param['advertis_en_name'] = $name_en[$param['advertis_name']];
                } else {
                    $param['advertis_en_name'] = '';
                }
                $result = $model->save($param);
            }
            if (false === $result) {
                $this->error($model->getError());
            }
            $this->clearCache();
            $this->success(lang('提交成功'), '', 'close');
        }
        $info = Model::where('id', $id)->find();
        View::assign("info", $info);
        View::assign("langs", getLangs());
        View::assign("status", config('app.status'));
        return View::fetch();
    }

    /**
     * 更新库存数量
     */
    public function update_stock()
    {

        if (request()->isAjax()) {

            $param = input('param.');
            $this->saveStock();
            $this->success(lang('提交成功'), '', 'close');
        }
        $rawList = Db::name('advertis_num')
            ->where('advertis_name', 'IN', function ($query) {
                $query->name('advertis_space')->distinct(true)->field('advertis_name');
            })
            ->field('advertis_name, num')
            ->select()
            ->toArray();
        $finalData = array_column($rawList, 'num', 'advertis_name');
        View::assign("info", $finalData);
        return View::fetch();
    }

    public function saveStock()
    {
        // 1. 接收前端数组
        $params = request()->param();

        // 2. 定义需要过滤的非业务字段
        $excludeFields = ['id'];
        $currentTime = time();
        $saveCount = 0;

        // 开启事务处理，保证数据一致性
        Db::startTrans();
        try {
            foreach ($params as $name => $num) {
                // 过滤掉 id 字段，且只处理数值不为空的项
                if (!in_array($name, $excludeFields) && $num !== '') {
                    $data = [
                        'advertis_name' => $name,
                        'num' => floatval($num),
                        'update_time' => $currentTime
                    ];
                    // 3. 核心逻辑：根据 advertis_name 匹配
                    $exists = Db::name('advertis_num')
                        ->where('advertis_name', $name)
                        ->find();

                    if ($exists) {
                        // 如果存在，执行更新
                        Db::name('advertis_num')
                            ->where('id', $exists['id'])
                            ->update($data);
                    } else {
                        // 如果不存在，添加创建时间并新增
                        $data['create_time'] = $currentTime;
                        Db::name('advertis_num')->insert($data);
                    }
                    $saveCount++;
                }
            }
            // 提交事务
            Db::commit();
            return json(['code' => 1, 'msg' => "成功处理 {$saveCount} 项库存数据"]);

        } catch (\Exception $e) {
            // 出错回滚
            Db::rollback();
            return json(['code' => 0, 'msg' => '入库失败：' . $e->getMessage()]);
        }
    }

    /**
     * 查询上一次的库存数量
     */
    public function query_last_stock()
    {
        $rawList = Db::name('advertis_num')
            ->where('advertis_name', 'IN', function ($query) {
                $query->name('advertis_space')->distinct(true)->field('advertis_name');
            })
            ->field('advertis_name, num')
            ->select()
            ->toArray();
        $finalData = array_column($rawList, 'num', 'advertis_name');
        $return['rows'] = [$finalData];
        $return['total'] = 1;
        return json($return);
    }


    public function update()
    {
//        $id = input('param.id');
//
//        $role = new Model();
//        $flag = $role->delBanner($id);
//        $this->clearCache();
//        return json(msg($flag['code'], $flag['data'], lang($flag['msg'])));
    }

    public function del()
    {
        $id = input('param.id');

        $model = new Model();
        $flag = $model->delSpace($id);
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
                'auth' => 'advertis_space/add',
                'href' => url('advertis_space/add', ['id' => $id]),
                'btnStyle' => 'primary',
                'icon' => 'fa fa-paste'
            ],
            '删除' => [
                'auth' => 'advertis_space/del',
                'href' => "javascript:del(" . $id . ")",
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

    }
}