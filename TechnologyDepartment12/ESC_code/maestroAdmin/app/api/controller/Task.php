<?php

namespace app\api\controller;

use app\api\middleware\checkLogin;
use app\api\transform\LevelTransform;
use app\api\transform\MemberLevelTransform;
use app\api\transform\TaskTransform;
use app\api\transform\VideoTransform;
use app\common\model\ConfigModel;
use app\common\model\HomeUserVirtualModel;
use app\common\model\MoneyLogModel;
use app\common\model\RecommendModel;
use app\common\model\TaskModel;
use app\common\model\TaskSummaryModel;
use app\common\model\UserModel;
use app\common\model\VideoModel;
use app\common\repository\FinanceProductOrderRepository;
use app\common\repository\LevelRepository;
use app\common\repository\MemberLevelRepository;
use app\common\repository\MoneyLogRepository;
use app\common\repository\TaskRepository;
use Carbon\Carbon;
use think\db\exception\DbException;
use think\facade\Cache;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\response\Json;

class Task extends Base
{
    public function video_list(VideoTransform $transform): Json
    {
        $cate = $this->request->param('cate');
        $page = $this->request->param('page', 1);
        $limit = $this->request->param('limit', 10);
        $is_random = $this->request->param('is_random/b', false);
        $where = [];
        if ($cate) {
            $where[] = ['cate', '=', $cate];
        }
        // 获取所有视频的id
        $expire = Carbon::now()->endofDay()->diffInSeconds(Carbon::now());
        $session_id = request()->cookie('PHPSESSID');
//        $video_ids = Cache::remember('video_ids' . $session_id, function () use ($where) {
//            return VideoModel::where($where)->column('id');
//        }, $expire);
//        $video_ids = VideoModel::where($where)->column('id');

        if ($page == 1) {
            $used_ids = [];
            $used_ids_page = [];
        } else {
            $used_ids = Cache::get('used_ids' . $session_id, []);
            $used_ids_page = Cache::get('used_ids_page' . $session_id, [$page => []]);
        }

        // video_ids 和 used_ids 取差集,再随机取limit条数据
//        if (!isset($used_ids_page[$page])) {
//            $diff_ids = array_diff($video_ids, $used_ids);
//            $used_ids_page[$page] = array_values(array_rand($diff_ids, $limit));
//            $used_ids = array_merge($used_ids, $used_ids_page[$page]);
//        }
//        Cache::set('used_ids' . $session_id, $used_ids, $expire);
//        Cache::set('used_ids_page' . $session_id, $used_ids_page, $expire);
//        $videos = VideoModel::where($where)->where('id', 'in', $used_ids_page[$page])->select();
        $offset = ($page - 1) * $limit;

        $query = VideoModel::where($where)->limit($offset, $limit);
        if ($is_random) {
            $query->orderRaw('RAND()');
        }
        $videos = $query->select();
        $data = $transform->transformCollection($videos->toArray());
        return json(msg(0, $data, lang('success')));
    }

    public function video_detail($id, VideoTransform $transform): Json
    {
        $video = VideoModel::find($id);
        if (!$video) {
            return json(msg(-1, [], lang('视频不存在')));
        }
        $data = $transform->transform($video->toArray());
        $data = [
            'user' => [], //$this->userInfo,
            'user_plan' => [],
            'video' => $data
        ];
        return json(msg(0, $data, lang('success')));
    }

    public function recommend_video_list($id, VideoTransform $transform): Json
    {
        // 随机取10条推荐视频
        $videos = VideoModel::where('id', '<>', $id)
            ->orderRaw('RAND()')
            ->limit(10)
            ->select();
        $data = $transform->transformCollection($videos->toArray());
        return json(msg(0, $data, lang('success')));
    }

