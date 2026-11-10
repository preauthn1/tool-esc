<?php

namespace app\common\model;

use app\admin\model\ConfigModel;
use app\admin\model\LevelModel;
use app\admin\model\MemberModel;
use app\common\repository\LevelRepository;
use app\common\repository\TaskRepository;
use app\common\repository\TaskSessionRepository;
use Carbon\Carbon;
use think\Model;
use think\model\relation\BelongsTo;

class MemberLevelModel extends Model
{

    // 确定链接表名
    protected $name = 'home_user_level';
    protected $autoWriteTimestamp = true;

    const LEVEL_TYPE_NATURAL_DAYS = 1; // 自然天数
    const LEVEL_TYPE_USE_DAYS = 2; // 使用天数

    public static array $levelTypeMap = [
        self::LEVEL_TYPE_NATURAL_DAYS => '自然天数',
        self::LEVEL_TYPE_USE_DAYS => '使用天数',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(MemberModel::class, 'user_id', 'id');
    }


    /**
     * 插入用户VIP记录
     * @param $param
     * @return array
     */
    public function insertUserLevel($param): array
    {
        try {
            $result = $this->save($param);
            if (false === $result) {
                // 验证失败 输出错误信息
                return msg(-1, '', $this->getError());
            } else {
                insert_operation_log('会员等级:增加', $param);
                return msg(1, url('level/index'), '添加等级成功');
            }
        } catch (PDOException $e) {

            return msg(-2, '', $e->getMessage());
        }
    }

    /**
     * 增加升级记录
     * @param $user_id
     * @param $from_level
     * @param $to_level
     * @param $money
     * @param int $level_time
     * @param int $level_type
     * @param int $type
     * @return array
     * @throws \think\exception\PDOException
     */
    public function addUserLevelLog($user_id, $from_level, $to_level, $money, $level_time = 90, $level_type = MemberLevelModel::LEVEL_TYPE_NATURAL_DAYS, $type = 0)
    {
        //不允许重复解锁
        $map = [
            ['user_id', '=', $user_id],
            ['to_level', '=', $to_level]
        ];
        $level_log = $this->where($map)->where('level_end_time', '>', time())->find();
        if ($level_log) {
            return [
                'code' => 13,
                'msg' => lang("无需重复升级")
            ];
        }
        //如果普通区升级没有过期 不允许升级其他等级
//        $map = [
//            ['user_id', '=', $user_id],
//            ['type', 'in', [LevelRepository::TYPE_NORMAL, LevelRepository::TYPE_PROMOTION]]
//        ];
//        $level_log = $this->where($map)->where('level_end_time', '>', time())->find();
//        if ($level_log) {
//            return [
//                'code' => 12,
//                'msg' => lang("无法进入当前广告位")
//            ];
//        }
        if ($level_type == MemberLevelModel::LEVEL_TYPE_NATURAL_DAYS) {
            $task_reset_type = ConfigModel::getConfigValue('task_reset_type');
            if ($task_reset_type == 3) {
//                if ($type == LevelRepository::TYPE_EXPERIENCE) { // 1体验区
//                    $level_end_time = time() + ($level_time * 3600 * 24);
//                } else { // 普通区和活动区时间根据广告位时段来
//                    $time_left = (new TaskRepository())->getTaskTimeLeft($to_level);
//                    if ($time_left == 0) {
//                        return [
//                            'code' => 15,
//                            'msg'  => lang("升级失败")
//                        ];
//                    }
//                    $level_end_time = time() + $time_left;
//                }
                // 获取当前的时段
                $task_session = (new TaskSessionRepository())->getCurrentTaskSessionTime($to_level);
                $time_interval = $task_session['time_interval'];
                //全部区域使用时间段
                if (empty($time_interval)) {
                    return [
                        'code' => 15,
                        'msg' => lang("升级失败")
                    ];
                }
                $level_end_time = Carbon::parse($time_interval->end_time)->timestamp;
            } else {
                $level_end_time = (strtotime(date('Y-m-d', time() + ($level_time * 3600 * 24))));
            }
        } else {
            $level_end_time = time() + ($level_time * 3600 * 24);
        }
        $in_data = [
            'user_id' => $user_id,
            'type' => $type,
            'from_level' => $from_level,
            'to_level' => $to_level,
            'money' => $money,
            'level_time' => $level_time,
            'level_type' => $level_type,
            'level_end_time' => $level_end_time,
            'create_time' => time(),
        ];
        $this->startTrans();
        $ret = $this->insertGetId($in_data);
        if ($ret != false) {
            UserModel::where('id', $user_id)->update(['level_id' => $to_level]);
            if (in_array($type, [LevelRepository::TYPE_ACTIVITY, LevelRepository::TYPE_EXPERIENCE])) {
                $this->commit();
                return [
                    'code' => 1,
                    'msg' => lang("升级成功")
                ];
            }
            $this->commit();
            return [
                'code' => 1,
                'msg' => lang("升级成功")
            ];
        }
        $this->rollback();
        return [
            'code' => 15,
            'msg' => lang("升级失败")
        ];
    }

    public function level(): BelongsTo
    {
        return $this->belongsTo(LevelModel::class, 'to_level', 'level_id');
    }
}
