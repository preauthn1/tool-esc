<?php

namespace app\common\model;

use app\admin\model\CollectionModel;
use app\exception\UpayException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\facade\Env;
use think\Exception;
use think\exception\DbException;
use think\facade\Log;
use think\Model;
use think\model\concern\SoftDelete;

class PaymentPlatformModel extends Model
{

    use SoftDelete;

    public $autoWriteTimestamp = true;
    // 确定链接表名
    protected $name = 'payment_platform';

    protected $json = ['config'];

    CONST CURRENCY_TYPE_USDT = 1;
    CONST CURRENCY_TYPE_BANK = 2;

    // 获取所有充提币种
    public static function getAll($field = '*', $type = '')
    {
        switch ($type) {
            case 'recharge':
                $where_type_field = 'recharge_is_show';
                break;
            case 'withdraw';
                $where_type_field = 'withdraw_is_show';
                break;
            default:
                return [];
        }
        return self::where($where_type_field, 1)
            ->order('sort', 'asc')
            ->field($field)
            ->select();
    }

    // 获取一个充提币种
    public static function getOne($id, $type = '', $field = '*')
    {
        switch ($type) {
            case 'recharge':
                $where_type_field = 'recharge_is_show';
                break;
            case 'withdraw';
                $where_type_field = 'withdraw_is_show';
                break;
            default:
                return [];
        }
        return self::where($where_type_field, 1)->where('id', $id)->field($field)->find();
    }


    /**
     * 获取充值钱包地址
     * @param $user_id
     * @param $platform_id
     * @return array|bool|float|int|mixed|object|\stdClass|void|null
     * @throws DataNotFoundException
     * @throws DbException
     * @throws Exception
     * @throws ModelNotFoundException
     */
    public static function getRechargeAddress($user_id, $platform_id)
    {
        $channel = PaymentPlatformModel::getOne($platform_id, 'recharge');
        if (!$channel) {
            throw new UpayException(lang('参数错误'));
        }
        $config = json_decode($channel->config, true);
        $user = UserModel::where('id', $user_id)->find();

        switch ($config['chain']) {
            case 'TRC20':
                if ($user->recharge_address) {
                    return $user->recharge_address;
                }
                break;
            case 'BEP20':
                if ($user->bsc_address) {
                    return $user->bsc_address;
                }
                break;
        }

        $data = [
            'merchantNo' => $config['merchantNo'],
            'coin'       => $config['coin'],
            'chain'      => $config['chain'],
            'username'   => $user['user_name'],
            'timestamp'  => time(),
        ];

        $key = self::generateSign($data, $config['key']);
        $data['sign'] = $key;

        $url = $config['wallet_url'];
        $res = self::request($data, $url);

        if ($res['code'] == 0) {
            switch ($config['chain']) {
                case 'TRC20':
                    $user->recharge_address = $res['data']['address'];
                    break;
                case 'BEP20':
                    $user->bsc_address = $res['data']['address'];
                    break;
            }

            $user->save();
            return $res['data']['address'];
        }
    }

    /**
     * 提现
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @throws Exception
     */
    public static function withdraw($user_id, $platform_id, $amount, $order_id)
    {
        $channel = PaymentPlatformModel::getOne($platform_id, 'recharge');
        if (!$channel) {
            throw new UpayException(lang('参数错误'));
        }
        $config = json_decode($channel->config, true);
        $user = UserModel::where('id', $user_id)->find();

        if (!$user['withdraw_address']) {
            throw new UpayException(lang('用户未绑定提款地址'));
        }

        $data = [
            'merchantNo' => $config['merchantNo'],
            'coin'       => $config['coin'],
            'chain'      => $config['chain'],
            'amount'     => floatval($amount),
            'orderNo'    => $order_id,
            'address'    => $user['withdraw_address'],
            'timestamp'  => time(),
            'notifyUrl'  => $config['withdraw_notify_url']
        ];

        $key = self::generateSign($data, $config['key']);
        $data['sign'] = $key;

        $url = $config['withdraw_url'];
        $res = self::request($data, $url);
        if ($res['code'] == 0) {
            return $res;
        } else {
            throw new UpayException(lang($res['msg']));
        }
    }

    /**
     * 发起请求
     * @param  array  $data
     * @param  string  $url
     * @param  string  $method
     * @return mixed
     */
    private static function request(array $data, string $url, string $method = 'post')
    {
        $client = new Client();
        $options = [
            'json'    => $data,
            'timeout' => 10,
        ];
        // 记录日志
        self::requestLog($url, $method, $options);
        try {
            $response = $client->request($method, $url, $options);
        } catch (GuzzleException|\Exception $e) {
            $title = lang('请求 upay 节点失败');
            Log::write([
                'title' => $title,
                'msg'   => $e->getMessage()
            ], "upay");
            return ['code' => -1, 'msg' => $e->getMessage()];
        }

        $content = $response->getBody()->getContents();
        return json_decode($content, true);
    }