    /**
     * 任务记录
     * @param TaskRepository $repository
     * @param TaskTransform $transform
     * @return Json
     */
    public function list(TaskRepository $repository, TaskTransform $transform): Json
    {
        $page = $this->request->param('page', 1);
        $limit = $this->request->param('limit', 10);
        $status = $this->request->param('status', 0);
        $user_id = $this->userid;
        $where = [
            ['user_id' , '=',  $user_id],
            ['status' , '=',  TaskModel::STATUS_END],
            ['task_type' ,'in', [TaskModel::TYPE_GOODS, TaskModel::TYPE_PRODUCT, TaskModel::TYPE_VIDEO]]
        ];
        if ($status == 0) {
            $where[] = ['create_time', 'between', [strtotime(date('Y-m-d 00:00:00')), strtotime(date('Y-m-d 23:59:59'))]];
        } else {
            $where[] = ['create_time', '<', strtotime(date('Y-m-d 00:00:00'))];
        }
        $tasks = $repository->with(['level'])
            ->order(['id' => 'desc'])
            ->where($where)
            ->paginate(['page' => $page, 'list_rows' => $limit]);
        $data = $transform->transformPages($tasks->toArray());
        return json(msg(0, $data, lang('success')));
    }

    /**
     * 贵宾首页接口
     * @param  LevelRepository  $level_repository
     * @param  TaskRepository  $task_repository
     * @param  MemberLevelRepository  $member_level_repository
     * @return Json
     */
    public function task_info(LevelRepository $level_repository, TaskRepository $task_repository, MemberLevelRepository $member_level_repository): Json
    {
        $user_id = $this->userid;
        $levels = $level_repository->getLevelList();
        $user_levels = $member_level_repository->getUserLevels($user_id);

        // 今日收益
        $today_income = i18n_currency((new MoneyLogRepository())->getTodayMoney([$user_id], [16]));//  16 广告位收入
        $total_income = i18n_currency((new MoneyLogRepository())->getTotalMoney([$user_id], [16]));//  16 广告位收入
        $user_income = [
            'today_income' => $today_income,
            'total_income' => $total_income,
            'user_money' => i18n_sprintf($this->userInfo['user_money'])
        ];

        $level_list = (new LevelTransform())->transformCollection($levels->toArray());
        foreach ($level_list as $key => $level) {
            if ($this->userInfo['do_task_status'] == 0) {
                $level_list[$key]['limit_unlock_num'] = 0;
            }
        }
        $data = [
            'level_list' => $level_list,
            'task_count' => [
                'task_reset_time' => $task_repository->getTaskResetCountdown($this->userid), //$task_repository->getTaskResetCountdown($this->userid), // 任务重置倒计时
                'today_income' => $today_income, // 今日收益
                'total_task_income' => i18n_currency($task_repository->getTotalIncome($user_id)), // 累计任务收益
            ],
            'user_income' => $user_income,
            'user_level' => (new MemberLevelTransform())->transformCollection($user_levels->toArray()),
        ];
        return json(msg(0, $data, lang('success')));
    }

