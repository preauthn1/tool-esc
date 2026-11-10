<?php

namespace app\api\controller;

use app\api\transform\PddDrawRecordTransform;
use app\api\transform\PddFinanceProductRecordTransform;
use app\api\transform\PddInviteRecordTransform;
use app\api\transform\PddPrizeTransform;
use app\api\transform\PddReceivedRecordTransform;
use app\api\transform\PddRecordTransform;
use app\api\transform\PddTransform;
use app\common\model\MoneyLogModel;
use app\common\model\PddTurntableCountModel;
use app\common\model\PddTurntableModel;
use app\common\model\PddTurntablePrizeConfigModel;
use app\common\model\PddTurntablePrizeModel;
use app\common\model\PddTurntableRecordModel;
use app\common\model\PddUserFakerModel;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\Exception;
use think\exception\DbException;
use think\response\Json;

class Pdd extends Base
{
    /**
     * Get active turntable configuration with prizes
     * @return Json
     */
    public function getTurntable(): Json
    {
        $pddTurntableModel = new PddTurntableModel();
        $config = $pddTurntableModel->with(['prizes'])
            ->where('status', 1)
            ->field('id, title, start_time, end_time, status')
            ->whereTime('start_time', '<', time())
            ->whereTime('end_time', '>', time())
            ->find();
        if (!$config) {
            return json(msg('-1', [], lang('暂无进行中的抽奖活动')));
        }

        $user_id = $this->userid;
        // 获取最新一条的抽奖记录，如果是大奖，并且进度未完成，并且时间未到期，不能再次抽奖
        $lastRecord = (new PddTurntableRecordModel())->where([
            'user_id' => $user_id,
            'turntable_id' => $config['id'],
            'is_big_prize' => 1
        ])->order(['id' => 'DESC'])->find();
        if ($lastRecord && $lastRecord['is_receive'] == 0 && strtotime($lastRecord['create_time']) + $lastRecord['task_period'] * 3600 > time()) {
            // 获取抽奖配置
            $prizeModel = new PddTurntablePrizeModel();
            $prize = $prizeModel->with('config')->where('id', $lastRecord['prize_id'])->find();

            if (!$prize || !$prize['config']) {
                return json(msg('-1', [], lang('奖品配置异常')));
            }
            $config = $prize['config'];
            $stages = [
                [
                    'min_progress' => $config['stage_1_min'],
                    'max_progress' => $config['stage_1_max'],
                    'high_min' => $config['stage_1_high_min'],
                    'high_max' => $config['stage_1_high_max'],
                    'high_prob' => $config['stage_1_high_prob'],
                    'middle_min' => $config['stage_1_middle_min'],
                    'middle_max' => $config['stage_1_middle_max'],
                    'middle_prob' => $config['stage_1_middle_prob'],
                    'low_min' => $config['stage_1_low_min'],
                    'low_max' => $config['stage_1_low_max'],
                    'low_prob' => $config['stage_1_low_prob'],
                ],
                [
                    'min_progress' => $config['stage_2_min'],
                    'max_progress' => $config['stage_2_max'],
                    'high_min' => $config['stage_2_high_min'],
                    'high_max' => $config['stage_2_high_max'],
                    'high_prob' => $config['stage_2_high_prob'],
                    'middle_min' => $config['stage_2_middle_min'],
                    'middle_max' => $config['stage_2_middle_max'],
                    'middle_prob' => $config['stage_2_middle_prob'],
                    'low_min' => $config['stage_2_low_min'],
                    'low_max' => $config['stage_2_low_max'],
                    'low_prob' => $config['stage_2_low_prob'],
                ],
                [
                    'min_progress' => $config['stage_3_min'],
                    'max_progress' => $config['stage_3_max'],
                    'high_min' => $config['stage_3_high_min'],
                    'high_max' => $config['stage_3_high_max'],
                    'high_prob' => $config['stage_3_high_prob'],
                    'middle_min' => $config['stage_3_middle_min'],
                    'middle_max' => $config['stage_3_middle_max'],
                    'middle_prob' => $config['stage_3_middle_prob'],
                    'low_min' => $config['stage_3_low_min'],
                    'low_max' => $config['stage_3_low_max'],
                    'low_prob' => $config['stage_3_low_prob'],
                ],
            ];
            $left_min_times = 0;
            $left_max_times = 0;
            $progress = $lastRecord['progress'];
            foreach ($stages as $stage) {
                $current_session_left = $stage['max_progress'] - $progress;
                if ($current_session_left > 0) {
                    // 获取$stage['low_min'] $stage['middle_min'] $stage['high_min'] 的值，不等于0 ，哪个有取哪个
                    if (!empty($stage['low_min'])) {
                        $left_max_times += ceil($current_session_left / $stage['low_min']);
                    } else {
                        if (!empty($stage['middle_min'])) {
                            $left_max_times += ceil($current_session_left / $stage['middle_min']);
                        } else {
                            if (!empty($stage['high_min'])) {
                                $left_max_times += ceil($current_session_left / $stage['high_min']);
                            }
                        }
                    }
                    if (!empty($stage['high_max'])) {
                        $left_min_times += ceil($current_session_left / $stage['high_max']);
                    } else {
                        if (!empty($stage['middle_max'])) {
                            $left_min_times += ceil($current_session_left / $stage['middle_max']);
                        } else {
                            if (!empty($stage['low_max'])) {
                                $left_min_times += ceil($current_session_left / $stage['low_max']);
                            }
                        }
                    }
                    $progress += $current_session_left;
                }
            }
            $lastRecord['user_id'] = $user_id;
            $lastRecord['min_invite'] = $left_min_times;
            $lastRecord['max_invite'] = $left_max_times;
            $data = (new PddRecordTransform())->transform($lastRecord);
            $data['progress_model'] = 1;
            return json(msg(0, $data, lang('获取成功')));
        }
        $config['user_id'] = $user_id;
        $data = (new PddTransform())->transform($config->toArray());
        $data['progress_model'] = 0;
        return json(msg(0, $data, lang('获取成功')));
    }