    /**
     * 生成签名
     * @param $data
     * @param $key
     * @return string
     */
    public static function generateSign($data, $key): string
    {
        ksort($data);
        $temp_str = '';
        foreach ($data as $k => $v) {
            $temp_str .= $k.'='.$v.'&';
        }
        $temp_str .= 'key='.$key;
        return md5($temp_str);
    }

    /**
     * 获取钱包余额
     */
    public static function getWalletBalance($address, $platform_id, $chain)
    {
        $channel = PaymentPlatformModel::where('id', $platform_id)->find();
        if (!$channel) {
            throw new UpayException(lang('参数错误'));
        }
        $config = json_decode($channel->config, true);
        $data = [
            'merchantNo' => $config['merchantNo'],
            'address'    => $address,
            'chain'      => $chain,
            'timestamp'  => time(),
        ];

        $key = self::generateSign($data, $config['key']);
        $data['sign'] = $key;
        $url = $config['balance_url'];
        $res = self::request($data, $url);
        if ($res['code'] == 0) {
            return $res;
        } else {
            throw new UpayException(lang($res['msg']));
        }
    }

    /**
     * 获取钱包余额
     */
    public static function getWalletBalanceData($address, $platform_id, $chain)
    {
        $TRC20_t_balance = 0;
        $TRC20_u_balance = 0;
        $channel = PaymentPlatformModel::where('id', $platform_id)->find();
        if (!$channel) {
            return [
                'TRX'  => $TRC20_t_balance,
                'USDT' => $TRC20_u_balance,
            ];
        }
        $config = json_decode($channel->config, true);
        $data = [
            'merchantNo' => $config['merchantNo'],
            'address'    => $address,
            'chain'      => $chain,
            'timestamp'  => time(),
        ];
        $key = self::generateSign($data, $config['key']);
        $data['sign'] = $key;
        $url = $config['balance_url'];
        $res = self::request($data, $url);

        if ($res['code'] == 0) {
            foreach ($res['data']['assets'] as $item) {
                switch ($item['coinName']) {
                    case 'TRX';
                        $TRC20_t_balance = $item['balance'];
                        break;
                    case 'USDT';
                        $TRC20_u_balance = $item['balance'];
                        break;
                }
            }
            return [
                'TRX'  => $TRC20_t_balance,
                'USDT' => $TRC20_u_balance,
            ];
        } else {
            return [
                'TRX'  => $TRC20_t_balance,
                'USDT' => $TRC20_u_balance,
            ];
        }
    }

    /**
     * 归集
     * @param $address
     * @param $minAmount
     * @param $type
     * @return int[]|mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws Exception
     * @throws ModelNotFoundException
     */
    public static function swapCollect($address, $minAmount, $type)
    {
        $channel = PaymentPlatformModel::where('name', 'like', $type.'%')->find();
        if (!$channel) {
            $channel = PaymentPlatformModel::where('name', 'like', $type.'%')->find();
        }
        if (!$channel) {
            throw new UpayException(lang('参数错误'));
        }
        $config = json_decode($channel->config, true);
        $data = [
            'merchantNo' => $config['merchantNo'],
            'address'    => $address,
            'chain'      => $config['chain'],
            'coin'       => $config['coin'],
            'minAmount'  => $minAmount,
            'timestamp'  => time(),
        ];

        $key = self::generateSign($data, $config['key']);
        $data['sign'] = $key;

        $url = $config['swap_create_url'];
        $res = self::request($data, $url);
        if ($res['code'] == 0) {
            return $res;
        } else {
            throw new UpayException(lang($res['msg']));
        }
    }

    /**
     * 归集结果查询
     * @return int[]|mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws Exception
     * @throws ModelNotFoundException
     */
    public static function swapCheck($batchNo, $collection)
    {
        $channel = PaymentPlatformModel::where('name', 'like', $collection->coin.'%')->find();
        if (!$channel) {
            $channel = PaymentPlatformModel::where('name', 'like', $collection->chain.'%')->find();
        }

        if (!$channel) {
            throw new UpayException(lang('参数错误'));
        }
        $config = json_decode($channel->config, true);
        $data = [
            'merchantNo' => $config['merchantNo'],
            'batchNo'    => $batchNo,
            'timestamp'  => time(),
        ];

        $key = self::generateSign($data, $config['key']);
        $data['sign'] = $key;

        $url = $config['swap_check_url'];
        $res = self::request($data, $url);
        if ($res['code'] == 0) {
            return $res;
        } else {
            throw new UpayException(lang($res['msg']));
        }
    }

