<?php

namespace app\common\model;

use think\Model;

class MemberCreditScoreModel extends Model
{
    protected $autoWriteTimestamp = false;
    protected $name = 'home_user_credit_score';

    protected $field = [
        'id', // 主键ID
        'user_id', // 用户ID
        'credit_score', // 信用评分
        'trade', // 交易量信用分
        'active', // 活跃用户信用分
        'new', // 新用户信用分
        'month', // 信用分月份
    ];

    public static array $fieldMap = [
        CreditScoreLogModel::TYPE_TRADE     => 'trade',
        CreditScoreLogModel::TYPE_ACTIVE    => 'active',
        CreditScoreLogModel::TYPE_NEW       => 'new',
    ];

    public static function getUserCreditScore($user_id)
    {
        // 先查询当月有没有生成记录
        $user_credit_score = MemberCreditScoreModel::where('user_id', $user_id)
            ->where('month', date('Ym'))
            ->find();
        if ($user_credit_score) {
            return $user_credit_score;
        }
        // 如果没有生成记录，创建一个新的记录
        $new_user_credit_score = new MemberCreditScoreModel();
        $new_user_credit_score->user_id = $user_id;
        $new_user_credit_score->credit_score = 0; // 初始信用分为0
        $new_user_credit_score->trade = 0; // 初始交易量信用分为0
        $new_user_credit_score->active = 0; // 初始活跃用户信用分为0
        $new_user_credit_score->new = 0; // 初始新用户信用分为0
        $new_user_credit_score->month = date('Ym'); // 当前月份
        $new_user_credit_score->save();
        return $new_user_credit_score;
    }
}
