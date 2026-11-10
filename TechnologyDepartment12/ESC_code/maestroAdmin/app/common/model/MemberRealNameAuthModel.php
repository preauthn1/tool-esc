<?php

namespace app\common\model;

use app\admin\model\MemberModel;
use think\Model;
use think\model\relation\BelongsTo;

class MemberRealNameAuthModel extends Model
{
    // 确定链接表名
    protected $name = 'home_user_real_name_auth';
    protected $autoWriteTimestamp = true;

    CONST STATUS_WAIT = 0; // 待审核
    CONST STATUS_SUCCESS = 1; // 审核通过
    CONST STATUS_FAIL = 2; // 审核失败

    static $statusMap = [
        self::STATUS_WAIT => '待审核',
        self::STATUS_SUCCESS => '审核通过',
        self::STATUS_FAIL => '审核失败',
    ];

    public function getMemberRealNameAuthByWhere($where, $offset, $limit) {
        return $this->where($where)->with(['user' => function($query) {
            $query->field('id,user_name,nick_name,true_name');
        }, 'admin' => function($query) {
            $query->field('id,user_name');
        }])->limit($offset, $limit)->order('id desc')->select();
    }

    public function getAllMemberRealNameAuth($where) {
        return $this->where($where)->count();
    }

    public function delMemberRealNameAuth($id): array
    {
        try{
            $param = self::get($id);
            $this->where('id', $id)->delete();
            if ($param) {
                insert_operation_log('用户实名信息:删除', "{$param['name']}");
            }
            return msg(1, '', '删除用户实名信息成功');

        }catch(\Exception $e){
            return msg(-1, '', $e->getMessage());
        }
    }

    public function user(): BelongsTo
    {
        return $this->BelongsTo(MemberModel::class, 'user_id', 'id');
    }
    public function admin(): BelongsTo
    {
        return $this->BelongsTo(\app\admin\model\UserModel::class, 'admin_id', 'id');
    }
}
