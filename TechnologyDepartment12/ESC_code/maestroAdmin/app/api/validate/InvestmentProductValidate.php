<?php

namespace app\api\validate;

use app\admin\model\ConfigModel;
use app\common\model\InvestmentProductModel;
use app\common\model\InvestmentProductSessionModel;
use app\common\model\MoneyLogModel;
use app\common\repository\FinanceProductOrderRepository;

class InvestmentProductValidate extends BaseValidate
{
    protected $rule = [
        'amount' => 'require|gt:0|currency|checkSession|checkUserMoney|checkWaitBuyFinanceProductOrderCount',
        'id'     => 'checkProduct'
    ];

    protected $message = [
        'amount.require'  => '请输入金额',
        'amount.currency' => '金额格式错误',
        'amount.gt'       => '请输入正确的金额',
    ];


    protected $scene = [

    ];

    public function checkSession($value, $rule, $data = []): bool|string
    {
        $session = (new InvestmentProductSessionModel())->getCurrentTimeConfig();
        if (!$session) {
            return '目前无法购买';
        }
        return true;
    }

    public function checkProduct($id, $rule, $data = []): bool|string
    {
        $product = $data['product'];
        // 判断产品是否可购买
        if (!$product) {
            return 'AI智能托管不存在';
        }
        if ($product->sales_status != 1) {
            return 'AI结算系统维护中';
        }
        return true;
    }

    public function checkUserMoney($value, $rule, $data = []): bool|string
    {
        if ($data[MoneyLogModel::$TYPE_FIELDS[MoneyLogModel::TYPE_USER]] < $data['amount']) {
            return '余额不足';
        }
        return true;
    }

    public function checkWaitBuyFinanceProductOrderCount($value, $rule, $data = []): bool|string
    {
        // 判断是否有未匹配的流量包
        $wait_buy_finance_product_order_count = FinanceProductOrderRepository::getWaitBuyFinanceProductOrderCount($data['user_id']);
        if ($wait_buy_finance_product_order_count) {
           return '请先完成剩余的库存订单，再使用AI';
        }
        return true;
    }

}