    /**
     * @return Json
     * @throws DataNotFoundException
     * @throws DbException
     * @throws Exception
     * @throws ModelNotFoundException
     */
    public function draw(): Json
    {
        // 获取转盘信息
        $turntable = (new PddTurntableModel())->where('status', 1)
            ->whereTime('start_time', '<', time())
            ->whereTime('end_time', '>', time())
            ->find();

        if (!$turntable) {
            return json(msg('-1', [], lang('暂无进行中的抽奖活动')));
        }

        $user_id = $this->userid;

        // 判断是否有抽奖次数
        if (PddTurntableCountModel::getAvailableNum($user_id) <= 0) {
            return json(msg('-1', [], lang('抽奖次数不足')));
        }

        // 获取最新一条的抽奖记录，如果是大奖，并且进度未完成，并且时间未到期，不能再次抽奖
        $lastRecord = (new PddTurntableRecordModel())->where([
            'user_id' => $user_id,
            'turntable_id' => $turntable['id'],
            'is_big_prize' => 1
        ])->order('id DESC')->find();
        if ($lastRecord) {
            if ($lastRecord['is_receive'] == 0 && strtotime($lastRecord['create_time']) + $lastRecord['task_period'] * 3600 > time()) {
                return json(msg('-1', [], lang('请先完成奖励任务，才能重新抽奖')));
            }
        }

        // 获取上一次抽中大奖到现在抽中了几次小奖
        // Get last big prize record and count subsequent normal prizes
        $recordModel = new PddTurntableRecordModel();

        // Find last big prize win
        $lastBigPrize = $recordModel->where([
            'user_id' => $user_id,
            'turntable_id' => $turntable['id'],
            'is_big_prize' => 1
        ])->order('id DESC')->find();

        // Count normal prizes since last big prize
        $normalPrizeCount = 0;
        if ($lastBigPrize) {
            $normalPrizeCount = $recordModel->where([
                'user_id' => $user_id,
                'turntable_id' => $turntable['id'],
                'is_big_prize' => 0,
                ['create_time', '>', strtotime($lastBigPrize['create_time'])]
            ])->count();
        } else {
            $normalPrizeCount = $recordModel->where([
                'user_id' => $user_id,
                'turntable_id' => $turntable['id'],
                'is_big_prize' => 0,
            ])->count();
        }

        // Determine prize pool based on fixed_times
        $prizeModel = new PddTurntablePrizeModel();
        $where = [
            ['turntable_id', '=', $turntable['id']],
            ['status', '=', 1]
        ];

        // If reached fixed_times count, include big prizes
        if ($turntable['fixed_times'] && $normalPrizeCount + 1 >= $turntable['fixed_times']) {
            $where[] = ['is_big_prize', '=', 1];
        } else {
            $where[] = ['is_big_prize', '=', 0];
        }

        $availablePrizes = $prizeModel->with('config')->where($where)->select();

        if ($availablePrizes->isEmpty()) {
            return json(msg('-1', [], lang('暂无可用奖品')));
        }

        // 从 $availablePrizes 中根据概率抽取一个奖品
        $totalProbability = 0;
        $probabilityArray = [];

        // Calculate total probability and build probability array
        foreach ($availablePrizes as $prize) {
            $totalProbability += $prize['probability'];
            $probabilityArray[] = [
                'prize' => $prize,
                'probability_start' => $totalProbability - $prize['probability'],
                'probability_end' => $totalProbability
            ];
        }

        // Generate random number between 0 and total probability
        $random = mt_rand(0, intval($totalProbability * 100)) / 100;
        $selectedPrize = null;

        // Find the prize based on random number
        foreach ($probabilityArray as $item) {
            if ($random > $item['probability_start'] && $random <= $item['probability_end']) {
                $selectedPrize = $item['prize'];
                break;
            }
        }

        // If no prize selected (shouldn't happen), take first prize
        if (!$selectedPrize && !empty($availablePrizes)) {
            $selectedPrize = $availablePrizes[0];
        }

        $is_big_prize = $selectedPrize['is_big_prize'];
        $task_period = 0;
        if ($is_big_prize) {
            $progress = mt_rand($selectedPrize['config']['initial_min'], $selectedPrize['config']['initial_max']);
            $is_receive = 0;
            $config = PddTurntablePrizeConfigModel::where('prize_id', $selectedPrize['id'])->find();
            $task_period = $config['task_period'];
        } else {
            $progress = 100;
            $is_receive = 1;
        }
        // Record the prize result
        $record = $recordModel->create([
            'user_id' => $user_id,
            'turntable_id' => $turntable['id'],
            'prize_id' => $selectedPrize['id'],
            'amount' => $selectedPrize['value'],
            'is_big_prize' => $is_big_prize,
            'progress' => $progress,
            'is_receive' => $is_receive,
            'task_period' => $task_period,
        ]);
        // 抽奖成功, 扣除一次抽奖次数
        $type = 1;
        if ($is_big_prize) {
            $type = 3; // 大奖type = 3
        }
        PddTurntableCountModel::useCount($user_id, $turntable['id'], $selectedPrize['id'], $record->id, $selectedPrize['value'],
            $type);
        // 如果是小奖，直接发放
        if (floatval($selectedPrize['value']) > 0 && $selectedPrize['is_big_prize'] == 0) {
            $money_log_model = new MoneyLogModel();
            $title = '转盘抽奖';
            $remarks = '转盘抽奖 ' . $selectedPrize['value'];
            $money_log_model->changeMoney(
                $user_id,
                floatval($selectedPrize['value']),
                MoneyLogModel::TYPE_BROKERAGE_POOL,
                'add',
                $title,
                $remarks,
                5
            );
        }
        $data = (new PddPrizeTransform())->transform($selectedPrize);
        return json(msg(0, $data, lang('抽奖成功')));
    }

