<?php

/** ========= 字符串相关函数 =========== **/

function randColor(){
    $colors = array();
    for($i = 0;$i<6;$i++){
        $colors[] = dechex(rand(0,15));
    }
    return implode('',$colors);
}

/**
 * 获取颜色值数组
 * @param type $num
 * @return array
 */
function getColors($num = 0) {
    $colors = [];
    for ($i = 0; $i < intval($num); $i++) {
        $color = "#" . randColor();
        if (strtoupper($color) === "#FFFFFF" || in_array($color, $colors)) {
            $i--;
            continue;
        }
        $colors[$i] = $color;
    }

    return  $colors;
}

/**
 * 获取随机字符串
 * @param number $length 长度
 * @param string $type 类型
 * @param number $convert 转换大小写
 * @return string 随机字符串
 */
function random($length = 6, $type = 'all', $convert = 0) {
    $config = array(
        'number' => '1234567890',
        'letter' => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ',
        'string' => 'abcdefghjkmnpqrstuvwxyzABCDEFGHJKMNPQRSTUVWXYZ23456789',
        'all' => 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890'
    );

    if (!isset($config[$type]))
        $type = 'string';
    $string = $config[$type];

    $code = '';
    $strlen = strlen($string) - 1;
    for ($i = 0; $i < $length; $i++) {
        $code .= $string[mt_rand(0, $strlen)];
    }
    if (!empty($convert)) {
        $code = ($convert > 0) ? strtoupper($code) : strtolower($code);
    }
    return $code;
}

/**
 * 字符串截取，支持中文和其他编码
 * @static
 * @access public
 * @param string $str 需要转换的字符串
 * @param string $start 开始位置
 * @param string $length 截取长度
 * @param string $charset 编码格式
 * @param string $suffix 截断显示字符
 * @return string
 */
function msubstr($str, $start = 0, $length = null, $charset = "utf-8", $suffix = true) {
    if (function_exists("mb_substr")) {
        $slice = mb_substr($str, $start, $length, $charset);
    } elseif (function_exists('iconv_substr')) {
        $slice = iconv_substr($str, $start, $length, $charset);
        if (false === $slice) {
            $slice = '';
        }
    } else {
        $re['utf-8'] = "/[\x01-\x7f]|[\xc2-\xdf][\x80-\xbf]|[\xe0-\xef][\x80-\xbf]{2}|[\xf0-\xff][\x80-\xbf]{3}/";
        $re['gb2312'] = "/[\x01-\x7f]|[\xb0-\xf7][\xa0-\xfe]/";
        $re['gbk'] = "/[\x01-\x7f]|[\x81-\xfe][\x40-\xfe]/";
        $re['big5'] = "/[\x01-\x7f]|[\x81-\xfe]([\x40-\x7e]|\xa1-\xfe])/";
        preg_match_all($re[$charset], $str, $match);
        $slice = join("", array_slice($match[0], $start, $length));
    }
    return $suffix ? $slice . '...' : $slice;
}

/**
 * 隐藏字符串中间部分
 * @param type $str
 * @param int $l
 * @return type
 */
function hidnstr($str, $l = null){
    $len = mb_strlen($str);
    if (is_null($l)) {
        $l = ceil($len/3);
        $l = $l > 6 ? 6 : $l;
    }
    return mb_substr($str,0, $l).'****'.mb_substr($str, $l * -1);
}

/**
 *
 * @param type $string
 * @param type $find
 * @return type
 */
function strexists($string, $find) {
    return !(strpos($string, $find) === FALSE);
}

/**
 * 生成excel列名超过26列大于Z时的解决方法
 * @staticvar array $_indexCache
 * @param type $pColumnIndex

 * @return string
 */
function stringFromColumnIndex($pColumnIndex = 0) {
    static $_indexCache = array();

    if (!isset($_indexCache[$pColumnIndex])) {
        // Determine column string
        if ($pColumnIndex < 26) {
            $_indexCache[$pColumnIndex] = chr(65 + $pColumnIndex);
        } elseif ($pColumnIndex < 702) {
            $_indexCache[$pColumnIndex] = chr(64 + (int)($pColumnIndex / 26)) . chr(65 + $pColumnIndex % 26);
        } else {
            $_indexCache[$pColumnIndex] = chr(64 + (($pColumnIndex - 26) / 676)) . chr(65 + ((($pColumnIndex - 26) % 676) / 26)) . chr(65 + $pColumnIndex % 26);
        }
    }
    return $_indexCache[$pColumnIndex];
}

/**
 * 替换安全日志信息
 * @param type $format
 * @param type $data
 * @return type
 */
function replaceOperatingInfos($format, $data) {
    if($format != '' && !empty($data)) {
        foreach ($data as $key => $value) {
            $format = str_replace_limit("[" .strtoupper($key) . "]", $value, $format, 1);
        }
    }
    return $format;
}

//可以实现替换次数的控制，不仅限于只替换一次，比如$limit为2的时候表示一个词出现很多吃的时候仅替换2次，-1表示全部替换。$search 和 $replace 都可以是字符串或者数组，但必须对应
function str_replace_limit($search,$replace,$content,$limit=-1){
    if(is_array($search)){
        foreach ($search as $k=>$v){
            $search[$k]='`'.preg_quote($search[$k],'`').'`';
        }
    }else{
        $search='`'.preg_quote($search,'`').'`';
    }
    return preg_replace($search,$replace,$content,$limit);
}

/**
 * 生成订单号
 * @return string
 */
function genOrderSN($order_id = '', $prefix = "") {
    return $prefix . date ( 'dmYHis' ) . rand ( 10, 99 ) . $order_id;
}

/**
 * Convert BR tags to nl
 *
 * @param string The string to convert
 * @return string The converted string
 */
function br2nl($string) {
    return preg_replace('/\<br(\s*)?\/?\>/i', "\n", $string);
}

function html2($string) {
    $pattern = '/\<(pre|div|p|table|thead|th|tbody|tr|td|ul|li|ol|li|dl|dt|dd|h2|h3|h4|h5|span|strong)([^>]*)>/i';
    $pattern2 = '/\<\/(pre|div|p|table|thead|th|tbody|tr|td|ul|li|ol|li|dl|dt|dd|h2|h3|h4|h5|span|strong)>/i';
    $pattern3 = '/\<img.*?src="(.*?)".*?\/?>/i';//图片
    return preg_replace([$pattern, $pattern2, $pattern3], ["[$1$2]", "[/$1]", "img[$1]"], $string);
}

/**
 * 生成uuid
 * @return string
 */
function uuid() {
    $chars = md5(uniqid(random(32), true));
    $uuid = substr($chars, 0, 8) . '-'
            . substr($chars, 8, 4) . '-'
            . substr($chars, 12, 4) . '-'
            . substr($chars, 16, 4) . '-'
            . substr($chars, 20, 12);
    return strtoupper($uuid);
}

function getStatusText($status, $text) {
    switch ($status) {
        case 0:
            $format = "<span class='label label-default'>%s</span>";
            break;
        case 1:
            $format = "<span class='label label-primary'>%s</span>";
            break;
        case 2:
            $format = "<span class='label label-danger'>%s</span>";
            break;
        case 3:
            $format = "<span class='label label-warning'>%s</span>";
            break;
        case 4:
            $format = "<span class='label label-info'>%s</span>";
            break;
        default:
            $format = "<span class='label label-primary'>%s</span>";
            break;
    }

    return sprintf($format, lang($text));
}