    /**
     * 直推任务
     * @return Json
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public function rec_reward(): Json
    {
        return json();
        $user_id = $this->userid;
        $user_model = new UserModel();
        $date = date('Y-m-d', time());
        $date_arr = [strtotime($date . '00:00:00'), time()];
        $map = [
            'leader1' => $user_id,
        ];
        //有效充值
        $recharge_num_valid_user = ConfigModel::getConfigValue('recharge_num_valid_user');
        $day_rec = $user_model
            ->hasWhere('financeProductOrder', [
                ['buy_time', 'between', $date_arr],
                ['status', 'in', [FinanceProductOrderRepository::STATUS_PURCHASED, FinanceProductOrderRepository::STATUS_SETTLED]]
            ])
            ->where($map)
            ->where('recharge_money', '>=', $recharge_num_valid_user)
            ->where('user_model.create_time', 'between', $date_arr)
            ->count();
        $firstDayOfWeek = date("Y-m-d", strtotime('Monday this week'));
        $date_arr = [strtotime($firstDayOfWeek . '00:00:00'), time()];
        $week_rec = $user_model
            ->hasWhere('financeProductOrder', [
                ['buy_time', 'between', $date_arr],
                ['status', 'in', [FinanceProductOrderRepository::STATUS_PURCHASED, FinanceProductOrderRepository::STATUS_SETTLED]]
            ])
            ->where($map)
            ->where('recharge_money', '>=', $recharge_num_valid_user)
            ->where('user_model.create_time', 'between', $date_arr)
            ->count();

        $firstDayOfMonth = date("Y-m") . "-01";
        $date_arr = [strtotime($firstDayOfMonth . '00:00:00'), time()];
        $month_rec = $user_model
            ->hasWhere('financeProductOrder', [
                ['buy_time', 'between', $date_arr],
                ['status', 'in', [FinanceProductOrderRepository::STATUS_PURCHASED, FinanceProductOrderRepository::STATUS_SETTLED]]
            ])
            ->where($map)
            ->where('recharge_money', '>=', $recharge_num_valid_user)
            ->where('user_model.create_time', 'between', $date_arr)
            ->count();

        $data = [];
        $user =  UserModel::where('id', $user_id)->field('is_xuni,show_virtual_data')->find();
        if ($user['is_xuni'] == 1 && $user['show_virtual_data'] == 1) {
            $data = HomeUserVirtualModel::getUserVirtual($user_id);
            $rec = $data['rec'];
            if (empty($rec)) {
                $day_rec = 0;
                $week_rec = 0;
                $month_rec = 0;
            } else {
                $day_rec = $rec['now_day_rec'];
                $week_rec = $rec['now_week_rec'];
                $month_rec = $rec['now_month_rec'];
            }
        }

        $recommend_model = new RecommendModel();
        $recommend_info = $recommend_model->where('id', 1)->find();
        $out_data = [];
        if ($recommend_info) {
            $task_model = new TaskSummaryModel();
            // `day_settle` char(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '' COMMENT '当日直推任务日期',
            //  `week_settle` char(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '' COMMENT '当周直推结算日期',
            //  `month_settle` char(8) CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci DEFAULT '' COMMENT '每月直推奖励结算日期'
            $recommend_info = json_decode(json_encode($recommend_info), true);
            $out_data['now_day_rec'] = $day_rec;
            $now_day_ratio = empty($recommend_info['day_rec']) ? 0 : floatval(($day_rec / $recommend_info['day_rec']));
            if ($now_day_ratio > 1) {
                $now_day_ratio = 1;
            }
            $out_data['now_day_ratio'] = sprintf4f($now_day_ratio * 100, 2, true);

            $out_data['now_week_rec'] = $week_rec;
            $now_week_ratio = empty($recommend_info['week_rec']) ? 0 : floatval(($week_rec / $recommend_info['week_rec']));
            if ($now_week_ratio > 1) {
                $now_week_ratio = 1;
            }
            $out_data['now_week_ratio'] = sprintf4f($now_week_ratio * 100, 2, true);

            $out_data['now_month_rec'] = $month_rec;
            $now_month_ratio = empty($recommend_info['month_rec']) ? 0 : floatval(($month_rec / $recommend_info['month_rec']));
            if ($now_month_ratio > 1) {
                $now_month_ratio = 1;
            }
            $out_data['now_month_ratio'] = sprintf4f($now_month_ratio * 100, 2, true);

            $day_status = $task_model->where([
                'user_id' => $user_id,
                'day_settle' => date("Ymd")
            ])->value('day_settle');
            if (empty($day_status)) {
                $day_status = $now_day_ratio == 1 ? 1 : 0;//0未完成 1未领取 2已领取
            } else {
                $day_status = 2;//0未完成 1未领取 2已领取
            }
            $out_data['now_day_status'] = $day_status;

            $week_status = $task_model->where([
                'user_id' => $user_id,
                'week_settle' => date("YW")
            ])->value('week_settle');
            if (empty($week_status)) {
                $week_status = $now_week_ratio == 1 ? 1 : 0;//0未完成 1未领取 2已领取
            } else {
                $week_status = 2;//0未完成 1未领取 2已领取
            }
            $out_data['now_week_status'] = $week_status;

            $month_status = $task_model->where([
                'user_id' => $user_id,
                'month_settle' => date("Ym")
            ])->value('month_settle');
            if (empty($month_status)) {
                $month_status = $now_month_ratio == 1 ? 1 : 0;//0未完成 1未领取 2已领取
            } else {
                $month_status = 2;//0未完成 1未领取 2已领取
            }
            $out_data['now_month_status'] = $month_status;
            if ($user['is_xuni'] == 1 && $user['show_virtual_data'] == 1) {
                $rec = $data['rec'];
                if (!empty($rec)) {
                    $out_data['now_day_status'] = $data['rec']['now_day_status'];
                    $out_data['now_week_status'] = $data['rec']['now_week_status'];
                    $out_data['now_month_status'] = $data['rec']['now_month_status'];
                }
            }
            $out_data['day_rec'] = $recommend_info['day_rec'];
            $out_data['day_reward'] = $recommend_info['day_reward'];
            $out_data['week_rec'] = $recommend_info['week_rec'];
            $out_data['week_reward'] = $recommend_info['week_reward'];
            $out_data['month_rec'] = $recommend_info['month_rec'];
            $out_data['month_reward'] = $recommend_info['month_reward'];
        } else {
            return json(msg(-1, [], ''));
        }
        $out_data['rec_tip'] = lang($recommend_info['rec_tip']);
        $out_data['vip_tip'] = lang($recommend_info['vip_tip']);
        $out_data['prompt_tip'] = lang($recommend_info['prompt_tip']);
        unset($recommend_info['rec_tip']);
        unset($recommend_info['vip_tip']);
        unset($recommend_info['prompt_tip']);
        $vip_list = [];
        foreach ($recommend_info as $key => $value) {
            if (strpos($key, 'vip') === 0) {
                if (!empty($value)) {
                    $vip_list[strtoupper($key)] = explode('_', $value);
                }
            }
        }
        $out_data['vip_list'] = $vip_list;
        return json(msg(0, $out_data, ''));
    }

    /**
     * 领取直推任务奖励
     * @param $type
     * @return Json
     * @throws DataNotFoundException
     * @throws DbException
     * @throws Exception
     * @throws ModelNotFoundException
     */
    public function get_rec_reward($type = 'day'): Json
    {
        return json();
        $user_id = $this->userid;
        $map = [
            'leader1' => $user_id,
        ];
        switch ($type) {
            case 'day':
                $date = date('Y-m-d', time());
                $date_arr = [strtotime($date . '00:00:00'), time()];
                break;
            case 'week':
                $firstDayOfWeek = date("Y-m-d", strtotime('Monday this week'));
                $date_arr = [strtotime($firstDayOfWeek . '00:00:00'), time()];
                break;
            case 'month':
                $firstDayOfMonth = date("Y-m") . "-01";
                $date_arr = [strtotime($firstDayOfMonth . '00:00:00'), time()];
                break;
            default:
                return json(msg(-1, [], lang('参数错误')));
        }
        $user_model = new UserModel();
        //有效充值
        $recharge_num_valid_user = ConfigModel::getConfigValue('recharge_num_valid_user');
        $day_rec = $user_model
            ->hasWhere('financeProductOrder', [
                ['buy_time', 'between', $date_arr],
                ['status', 'in', [FinanceProductOrderRepository::STATUS_PURCHASED, FinanceProductOrderRepository::STATUS_SETTLED]]
            ])
            ->where($map)
            ->where('recharge_money', '>=', $recharge_num_valid_user)
            ->where('user_model.create_time', 'between', $date_arr)
            ->count();
        $recommend_model = new RecommendModel();
        $recommend_info = $recommend_model->where('id', 1)->find();
        if ($recommend_info) {
            if ($recommend_info['status'] != 1) {
                return json(msg(-1, [], lang('活动已结束')));
            }
            $date = date('Y-m-d');
            $money_log = new MoneyLogModel();
            $task_model = new TaskSummaryModel();

            switch ($type) {
                case 'day':
                    $map = [
                        'user_id' => $user_id,
                        'date' => date("Ymd", strtotime($date)),
                    ];
                    if ($recommend_info['day_rec'] > $day_rec) {
                        return json(msg(-1, [], lang('未达到推荐人数')));
                    }
                    $task_summary = $task_model->where($map)->find();
                    if ($task_summary) {
                        $task_summary = json_decode(json_encode($task_summary), true);
                        if ($task_summary['day_settle'] == date("Ymd", strtotime($date))) {
                            return json(msg(-1, [], lang('已领取')));
                        }
                    } else {
                        $in_task = [
                            'user_id' => $user_id,
                            'date' => date("Ymd", strtotime($date)),
                            'create_time' => time(),
                            'update_time' => time(),
                            'delete_time' => time()
                        ];
                        $task_model->insert($in_task);
                    }
                    $map['user_id'] = $user_id;
                    $ret = $task_model->where($map)->update(['day_settle' => date("Ymd", strtotime($date))]);
                    if ($ret) {
                        $title = "当日直推奖励";
                        $remarks = "当日直推获得奖励:{$recommend_info['day_reward']} ";
                        $change = "add";
                        $type = MoneyLogModel::TYPE_USER;
                        $money_log->changeMoney($user_id, $recommend_info['day_reward'], $type, $change, $title, $remarks, 15);
                        return json(msg(1, [], lang('领取成功')));
                    }
                    break;
                case 'week':
                    $map = [
                        'user_id' => $user_id,
                        'week_settle' => date("YW", strtotime($date)),
                    ];
                    if ($recommend_info['week_rec'] > $day_rec) {
                        return json(msg(-1, [], lang('未达到推荐人数')));
                    }
                    $task_summary = $task_model->where($map)->find();
                    if ($task_summary) {
                        $task_summary = json_decode(json_encode($task_summary), true);
                        if ($task_summary['week_settle'] == date("YW", strtotime($date))) {
                            return json(msg(-1, [], lang('已领取')));
                        }
                    } else {
                        $map_temp = [
                            'user_id' => $user_id,
                            'date' => date("Ymd", strtotime($date))
                        ];
                        $task_summary = $task_model->where($map_temp)->find();
                        if (empty($task_summary)) {
                            $in_task = [
                                'user_id' => $user_id,
                                'date' => date("Ymd", strtotime($date)),
                                'create_time' => time(),
                                'update_time' => time(),
                                'delete_time' => time()
                            ];
                            $task_model->insert($in_task);
                        }
                    }
                    $map = [
                        'user_id' => $user_id
                    ];
                    $ret = $task_model->where($map)->update(['week_settle' => date("YW", strtotime($date))]);
                    if ($ret) {
                        $title = "当周直推奖励";
                        $remarks = "当周直推获得奖励:{$recommend_info['week_reward']} ";
                        $change = "add";
                        $type = MoneyLogModel::TYPE_USER;
                        $money_log->changeMoney($user_id, $recommend_info['week_reward'], $type, $change, $title, $remarks, 15);
                        return json(msg(1, [], lang('领取成功')));
                    }
                    break;
                case 'month':
                    $map = [
                        'user_id' => $user_id,
                        'month_settle' => date("Ym", strtotime($date)),
                    ];
                    if ($recommend_info['month_rec'] > $day_rec) {
                        return json(msg(-1, [], lang('未达到推荐人数')));
                    }
                    $task_summary = $task_model->where($map)->find();
                    if ($task_summary) {
                        $task_summary = json_decode(json_encode($task_summary), true);
                        if ($task_summary['month_settle'] == date("Ym", strtotime($date))) {
                            return json(msg(-1, [], lang('已领取')));
                        }
                    } else {
                        $map_temp = [
                            'user_id' => $user_id,
                            'date' => date("Ymd", strtotime($date))
                        ];
                        $task_summary = $task_model->where($map_temp)->find();
                        if (empty($task_summary)) {
                            $in_task = [
                                'user_id' => $user_id,
                                'date' => date("Ymd", strtotime($date)),
                                'create_time' => time(),
                                'update_time' => time(),
                                'delete_time' => time()
                            ];
                            $task_model->insert($in_task);
                        }
                    }
                    $map = [
                        'user_id' => $user_id
                    ];
                    $ret = $task_model->where($map)->update(['month_settle' => date("Ym", strtotime($date))]);
                    if ($ret) {
                        $title = "当月直推奖励";
                        $remarks = "当月直推获得奖励:{$recommend_info['month_reward']} ";
                        $change = "add";
                        $type = MoneyLogModel::TYPE_USER;
                        $money_log->changeMoney($user_id, $recommend_info['month_reward'], $type, $change, $title, $remarks, 15);
                        return json(msg(1, [], lang('领取成功')));
                    }
                    break;
                default:
                    return json(msg(-1, [], lang('领取失败')));
            }
        }
        return json(msg(-1, [], lang('活动已结束')));
    }
}
