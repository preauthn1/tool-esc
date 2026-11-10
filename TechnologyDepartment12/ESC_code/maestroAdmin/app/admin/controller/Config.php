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

use app\admin\model\ConfigModel;
use app\common\model\PaymentPlatformModel;
use think\facade\View;

class Config extends Base
{
    public function index()
    {
        $config_model = new ConfigModel();
        if (request()->isPost()) {
            $act = $this->request->param('act/s');
            if ($act == 'query_ip') {
                $ip = $this->request->param('ip/s');
                $this->success('ok', '', ip2location_arr($ip, [1, 2, 3, 4], 'en'));
            }
            $param = input('post.');
            // trc20 地址校验
            if (array_key_exists('collection_wallet', $param) && $param['collection_wallet'] != '') {
                if (!isValidTRCAddress($param['collection_wallet'])) {
                    return json(msgError('-1', lang('归集账户地址校验失败')));
                }
            }
            if (array_key_exists('payment_wallet', $param) && $param['payment_wallet'] != '') {
                if (!isValidTRCAddress($param['payment_wallet'])) {
                    return json(msgError('-1', lang('出款账户地址校验失败')));
                }
            }
            if (array_key_exists('trx_payment_wallet', $param) && $param['trx_payment_wallet'] != '') {
                if (!isValidTRCAddress($param['trx_payment_wallet'])) {
                    return json(msgError('-1', lang('出款账户地址校验失败')));
                }

            }
            if (array_key_exists('bep20_collection_wallet', $param) && $param['bep20_collection_wallet'] != '') {
                if (!isValidBEPAddress($param['bep20_collection_wallet'])) {
                    return json(msgError('-1', lang('bep20归集账户地址校验失败')));
                }
            }
            if (array_key_exists('bsc_payment_wallet', $param) && $param['bsc_payment_wallet'] != '') {
                if (!isValidBEPAddress($param['bsc_payment_wallet'])) {
                    return json(msgError('-1', lang('bsc出款账户地址校验失败')));
                }
            }
            if (array_key_exists('entry_domain', $param) && $param['entry_domain'] != '') {
                if (array_key_exists('app_entry_domain', $param) && $param['app_entry_domain'] != '') {
                    $param['entry_domain'] = $param['app_entry_domain'] . "\n" . $param['entry_domain'];
                    unset($param['app_entry_domain']);
                }
                // 按回车换行分割
                $entry_domains = explode("\n", $param['entry_domain']);
                $new_entry_domain = '';
                $put_domain = [];
                foreach ($entry_domains as $domain) {
                    $domain = trim($domain);
                    $explode_domain = explode("|", $domain);
                    if (count($explode_domain) != 2) {
                        continue;
                    }
                    // 检查域名格式 字母.字母
                    $pattern = '/^(?:[a-z0-9]+(?:-[a-z0-9]+)*\.)+[a-z]{2,}$/i';
                    if (!preg_match($pattern, $explode_domain[0]) && !preg_match($pattern, $explode_domain[0])) {
                        continue;
                    }
                    $put_domain[$explode_domain[0]] = $explode_domain[1];
                    $new_entry_domain .= $domain . "\n";
                }

                $save_path = '/www/wwwroot/omg/in_www/';
                if (!is_dir($save_path)) {
                    mkdir($save_path, 755, true);
                }
                // 写入文件
                file_put_contents($save_path . 'domain.php', '<?php $host_arr =' . var_export($put_domain, true) . ';');
                $param['entry_domain'] = $new_entry_domain;
            }
            $param['update_time'] = time();
            $flag = $config_model->editConfig($param);
            insert_operation_log('修改配置文件', $param);
            return json(msg($flag['code'], $flag['data'], lang($flag['msg'])));
        }
        $status = config('app.status');

        $config = $config_model->getConfigInfo();
        $finance_product_data = $config['finance_product_data'] ? json_decode($config['finance_product_data'], true) : [];
        if (isset($config['entry_domain'])) {
            $entry_domains = explode("\n", $config['entry_domain']);
            $config['app_entry_domain'] = isset($entry_domains[0]) ? $entry_domains[0] : '';
            // 删除第一个
            unset($entry_domains[0]);
            $config['entry_domain'] = implode("\n", $entry_domains);
        }
        View::assign([
            'config' => $config,
            'finance_product_data' => $finance_product_data,
            'status' => $status,
            'ip' => $this->request->ip(),
            'ip_local' => ip2location_arr($this->request->ip(), [1, 2, 3, 4], 'en'),
        ]);
        $langs = getLangs(1);
        View::assign("langs", $langs);
        View::assign("phone_codes", ConfigModel::$phone_codes);
        View::assign('base_url', config('app.aws_s3.url'));
        $date_time = ConfigModel::$SYS_DATA_TIME;
        View::assign("date_time", $date_time);
        return View::fetch();
    }

    // 获取出款地址
    public function getPayment_walletAddress()
    {
        $res = PaymentPlatformModel::withdrawWallet('TRC20');
        $data = [
            'payment_wallet' => '',
            'bsc_payment_wallet' => '',
            'trx_payment_wallet' => '',
        ];

        foreach ($res['data'] as $item) {
            if ($item['chain'] == 'TRC20' && $item['coinName'] == 'USDT') {
                $data['payment_wallet'] = $item['address'];
            }
            if ($item['chain'] == 'TRC20' && $item['coinName'] == 'TRX') {
                $data['trx_payment_wallet'] = $item['address'];
            }
            if ($item['chain'] == 'BEP20' && $item['coinName'] == 'USDT') {
                $data['bsc_payment_wallet'] = $item['address'];
            }
        }

        $this->success('', '', $data);
    }
}