    /**
     * Update big prize progress
     * @return Json
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function updateBigPrizeProgress(): Json
    {
        $user_id = $this->userid;
        $record_id = input('record_id/d', 0);

        // 判断是否有抽奖次数
        if (PddTurntableCountModel::getAvailableNum($user_id) <= 0) {
            return json(msg('-1', [], lang('抽奖次数不足')));
        }

        $recordModel = new PddTurntableRecordModel();
        $record = $recordModel->where([
            'id' => $record_id,
            'user_id' => $user_id,
            'is_big_prize' => 1,
            'is_receive' => 0,
        ])->find();

        if (!$record) {
            return json(msg('-1', [], lang('未找到有效的任务')));
        }

        if (strtotime($record['create_time']) < time() - $record['task_period'] * 3600) {
            return json(msg('-1', [], lang('任务时间已过期')));
        }

        if ($record['progress'] >= 100) {
            return json(msg('-1', [], lang('进度已完成')));
        }


        // Get prize configuration
        $prizeModel = new PddTurntablePrizeModel();
        $prize = $prizeModel->with('config')->where('id', $record['prize_id'])->find();

        if (!$prize || !$prize['config']) {
            return json(msg('-1', [], lang('奖品配置异常')));
        }

        $config = $prize['config'];
        // Determine the appropriate stage configuration based on current progress
        $progress_increment = 0;
        $stages = [
            [
                'min_progress' => $config['stage_1_min'],
                'max_progress' => $config['stage_1_max'],
                'high_min' => $config['stage_1_high_min'],
                'high_max' => $config['stage_1_high_max'],
                'high_prob' => $config['stage_1_high_prob'],
                'middle_min' => $config['stage_1_middle_min'],
                'middle_max' => $config['stage_1_middle_max'],
                'middle_prob' => $config['stage_1_middle_prob'],
                'low_min' => $config['stage_1_low_min'],
                'low_max' => $config['stage_1_low_max'],
                'low_prob' => $config['stage_1_low_prob'],
            ],
            [
                'min_progress' => $config['stage_2_min'],
                'max_progress' => $config['stage_2_max'],
                'high_min' => $config['stage_2_high_min'],
                'high_max' => $config['stage_2_high_max'],
                'high_prob' => $config['stage_2_high_prob'],
                'middle_min' => $config['stage_2_middle_min'],
                'middle_max' => $config['stage_2_middle_max'],
                'middle_prob' => $config['stage_2_middle_prob'],
                'low_min' => $config['stage_2_low_min'],
                'low_max' => $config['stage_2_low_max'],
                'low_prob' => $config['stage_2_low_prob'],
            ],
            [
                'min_progress' => $config['stage_3_min'],
                'max_progress' => $config['stage_3_max'],
                'high_min' => $config['stage_3_high_min'],
                'high_max' => $config['stage_3_high_max'],
                'high_prob' => $config['stage_3_high_prob'],
                'middle_min' => $config['stage_3_middle_min'],
                'middle_max' => $config['stage_3_middle_max'],
                'middle_prob' => $config['stage_3_middle_prob'],
                'low_min' => $config['stage_3_low_min'],
                'low_max' => $config['stage_3_low_max'],
                'low_prob' => $config['stage_3_low_prob'],
            ],
        ];

        $type = '';
        foreach ($stages as $stage) {
            if ($record['progress'] >= $stage['min_progress'] && $record['progress'] < $stage['max_progress']) {
                $rand = mt_rand(1, 100);
                if ($rand <= $stage['high_prob']) {
                    $type = 'high';
                    $progress_increment = round(
                        $stage['high_min'] + lcg_value() * ($stage['high_max'] - $stage['high_min']),
                        2
                    );
                } elseif ($rand <= $stage['high_prob'] + $stage['middle_prob']) {
                    $type = 'middle';
                    $progress_increment = round(
                        $stage['middle_min'] + lcg_value() * ($stage['middle_max'] - $stage['middle_min']),
                        2
                    );
                } else {
                    $type = 'low';
                    $progress_increment = round(
                        $stage['low_min'] + lcg_value() * ($stage['low_max'] - $stage['low_min']),
                        2
                    );
                }
                break;
            }
        }

        // Update progress
        $new_progress = min(100, round($record['progress'] + $progress_increment, 2));
        $record->progress = $new_progress;
        $record->save();

        // 抽奖成功, 扣除一次抽奖次数
        PddTurntableCountModel::useCount($user_id, $record['turntable_id'], $record['prize_id'], $record->id, $progress_increment,
            2);

        return json(msg(0, [
            'progress' => $new_progress,
            'increment' => $progress_increment,
            'is_complete' => $new_progress >= 100,
            'type' => $type
        ], lang('进度更新成功')));
    }

    /**
     * 领取大奖
     * @return Json
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     */
    public function receiveBigPrize(): Json
    {
        $user_id = $this->userid;
        $record_id = input('record_id', 0, 'intval');

        $recordModel = new PddTurntableRecordModel();
        $record = $recordModel->where([
            'id' => $record_id,
            'user_id' => $user_id,
            'is_big_prize' => 1,
            'is_receive' => 0
        ])->find();

        if (!$record) {
            return json(msg('-1', [], lang('未找到有效的任务记录')));
        }

        if ($record['progress'] < 100) {
            return json(msg('-1', [], lang('进度未达到100%，不能领取')));
        }

        // 获取奖品信息
        $prizeModel = new PddTurntablePrizeModel();
        $prize = $prizeModel->where('id', $record['prize_id'])->find();

        if (!$prize) {
            return json(msg('-1', [], lang('奖品信息不存在')));
        }

        // 发放奖励
        if (floatval($prize['value']) > 0) {
            $money_log_model = new MoneyLogModel();
            $money_log_model->changeMoney(
                $user_id,
                floatval($prize['value']),
                MoneyLogModel::TYPE_BROKERAGE_POOL,
                'add',
                '转盘抽奖',
                "转盘抽奖 {$prize['value']}",
                5
            );
        }

        // 更新记录状态
        $record->is_receive = 1;
        $record->receive_time = time();
        $record->save();

        return json(msg(0, [
            'value' => $prize['value']
        ], lang('领取成功')));
    }

