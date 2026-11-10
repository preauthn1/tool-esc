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

use app\admin\model\LevelModel;
use app\admin\model\ConfigModel;
use app\common\model\LevelInvestTimeModel;
use app\common\repository\LevelRepository;
use think\facade\Cache;
use think\facade\View;

class Level extends Base
{

    //level列表
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
                $where[] = ['level_name', 'like', '%' . $param['searchText'] . '%'];
            }
            $level = new LevelModel();
            $selectResult = $level->getLevelByWhere($where, $offset, $limit)->toArray();
            // 拼装参数
            foreach ($selectResult as $key => $vo) {
                $selectResult[$key]['img'] = $vo['img'] ? '<img src="' . toMedia($vo['img']) . '" width="40px" height="40px">' : '';
                $selectResult[$key]['operate'] = showOperate($this->makeButton($vo['id'], $vo['level_id']));
                $selectResult[$key]['type'] = LevelRepository::$typeMap[$vo['type']] ?? '未知类型';
            }
            $return['total'] = $level->getAllLevel($where);  //总数据
            $return['rows'] = $selectResult;
            return json($return);
        }
        return View::fetch();
    }

    public function add()
    {
        $id = $this->request->param("id/d");
        $model = new LevelModel();
        if (request()->isPost()) {
            $param = input('post.');
            unset($param['select']);

            if (!array_key_exists('is_show', $param)) {
                $param['is_show'] = 0;
            }
            if (!array_key_exists('is_can_buy', $param)) {
                $param['is_can_buy'] = 0;
            }
            if (isset($param['excess_invest_rate']) && !empty($param['excess_invest_rate'])) {
                $excess_invest_rate = json_decode($param['excess_invest_rate'], true);
                if ($param['invest_num'] < count($excess_invest_rate)) {
                    // 截取 $param['invest_num'] 个元素
                    $param['excess_invest_rate'] = json_encode(array_slice($excess_invest_rate, 0, $param['invest_num']));
                }
            }

            if ($id > 0) {
                $result = $model->where('id', $id)->update($param);
            } else {
                unset($param['id']);
                $result = $model->save($param);
            }
            if (false === $result) {
                $this->error($model->getError());
            } else {
                insert_operation_log('会员等级:' . ($id > 0 ? '修改' : '添加'), $param);
            }
            $this->clearCache('level_info', isset($param['level_id']) ? $param['level_id'] : '');
            $this->clearCache('level_list', '');
            $this->clearCache('level_types', '');
            $this->success(lang('提交成功'), '', 'close');
        }
        $info = $model->where('id', $id)->find();
        if (empty($info)) {
            $sort = LevelModel::max('sort');
            $level_id = LevelModel::max('level_id');
            $info = [
                'id' => '',
                'withdraw_week' => '',
                'sort' => intval($sort) + 1,
                'level_id' => $level_id + 1,
                'session_count'=>30,
                'unlock_time_start' => '00:00:00',
                'unlock_time_end' => '23:59:59'
            ];
        }
        $types = LevelRepository::$typeMap;
        View::assign('types', $types);
        View::assign("info", $info);
        return View::fetch();
    }

    // 删除level
    public function levelDel()
    {
        $id = input('param.id');
        $role = new LevelModel();
        $flag = $role->delLevel($id);
        return json(msg($flag['code'], $flag['data'], lang($flag['msg'])));
    }

    public function setting()
    {
        $id = $this->request->param("id/d");
        $level = new LevelModel();
        $info = $level->getOneLevel($id);
        if (empty($info)) {
            $this->error(lang('等级信息有误'));
        }
        if (request()->isPost()) {
            $param = $this->request->param();
            unset($param['id']);
            $ret = $level->save([
                'setting' => $param
            ], ['level_id' => $id]);
            if ($ret !== false) {
                $redis = Cache::store('redis');
                $redis_key = "home_user_level{$id}";
                $redis->delete($redis_key);
                insert_operation_log('会员等级:修改设置', $param);
                $this->success(lang('设置成功'));
            } else {
                $this->error(lang('设置失败'));
            }
        }
        $config = new ConfigModel();
        View::assign([
            'info' => $info,
            'config' => $config->getConfigInfo(),
        ]);
        return View::fetch();
    }

    /**
     * 广告位弹窗配置
     */
    public function finance_product()
    {
        $config_model = new ConfigModel();
        $config = $config_model->getConfigInfo();

        $finance_product_data = $config['finance_product_data'] ? json_decode($config['finance_product_data'], true) : [];
        View::assign([
            'config' => $config,
            'finance_product_data' => $finance_product_data,
        ]);
        return View::fetch();
    }

    /**
     * 平台规模配置
     */
    public function platform_scale()
    {
        $config_model = new ConfigModel();
        $config = $config_model->getConfigInfo();

        // 修改数据处理，确保office_locations和data_centers是字符串
        $platform_scale_data = $config['platform_scale_data'] ? json_decode($config['platform_scale_data'], true) : [
            'daily_impressions' => '',
            'daily_data_volume' => '',
            'advertiser_bid_amount' => '',
            'direct_publishers' => '',
            'office_locations' => '',
            'data_centers' => ''
        ];

        View::assign([
            'config' => $config,
            'platform_scale_data' => $platform_scale_data,
        ]);
        return View::fetch();
    }

    /**
     * 设置广告位编码
     */
    public function level_password()
    {
        // 获取所有等级
        $level_model = new LevelModel();
        if (request()->isAjax()) {
            if (request()->isGet()) {
                // 获取所有等级的数据，包含类型和编码
                $levels = $level_model
                    ->field('id,level_id,level_name,type,invest_password,password_expire_time,password_refresh_frequency')
                    ->where('is_show', 1)
                    ->select();

                // 按type分组
                $typeGroups = [];
                foreach ($levels as $level) {
                    $type = intval($level['type']);
                    if (!isset($typeGroups[$type])) {
                        // 将时间戳转换为日期时间字符串用于显示
                        $expire_time = '';
                        if (!empty($level['password_expire_time']) && $level['password_expire_time'] > 0) {
                            $expire_time = date('Y-m-d H:i:s', $level['password_expire_time']);
                        }
                        $typeGroups[$type] = [
                            'type' => $type,
                            'type_name' => LevelRepository::$typeMap[$type] ?? '未知类型',
                            'invest_password' => $level['invest_password'],
                            'password_expire_time' => $expire_time,
                            'password_expire' => $level['password_expire_time'] <= time() ? 1 : 0,
                            'password_expire_time_str' => $this->getTimeStr($level['password_expire_time'] - time()),
                            'password_refresh_frequency' => $level['password_refresh_frequency'],
                        ];
                    }
                }
                //$typeGroups对象变成数组
                $typeGroups = array_values($typeGroups);
                foreach ($typeGroups as $val) {
                    if ($val['password_expire'] == 1) {
                        //密码已经过期 自动更新
                        $updateData = [
                            'invest_password' => rand(100000, 999999)
                        ];
                        // 添加到期时间，如果有设置，将日期时间字符串转换为时间戳
                        if (!empty($val['password_refresh_frequency'])) {
                            $updateData['password_expire_time'] = time() + intval($val['password_refresh_frequency'] * 60);
                        } else {
                            $updateData['password_expire_time'] = 0; // 设置为0表示没有到期时间
                        }
                        $this->clearCache('level_password', $val['type']);
                        $level_model->where('type', $val['type'])->update($updateData);
                    }
                }
                $result = [
                    'code' => 1,
                    'data' => $typeGroups
                ];
                return json($result);
            }
        }

        // 获取所有等级的数据，包含类型和编码
        $levels = $level_model->field('id,level_id,level_name,type,invest_password,password_expire_time,password_refresh_frequency')
            ->where('is_show', 1)
            ->select();

        // 按type分组
        $typeGroups = [];
        foreach ($levels as $level) {
            $type = $level['type'];
            if (!isset($typeGroups[$type])) {
                // 将时间戳转换为日期时间字符串用于显示
                $expire_time = '';
                if (!empty($level['password_expire_time']) && $level['password_expire_time'] > 0) {
                    $expire_time = date('Y-m-d H:i:s', $level['password_expire_time']);
                }

                $typeGroups[$type] = [
                    'type' => $type,
                    'type_name' => LevelRepository::$typeMap[$type] ?? '未知类型',
                    'invest_password' => $level['invest_password'],
                    'password_expire_time' => $expire_time,
                    'password_expire_time_str' => $this->getTimeStr($level['password_expire_time'] - time()),
                    'password_refresh_frequency' => $level['password_refresh_frequency'],
                    'levels' => []
                ];
            }
            $typeGroups[$type]['levels'][] = $level;
        }

        View::assign([
            'typeGroups' => array_values($typeGroups)
        ]);
        return View::fetch();
    }


    /**
     * 设置广告位编码
     */
    public function level_password_update()
    {
        // 获取所有等级
        $level_model = new LevelModel();
        if (request()->isAjax() && request()->isPost()) {
            $param = input('post.');
            $types = isset($param['type']) ? $param['type'] : [];
            $passwords = isset($param['password']) ? $param['password'] : [];
            $minutes = isset($param['minutes']) ? $param['minutes'] : [];

            $successCount = 0;

            // 更新每个等级类型的密码
            foreach ($types as $key => $type) {
                // 修改这里的条件判断，确保 type=0 也能正确处理
                if ((is_numeric($type) || !empty($type)) && !empty($passwords[$key])) {
                    $updateData = [
                        'invest_password' => $passwords[$key]
                    ];

                    // 添加到期时间，如果有设置，将日期时间字符串转换为时间戳
                    if (!empty($minutes[$key])) {
                        $updateData['password_expire_time'] = time() + intval($minutes[$key] * 60);
                    } else {
                        $updateData['password_expire_time'] = 0; // 设置为0表示没有到期时间
                    }

                    // 保存更新频率（分钟）
                    if (!empty($minutes[$key]) && is_numeric($minutes[$key])) {
                        $updateData['password_refresh_frequency'] = intval($minutes[$key]);
                    }

                    $result = (int)$level_model->where('type', $type)->update($updateData);
                    if ($result) {
                        $this->clearCache('level_password', $type);
                        $successCount += $result; // 返回影响的行数
                    }
                }
            }

            if ($successCount > 0) {
                insert_operation_log('设置广告位编码', $param);
                return json(msg(1, [], lang('成功更新') . ' ' . $successCount . ' ' . lang('个编码')));
            } else {
                return json(msg(-1, [], lang('未更新任何编码')));
            }
        }
    }

    private function getTimeStr($time)
    {
        //小时
        $hour = floor($time / 3600);
        //分钟
        $min = floor(($time % 3600) / 60);
        $sec = $time % 60;
        if ($hour > 0) {
            return $hour . '小时' . $min . '分钟' . $sec . '秒';
        }
        return $min . '分钟' . $sec . '秒';
    }

    /**
     * 拼装操作按钮
     * @param $id
     * @return array
     */
    private function makeButton($id, $level_id)
    {
        return [
            //            '奖品' => [
            //                'auth' => 'level/add',
            //                'href' => url('turntable/prizeList', ['level_id' => $level_id])->build(),
            //                'btnStyle' => 'info',
            //                'icon' => 'fa fa-gear'
            //            ],
            //            '广告位时段' => [
            //                'auth' => 'level/investTime',
            //                'href' => url('level/investTime', ['level_id' => $level_id])->build(),
            //                'btnStyle' => 'warning',
            //                'icon' => 'fa fa-clock-o'
            //            ],
            '编辑' => [
                'auth' => 'level/add',
                'href' => url('level/add', ['id' => $id]),
                'btnStyle' => 'primary',
                'icon' => 'fa fa-paste'
            ],
            /* '删除' => [
                  'auth' => 'level/leveldel',
                  'href' => "javascript:levelDel(" .$id .")",
                  'btnStyle' => 'danger',
                  'icon' => 'fa fa-trash-o'
                  ] */
        ];
    }

    // 修改任务状态
    public function changeStatus()
    {
        $param = $this->request->param();

        $field = '';
        switch ($param['type']) {
            case 'is_show':
                $field = 'is_show';
                break;
            case 'is_can_buy':
                $field = 'is_can_buy';
                break;
            default:
                $this->error(lang('参数错误'));
        }
        $this->clearCache('level_info', $param['level_id']);
        $this->clearCache('level_list', '');
        $this->clearCache('level_types', '');
        $res = LevelModel::update([$field => !$param['val']], ['id' => $param['id']]);
        if ($res) {
            $this->success(lang('修改成功'));
        }

        $this->error(lang('修改失败'));
    }

    /**
     * 理财时段列表
     */
    public function investTime()
    {
        $level_id = input('param.level_id/d');
        if (request()->isAjax()) {
            $param = input('param.');
            $param['pageSize'] = !isset($param['pageSize']) ? 10 : intval($param['pageSize']);
            $param['pageNumber'] = !isset($param['pageNumber']) ? 1 : intval($param['pageNumber']);
            $limit = $param['pageSize'];
            $offset = ($param['pageNumber'] - 1) * $limit;

            $where = ['level_id' => $level_id];
            $investTimeModel = new LevelInvestTimeModel();

            $total = $investTimeModel->getInvestTimeCount($where);
            $list = $investTimeModel->getInvestTimeList($where, $offset, $limit);

            foreach ($list as &$item) {
                $item['status_text'] = LevelInvestTimeModel::$statusMap[$item['status']];
                $item['operate'] = showOperate([
                    '编辑' => [
                        'auth' => 'level/investTimeEdit',
                        'href' => url('level/investTimeEdit', ['id' => $item['id']]),
                        'btnStyle' => 'primary',
                        'icon' => 'fa fa-paste'
                    ],
                    '删除' => [
                        'auth' => 'level/investTimeDel',
                        'href' => "javascript:investTimeDel(" . $item['id'] . ")",
                        'btnStyle' => 'danger',
                        'icon' => 'fa fa-trash-o'
                    ]
                ]);
            }

            return json(['total' => $total, 'rows' => $list]);
        }

        $level = db('level')->where('level_id', $level_id)->find();
        View::assign('level', $level);
        return View::fetch();
    }

    /**
     * 切换理财时段状态
     */
    public function changeInvestTimeStatus()
    {
        $id = input('param.id/d');
        $investTimeModel = new LevelInvestTimeModel();

        $result = $investTimeModel->toggleStatus($id);
        if ($result !== false) {
            insert_operation_log('会员等级理财时段:修改状态', ['id' => $id]);
            return json(msg(1, [], lang('修改成功')));
        }
        return json(msg(-1, [], lang('修改失败')));
    }

    /**
     * 添加/编辑理财时段
     */
    public function investTimeEdit()
    {
        $id = input('param.id/d', 0);
        $investTimeModel = new LevelInvestTimeModel();

        if (request()->isPost()) {
            $param = input('post.');

            $data = [
                'level_id' => intval($param['level_id']),
                'start_time' => $param['start_time'],
                'end_time' => $param['end_time'],
                'invest_rate' => floatval($param['invest_rate']),
                'profit_rate' => floatval($param['profit_rate'])
            ];

            if ($id > 0) {
                $result = $investTimeModel->updateInvestTime($data, $id);
            } else {
                $result = $investTimeModel->addInvestTime($data);
            }

            if (false === $result) {
                $this->error(lang('操作失败'));
            }
            insert_operation_log('会员等级理财时段:' . ($id > 0 ? '修改' : '添加'), $data);
            $this->success(lang('提交成功'), '', 'close');
        }

        $info = [];
        if ($id > 0) {
            $info = $investTimeModel->getOneInvestTime($id);
        } else {
            $level_id = input('param.level_id/d');
            $info = ['level_id' => $level_id];
        }

        View::assign('info', $info);
        return View::fetch();
    }

    /**
     * 删除理财时段
     */
    public function investTimeDel()
    {
        $id = input('param.id/d');
        $investTimeModel = new LevelInvestTimeModel();
        $result = $investTimeModel->deleteInvestTime($id);

        if ($result !== false) {
            insert_operation_log('会员等级理财时段:删除', ['id' => $id]);
            return json(msg(1, [], lang('删除成功')));
        }
        return json(msg(-1, [], lang('删除失败')));
    }

    public function clearCache($key, $value)
    {
        $redis = Cache::store('redis');
        $redis_key = config("redis_cache_key." . $key) . $value;
        $redis->delete($redis_key);
    }
}
