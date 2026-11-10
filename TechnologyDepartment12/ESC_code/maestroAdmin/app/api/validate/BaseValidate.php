<?php

namespace app\api\validate;

use app\common\model\UserModel;
use think\Validate;

class BaseValidate extends Validate
{

    // 判断金额格式（数字，可以是正数和负数，并且最多两位小数）
    public function currency($value, $rule, $data): bool
    {
        if (is_numeric($value) && preg_match('/^-?\d+(\.\d{1,2})?$/', $value)) {
            return true;
        }
        return false;
    }

    // 校验用户id是否正确
    public function checkUserById($user_id): bool
    {
        $user_info = UserModel::getOneUser($user_id, 'id', 'id');
        if ($user_info) {
            return true;
        }
        return false;
    }
}
