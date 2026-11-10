<?php

namespace app\api\controller;

use app\api\transform\CustomerServiceTransform;
use app\api\transform\UserTransform;
use app\BaseController;
use app\common\model\ConfigModel;
use app\common\model\CustomerServiceModel;
use app\common\model\PaymentPlatformModel;
use app\common\model\UserFakeModel;
use app\common\model\UserMessageModel;
use app\common\repository\CustomerServiceRepository;
use app\common\repository\NoticeRepository;
use app\common\repository\VideoRepository;
use app\lib\exception\TokenException;
use GuzzleHttp\Client;
use http\Client\Request;
use pay\NOWPayments;
use pay\Vipdcpay;
use Symfony\Component\HttpClient\HttpClient;
use think\facade\Cache;
use think\response\Json;
use util\NowPaymentsAPI;
use NowPaymentsIO\NowPaymentsAPI as thisPayments;

class Index extends BaseController
{
    public function index(\think\App $app): Json
    {
        // 公告
        $notices = (new NoticeRepository())->i18n_notices()['data'];
        $banner = i18n_home_banner('index');

        $setting = (new ConfigModel)->getConfig();

        $videos = (new VideoRepository())->getIndexVideoList();
        $cate_videos = (new VideoRepository())->getIndexCateVideo();
        //充值客服
        $data = [
            'index' => [],
            'notice' => $notices,
            'slide' => $banner,
            'system' => [
                'index_video_is_show' => $setting['index_video_is_show'],
                'index_video_url' => toMedia($setting['index_video_url'], 1),
                'is_show_cate' => $setting['is_show_cate'],
                'is_show_fake_data' => $setting['is_show_fake_data'],
            ],
            'task_reset_time' => '12:00',
            'video' => $videos,
            'cate_videos' => $cate_videos
        ];

        try {
            $base = new Base($app);
            if ($base->isLogin()) {
                $data['index'] = [
                    'new_message_count' => UserMessageModel::getUnreadCount($base->userid),
                    'new_pop_message_count' => UserMessageModel::getPopUnreadCount($base->userid),
                    'user' => (new UserTransform())->transform($base->userInfo),
                ];
            }
        } catch (TokenException $e) {
            // 不处理异常
        }


        return json(msg(0, $data, lang('success')));
    }

    /**
     * 轮播图
     * @param string $region
     * @return Json
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     */
    public function banner($region = 'introduce'): Json
    {
        $banner = i18n_home_banner($region);
        return json(msg(0, $banner, ''));
    }

    public function init(): Json
    {
        $customer_services = Cache::store('redis')->remember(config("redis_cache_key.customer_service"), function () {
            return (new CustomerServiceRepository())
                ->order(['sort' => 'asc'])
                ->selectWhere([['status', '=', 1]], ['image', 'link', 'name']);
        });
        $config = (new ConfigModel)->getConfig();
        $data = [
            'customer' => (new CustomerServiceTransform())->transformCollection($customer_services->toArray()),
            'register_info' => [
                'invitation_code_require' => $config['invite_code_required'],
                'is_repeat_password' => $config['is_repeat_password'],
                'is_verify_email_code' => $config['is_verify_email_code'],
                'is_verify_phone_code' => $config['is_verify_phone_code'],
                'register_account_status' => $config['account_rule'], // 0:不限制 1:邮箱注册 2:手机号注册
                'verify_code_ttl' => $config['verify_code_ttl'],
            ],
            'setting' => [
                'customer_logo' => $config['customer_logo'],
                'customer_logo_status' => $config['customer_logo_status'],
                'decimal_places' => $config['decimal_places'],
                'google_secret_status' => $config['google_secret_status'],
                'index_force_notice' => $config['index_force_notice'],
                'index_is_login' => $config['index_is_login'],
                'invest_rebate_type' => $config['invest_rebate_type'],
                'invest_status' => $config['invest_status'],
                'is_brokerage_to_basic' => $config['is_brokerage_to_basic'],
                'is_watch_video' => $config['is_watch_video'],
                'level_income_type' => $config['level_income_type'],
                'salesmartly_src' => $config['salesmartly_src'],
                'system_default_lang' => $config['system_default_lang'],
                'system_default_phone_code' => $config['system_default_phone_code'],
                'sys_data_time' => $config['sys_data_time'],
                'finance_product_no_can_click' => $config['finance_product_no_can_click'],
                'finance_product_yes_can_click' => $config['finance_product_yes_can_click'],
                'finance_product_dialog_count_down' => $config['finance_product_dialog_count_down'],
                'finance_product_button_count_down' => $config['finance_product_button_count_down'],
                'repayment_reminder_content' => $config['repayment_reminder_content'] ?? '',
                'repayment_reminder_status' => $config['repayment_reminder_status'] ?? 0,
            ],
            'site_info' => [
//                'app_download_show_switch' => $config['app_download_show_switch'],
                'app_ios_download_url' => $config['ios_url'] ?? '',
                'app_android_download_url' => $config['android_url'] ?? '',
                'app_mt_android_download_url' => $config['mt_android_url'] ?? '',
                'app_mt_ios_download_url' => $config['mt_ios_url'] ?? '',
                'is_show_user_protocol' => $config['is_show_user_protocol'] ?? '',
                'official_website_url' => $config['official_website_url'] ?? '',
                'site_logo' => $config['logo'] ?? '',
                'site_icon' => $config['admin_icon'] ?? '',
                'site_name' => $config['name'] ?? '',
            ],
            'version' => isset($config['app_version']) ? $config['app_version'] : ''
//            'skin_setting'  => [
//                'pattern_no' => '1', // 皮肤样式
//                'skin_no'    => '1', // 皮肤模板
//            ]
        ];
        if ($config['sys_currency_type'] == 1) {
            $data['setting']['fiat_currency_unit'] = 'USDT';
            $data['setting']['fiat_currency_symbol'] = '$';
            $data['setting']['fiat_currency_rate'] = 1;
        } else {
            $data['setting']['fiat_currency_unit'] = $config['fiat_currency_unit'];
            $data['setting']['fiat_currency_symbol'] = $config['fiat_currency_symbol'];
            $data['setting']['fiat_currency_rate'] = $config['fiat_currency_rate'];
        }
        return json(msg(0, $data, lang('success')));
    }

