<?php

namespace app\common\model;

use app\admin\transform\AdPlanUserTransform;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\DbException;
use think\Model;
use think\model\relation\BelongsTo;

class AdPlanUserModel extends Model
{
    protected $table = 'qd_ad_plan_user';

    // Automatically add created_at and updated_at fields
    protected $autoWriteTimestamp = true;

    // Define relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(UserModel::class, 'user_id');
    }

    public function adPlan(): BelongsTo
    {
        return $this->belongsTo(AdPlanModel::class, 'ad_plan_id');
    }

    public function previousPlan(): BelongsTo
    {
        return $this->belongsTo(AdPlanModel::class, 'previous_plan_id');
    }

    /**
     * Records a user joining an ad plan, but only if no record exists
     * @param  int  $userId  The user ID
     * @return boolean
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public static function recordUserAdPlan($userId)
    {
        // Get the appropriate ad plan for the user
        $adPlan = model('AdPlanModel')
            ->order('sort')
            ->where('status', 1)
            ->find();
        if (!$adPlan) {
            return false; // No ad plan available
        }
        $adPlanId = $adPlan->id;

        // Check if a record already exists
        $existingRecord = self::where('user_id', $userId)
            ->where('ad_plan_id', $adPlanId)
            ->find();

        // If exists, don't create a new one
        if ($existingRecord) {
            return false;
        }

        // Create a new record
        $saveResult = self::create([
            'user_id'    => $userId,
            'ad_plan_id' => $adPlanId,
        ]);
        // Update user's ad_plan_id if record was saved successfully
        if ($saveResult) {
            $user = UserModel::find($userId);
            if ($user) {
                $user->ad_plan_id = $adPlanId;
                $user->save();
            }
        }

        return true;
    }

    /**
     * Get users with active ad plans
     * @param  array  $where  Additional filter conditions
     * @param  int  $page  Page number
     * @param  int  $limit  Items per page
     * @param  int|null  $day  Filter by day if provided
     * @return array Users with their ad plan information
     * @throws DataNotFoundException
     * @throws ModelNotFoundException
     * @throws DbException
     */
    public static function getUsersWithAdPlans($where = [], $page = 1, $limit = 10, $day = null): array
    {
        $users = UserModel::alias('u')
            ->field('u.id, u.user_name, u.ad_plan_id,apu.create_time')
            ->join('qd_ad_plan ap', 'u.ad_plan_id = ap.id', 'LEFT')
            ->join('qd_ad_plan_user apu', 'u.id = apu.user_id and ap.id = apu.ad_plan_id', 'LEFT')
            ->with('ad_plan.next_plan')
            ->where($where)
            ->paginate(['page' => $page, 'list_rows' => $limit]);
        return (new AdPlanUserTransform())->transformPages($users->toArray());
    }
}