    public function receiveRecord(): Json
    {
        $page = $this->request->param('page/d', 1);
        $limit = $this->request->param('limit/d', 10);
        $where = [];
        $selectResult = PddUserFakerModel::where($where)
            ->order('sort ASC')
            ->paginate(['page' => $page, 'list_rows' => $limit]);
        $data = (new PddReceivedRecordTransform())->transformPages($selectResult->toArray());
        return json(msg(0, $data, ''));
    }

    // 邀请记录
    public function invitedRecord(): Json
    {
        $page = $this->request->param('page/d', 1);
        $limit = $this->request->param('limit/d', 10);
        $selectResult = PddTurntableCountModel::with('from_user')
            ->where('user_id', $this->userid)
            ->where('from_user_id', '<>', $this->userid)
            ->paginate(['page' => $page, 'list_rows' => $limit]);
        $data = (new PddInviteRecordTransform())->transformPages($selectResult->toArray());
        return json(msg(0, $data, ''));
    }

    // 购买广告位获取抽奖次数记录
    public function financeProductRecord(): Json
    {
        $page = $this->request->param('page/d', 1);
        $limit = $this->request->param('limit/d', 10);
        $selectResult = PddTurntableCountModel::where('user_id', $this->userid)
            ->where('from_user_id', $this->userid)
            ->where('session_no', '<>', '')
            ->paginate(['page' => $page, 'list_rows' => $limit]);
        $data = (new PddFinanceProductRecordTransform())->transformPages($selectResult->toArray());
        return json(msg(0, $data, ''));
    }

    // 抽奖记录
    public function drawRecord(): Json
    {
        $page = $this->request->param('page/d', 1);
        $limit = $this->request->param('limit/d', 10);
        $selectResult = PddTurntableCountModel::with('record')
            ->where('user_id', $this->userid)
            ->where('record_id', '>', 0)
            ->order('update_time desc')
            ->paginate(['page' => $page, 'list_rows' => $limit]);;
        $data = (new PddDrawRecordTransform())->transformPages($selectResult->toArray());
        return json(msg(0, $data, ''));
    }
}