    /**
     * 获取会员列表假数据
     * @return Json
     */
    public function vip_list()
    {
        $vip_list = UserFakeModel::getListAll();
        return json(msg(0, $vip_list, ''));
    }

    public function test()
    {
//        $order = [
//            'order_id'=>"DCPAY14112025082020967",
//            'subject'=>'测试商品',
//            'money'=>1
//        ];
//        $config = [
//            'sandbox_merchant_id' => 'sandbox001',
//            'sandbox_key' => 'w34378puooeh1acalue09shuf8rbgi3p',
//            'merchant_id' => 'M1763138490',
//            'key' => '27cb5246da3e157b6b6e76821a60c300',
//            'coin' => 'VIPDCPAY',
//            'currency_code' => 'BRL',
//            'pay' => '/pay/unifiedOrder',
//            'repay' => '/repay/unifiedOrder',
//            'pay_query' => '/pay/query',
//            'repay_query' => '/repay/query',
//            'balance' => '/query/balance',
//            'domain' => 'https://api.vipdcpay.com',
//            'sandbox_domain' => 'https://sandbox-api.vipdcpay.com',
//            'pay_notify'=>"https://api.huotai10086.com/api.php/notify/VipdcpayRecharge",
//            'repay_notify'=>"https://api.huotai10086.com/api.php/notify/VipdcpayRecharge",
//            'pay_channel_code'=>'brlds',
//            'repay_channel_code'=>'brldf'
//        ];
//        $result = (new \transfer\Vipdcpay($config))->pay($order);

//        $result = new NowPaymentsAPI("WRPYP4C-BAQ44JN-QZX6BT8-YGW8K7B");
//
//        return json(msg(0, $result->getJwtToken(), ''));

        /**
         * 'email' => "zzhy112266@gmail.com",
         * 'password' => 'yy126325'
         */

//        $curl = curl_init();
//
//        curl_setopt_array($curl, array(
//            CURLOPT_URL => 'https://api.nowpayments.io/v1/auth',
//            CURLOPT_RETURNTRANSFER => true,
//            CURLOPT_ENCODING => '',
//            CURLOPT_MAXREDIRS => 10,
//            CURLOPT_TIMEOUT => 0,
//            CURLOPT_FOLLOWLOCATION => true,
//            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
//            CURLOPT_CUSTOMREQUEST => 'POST',
//            CURLOPT_POSTFIELDS => '{
//                "email": "zzhy112266@gmail.com",
//                "password": "yy126325"
//            }',
//
//                CURLOPT_HTTPHEADER => array(
//                    'Content-Type: application/json'
//                ),
//            ));
//
//        $response = curl_exec($curl);
//
//        curl_close($curl);
//        return json(msg(0, $response, ''));

        $httpClient = HttpClient::create();

        $data = [
            'username' => "zzhy112266@gmail.com",
            'password' => "yy126325"
        ];

        $response = $httpClient->request('POST', "https://api.nowpayments.io/v1" . '/auth', [
            'headers' => [
                'Content-Type' => 'application/json',
            ],
            'body' => json_encode($data)
        ]);

        $content = $response->getContent();
        $decode = json_decode($content, true);

        return json(msg(0, $decode, ''));
    }
}
