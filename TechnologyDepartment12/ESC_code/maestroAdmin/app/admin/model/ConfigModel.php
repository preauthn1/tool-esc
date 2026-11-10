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
namespace app\admin\model;

use think\Model;
use think\facade\Cache;

class ConfigModel extends Model
{
    //时区对应
    public static $SYS_DATA_TIME = [
        'Pacific/Wake' => "GMT-12:00",
        'Pacific/Apia' => "GMT-11:00",
        'Pacific/Honolulu' => "GMT-10:00",
        'America/Anchorage' => "GMT-09:00",
        'America/Los_Angeles' => "GMT-08:00",
        'America/Chihuahua' => "GMT-07:00",
        'America/Managua' => "GMT-06:00",
        'America/Chicago' => "GMT-06:00",
        'America/Mexico_City' => "GMT-06:00",
        'America/Bogota' => "GMT-05:00",
        'America/New_York' => "GMT-05:00",
        'America/Halifax' => "GMT-04:00",
        'America/Caracas' => "GMT-04:00",
        'America/Santiago' => "GMT-04:00",
        'America/St_Johns' => "GMT-03:30",
        'America/Godthab' => "GMT-03:00",
        'America/Sao_Paulo' => "GMT-03:00",
        'America/Noronha' => "GMT-02:00",
        'Atlantic/Azores' => "GMT-01:00",
        'Europe/London' => "GMT 00:00",
        'Europe/Paris' => "GMT+01:00",
        'Europe/Berlin' => "GMT+01:00",
        'Europe/Helsinki' => "GMT+02:00",
        'Asia/Jerusalem' => "GMT+02:00",
        'Europe/Moscow' => "GMT+03:00",
        'Asia/Tehran' => "GMT+03:30",
        'Asia/Tbilisi' => "GMT+04:00",
        'Asia/Kabul' => "GMT+04:30",
        'Asia/Karachi' => "GMT+05:00",
        'Asia/Calcutta' => "GMT+05:30",
        'Asia/Katmandu' => "GMT+05:45",
        'Asia/Dhaka' => "GMT+06:00",
        'Asia/Rangoon' => "GMT+06:30",
        'Asia/Bangkok' => "GMT+07:00",
        'Asia/Hong_Kong' => "GMT+08:00",
        'Asia/Seoul' => "GMT+09:00",
        'Asia/Tokyo' => "GMT+09:00",
        'Australia/Adelaide' => "GMT+09:30",
        'Australia/Sydney' => "GMT+10:00",
        'Asia/Magadan' => "GMT+11:00",
        'Pacific/Auckland' => "GMT+12:00",
        'Pacific/Fiji' => "GMT+12:00",
        'Pacific/Tongatapu' => "GMT+13:00",
    ];

    public static $phone_codes = [
        '+1', '+7', '+20', '+27', '+30', '+31', '+32', '+33', '+34', '+36', '+39', '+40', '+41', '+43', '+44', '+45', '+46', '+47', '+48', '+49', '+51', '+52', '+54', '+55', '+56', '+57', '+58', '+60', '+61', '+62', '+63', '+64', '+65', '+66', '+81', '+82', '+84', '+86', '+90', '+91', '+92', '+93', '+963', '+94', '+95', '+98', '+212', '+213', '+216', '+218', '+220', '+221', '+222', '+223', '+224', '+225', '+226', '+227', '+228', '+229', '+230', '+231', '+232', '+233', '+234', '+235', '+236', '+237', '+238', '+239', '+240', '+241', '+242', '+243', '+244', '+245', '+248', '+250', '+251', '+252', '+253', '+254', '+255', '+256', '+257', '+258', '+260', '+261', '+262', '+263', '+264', '+265', '+266', '+267', '+268', '+269', '+291', '+297', '+298', '+299', '+350', '+351', '+352', '+353', '+354', '+355', '+356', '+357', '+358', '+359', '+370', '+371', '+372', '+373', '+374', '+375', '+376', '+377', '+378', '+380', '+381', '+382', '+385', '+386', '+387', '+389', '+420', '+421', '+423', '+501', '+502', '+503', '+504', '+505', '+506', '+507', '+508', '+509', '+590', '+591', '+592', '+593', '+594', '+595', '+596', '+597', '+598', '+599', '+670', '+673', '+675', '+676', '+677', '+678', '+679', '+680', '+682', '+685', '+686', '+687', '+689', '+852', '+853', '+855', '+856', '+880', '+886', '+960', '+961', '+962', '+964', '+965', '+966', '+967', '+968', '+970', '+971', '+972', '+973', '+974', '+975', '+976', '+977', '+992', '+993', '+994', '+995', '+996', '+998', '+1242', '+1246', '+1264', '+1268', '+1284', '+1340', '+1345', '+1441', '+1473', '+1649', '+1664', '+1671', '+1684', '+1721', '+1758', '+1767', '+1784', '+1787', '+1809', '+1829', '+1849', '+1868', '+1869', '+1876',
    ];

    // 确定链接表名
    protected $name = 'setting';

    /**
     * 编辑配置信息
     * @param $param
     */
    public function editConfig($param)
    {
        foreach ($param as $key => $value) {
            if ($key == 'sys_theme_style') {
                setEnvVal('../.env', 'INDEX_THEME', $value);
            }
            $row = $this->where(['field' => $key])->find();
            if ($row) {
                if (is_string($value) || is_numeric($value)) {
                    $this->where(['field' => $key])->update(['value' => trim($value)]);
                } else {
                    $this->where(['field' => $key])->update(['value' => json_encode($value)]);
                }
            } else {
                $this->insert([
                    'name' => trim($key),
                    'field' => trim($key),
                    'value' => trim($value),

                ]);
            }
            $this->clearConfigCache($key);
        }
        $this->reWriteConfig();
        return msg(1, '', 'success');
    }

    public function reWriteConfig()
    {
        $config = $this->getConfigInfo();
        if (extension_loaded('redis')) {
            Cache::store("redis")->set(config("redis_cache_key.config"), $config);
        }
    }

    /**
     * 获取系统设置信息
     */
    public function getConfigInfo()
    {
        $all = $this->select();
        $result = [];
        foreach ($all as $row) {
            $result[$row['field']] = $row['value'];
        }
        return $result;
    }

    /**
     * 根据字段名称获取指定配置数据
     * @param $field
     * @param bool $cache
     * @return mixed|string
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     */
    public static function getConfigValue($field, $cache = true)
    {
        $redis = \think\facade\Cache::store('redis');
        $redis_key = "config_field_" . $field;
        if ($redis->has($redis_key) && $cache) {
            $temp = $redis->get($redis_key);
            if (empty($temp)) {
                return "";
            }
            return unserialize($temp);
        }
        $temp = self::where('field', $field)->field('value')->find();
        if (empty($temp)) {
            return "";
        }
        $redis->set($redis_key, serialize($temp['value']));
        return $temp['value'];
    }

    /**
     * 清空配置缓存
     * @param $field
     * @return bool
     */
    public function clearConfigCache($field)
    {
        $redis = \think\facade\Cache::store('redis');
        $redis_key = "config_field_" . $field;
        if ($redis->has($redis_key)) {
            $redis->set($redis_key, '', 1);
        }
        return true;
    }
}