    /**
     * 归集记录
     * @return int[]|mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws Exception
     * @throws ModelNotFoundException
     */
    public static function swapRecord(CollectionModel $collection, $pageNo = 1, $pageSize = 10)
    {
        $channel = PaymentPlatformModel::where('name', 'like', $collection->coin.'%')->find();
        if (!$channel) {
            $channel = PaymentPlatformModel::where('name', 'like', $collection->chain.'%')->find();
        }
        if (!$channel) {
            throw new Exception(lang('参数错误'));
        }
        $config = json_decode($channel->config, true);
        $data = [
            'merchantNo' => $config['merchantNo'],
            'batchNo'    => $collection->batch_no,
            'timestamp'  => time(),
        ];

        $key = self::generateSign($data, $config['key']);
        $data['sign'] = $key;
        $data['pageNo'] = $pageNo;
        $data['pageSize'] = $pageSize;

        $url = $config['swap_record_url'];
        $res = self::request($data, $url);
        if ($res['code'] == 0) {
            return $res;
        } else {
            throw new Exception(lang($res['msg']));
        }
    }

    /**
     * 单笔归集
     * @param $address string 归集地址
     * @param $user_address string 用户名
     * @param $amount string 金额
     * @param  string  $coin
     * @param  string  $china
     * @return int[]|mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws Exception
     * @throws ModelNotFoundException
     */
    public static function swapSingle(
        string $address,
        string $user_address,
        string $amount,
        string $coin,
        string $china
    ) {
        $channel = PaymentPlatformModel::where('name', 'like', $coin.'%')->find();
        if (!$channel) {
            $channel = PaymentPlatformModel::where('name', 'like', $china.'%')->find();
        }
        if (!$channel) {
            throw new UpayException(lang('参数错误'));
        }
        $config = json_decode($channel->config, true);
        $data = [
            'merchantNo'  => $config['merchantNo'],
            'address'     => $address,
            'chain'       => $config['chain'],
            'coin'        => $config['coin'],
            'amount'      => floatval($amount),
            'timestamp'   => time(),
            'userAddress' => $user_address,
            'notifyUrl'   => $config['swap_notify_url']
        ];

        $key = self::generateSign($data, $config['key']);
        $data['sign'] = $key;
        $url = $config['swap_single_url'];
        $res = self::request($data, $url);
        if ($res['code'] == 0) {
            return $res;
        } else {
            throw new UpayException(lang($res['msg']));
        }
    }

    /**
     * 获取出款钱包地址
     * @return int[]|mixed
     * @throws DataNotFoundException
     * @throws DbException
     * @throws Exception
     * @throws ModelNotFoundException
     */
    public static function withdrawWallet($type)
    {
        $channel = PaymentPlatformModel::where('name', 'like', $type.'%')->find();
        if (!$channel) {
            $channel = PaymentPlatformModel::where('name', 'like', $type.'%')->find();
        }
        if (!$channel) {
            throw new Exception(lang('参数错误'));
        }
        $config = json_decode($channel->config, true);
        $data = [
            'merchantNo' => $config['merchantNo'],
            'timestamp'  => time(),
        ];

        $key = self::generateSign($data, $config['key']);
        $data['sign'] = $key;
        $url = $config['withdraw_wallet_url'];
        $res = self::request($data, $url);
        if ($res['code'] == 0) {
            return $res;
        } else {
            throw new UpayException(lang($res['msg']));
        }
    }

    /**
     * 获取归集地址
     * @throws DataNotFoundException
     * @throws DbException
     * @throws ModelNotFoundException
     * @throws Exception
     */
    public static function getCollectionAddress($type)
    {
        if (in_array($type, ['TRC20', 'TRX'])) {
            // 获取配置的归集地址
            $address = ConfigModel::getConfigValue('collection_wallet');
        } else {
            if (in_array($type, ['BEP20', 'BNB'])) {
                // 获取配置的归集地址
                $address = ConfigModel::getConfigValue('bep20_collection_wallet');
            } else {
                throw new Exception(lang('获取地址失败'));
            }
        }
        return $address;
    }

    /**
     * 记录请求日志
     * @param  string  $url
     * @param  string  $method
     * @param  array  $options
     * @return void
     */
    public static function requestLog(string $url, string $method, array $options): void
    {
        if (Env::get("APP_DEBUG", false)) {
            Log::write([
                'url'     => $url,
                'method'  => $method,
                'options' => $options
            ], "upay");
        }
    }
}
