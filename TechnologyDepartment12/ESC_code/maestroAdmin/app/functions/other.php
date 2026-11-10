<?php

use ip\MaxMind\Reader;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use PhpOffice\PhpSpreadsheet\Writer\Xls;
use think\facade\Log;

/** ========= 其他函数 =========== * */

/**
 * 格式化字节大小
 * @param number $size 字节数
 * @param string $delimiter 数字和单位分隔符
 * @return string            格式化后的带单位的大小
 */
function format_bytes($size, $delimiter = '')
{
    $units = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
    for ($i = 0; $size >= 1024 && $i < 5; $i++)
        $size /= 1024;
    return round($size, 2) . $delimiter . $units[$i];
}

/**
 * 获取IP
 * @staticvar string $ip
 * @return type
 */
function getip()
{
    static $ip = null;
    if (null !== $ip) {
        return $ip;
    }

    $httpAgentIp = config('http_agent_ip');

    if ($httpAgentIp && isset($_SERVER[$httpAgentIp])) {
        $ip = $_SERVER[$httpAgentIp];
    }

    if (empty($ip)) {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
        if (isset($_SERVER['HTTP_CDN_SRC_IP'])) {
            $ip = $_SERVER['HTTP_CDN_SRC_IP'];
        } elseif (isset($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $ip = $_SERVER['HTTP_CF_CONNECTING_IP'];
        } elseif (isset($_SERVER['HTTP_CLIENT_IP']) && preg_match('/^([0-9]{1,3}\.){3}[0-9]{1,3}$/', $_SERVER['HTTP_CLIENT_IP'])) {
            $ip = $_SERVER['HTTP_CLIENT_IP'];
        } elseif (isset($_SERVER['HTTP_X_FORWARDED_FOR']) and preg_match_all('#\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}#s', $_SERVER['HTTP_X_FORWARDED_FOR'], $matches)) {
            foreach ($matches[0] as $xip) {
                if (!preg_match('#^(10|172\.16|192\.168)\.#', $xip)) {
                    $ip = $xip;
                    break;
                }
            }
        }
    }

    return empty($ip) ? '0.0.0.0' : $ip;
}

/**
 * 生成execl
 * @param array $header execl首行标题
 * @param array $list 数据源
 * @param string $file_name 生成文件名称
 * @param string $title 文件标题
 * @param int $start_line 开始行号
 * @param boolean $isput 是否写入文件,不写入文件则直接输出，默认不写入文件
 * @param string $save_path 保存路径
 * @param string $classes Excel2007|Excel2005
 * @return string|boolean
 */
function genXlsx(array $header, $list, $file_name = "data_list", $title = '', $start_line = 1, $isput = false, $save_path = '', $classes = 'Excel2007')
{
    if (empty($header)) {
        return false;
    }

    $file_name .= $classes == "Excel2007" ? ".xlsx" : ".xls";

    //创建一个excel
    $objPHPExcel = new Spreadsheet();
    //设置excel的属性：
    //创建人
    $objPHPExcel->getProperties()->setCreator("admin");
    //最后修改人
    $objPHPExcel->getProperties()->setLastModifiedBy("admin");
    //标题
    //$objPHPExcel->getProperties()->setTitle($title);
    //题目
    $objPHPExcel->getProperties()->setSubject($title);
    //设置当前的sheet
    $objPHPExcel->setActiveSheetIndex(0);
    //设置sheet的name
    $objPHPExcel->getActiveSheet()->setTitle($title ?: $file_name);
    $line = $start_line;
    if ($title != "") {
        $objPHPExcel->getActiveSheet()->mergeCells("A{$line}:" . stringFromColumnIndex((count($header) - 1)) . $line);
        $objPHPExcel->getActiveSheet()->setCellValue("A{$line}", $title);
        $line++;
    }

    //设置表格头
    $i = 0;
    foreach ($header as $key => $value) {
        $objPHPExcel->getActiveSheet()->setCellValue(stringFromColumnIndex($i) . $line, $value);
        $i++;
    }
    $line++;
    $keys = array_keys($header);
    if (!empty($list)) {
        foreach ($list as $row) {
            $col = 0;
            foreach ($keys as $key) {
                $cell = stringFromColumnIndex($col) . $line;
                $objPHPExcel->getActiveSheet()->setCellValue($cell, ' ' . "$row[$key]");
                $col++;
            }
            $line++;
        }
    }

    $arr = array();
    for ($i = 0; $i <= count($header) - 1; $i++) {
        $col_name = stringFromColumnIndex($i);
        for ($j = 1; $j < $line; $j++) {
            if ($title == "") {
                $val = $objPHPExcel->getActiveSheet()->getCell("{$col_name}{$j}")->getValue();
                $arr[$col_name][] = strlen($val);
            } elseif ($j > 1 && $title != "") {
                $val = $objPHPExcel->getActiveSheet()->getCell("{$col_name}{$j}")->getValue();
                $arr[$col_name][] = strlen($val);
            }
            if ($col_name == "A" && $title != "") {
                $objPHPExcel->getActiveSheet()->getRowDimension("{$j}")->setRowHeight(18);
            }
        }
    }
    foreach ($arr as &$arr_row) {
        $arr_row = array_filter($arr_row);
        if (!empty($arr_row)) {
            $arr_row = array_unique($arr_row);
            $arr_row = max($arr_row);
        }
    }
    foreach ($arr as $col_name => $value) {
        $width = intval($value) < 4 ? 4 : sprintf("%.2f", (intval($value) * 11 / 9.7));
        if ($width > 40) {
            $width = 40;
        }
        $objPHPExcel->getActiveSheet()->getColumnDimension("{$col_name}")->setWidth($width);
        $objPHPExcel->getActiveSheet()->getColumnDimension("{$col_name}")->setAutoSize(false);
    }
    if ($classes == "Excel2007") {
        $objWriter = new Xlsx($objPHPExcel);
    } else {
        $objWriter = new Xls($objPHPExcel);
    }
    if ($isput) {
        $file_path = $save_path . $file_name;
        $objWriter->save($file_path);
        return $file_name;
    } else {
        header("Pragma: public");
        header("Expires: 0");
        header("Cache-Control:must-revalidate, post-check=0, pre-check=0");
        header("Content-Type:application/force-download");
        header("Content-Type:application/vnd.ms-execl");
        header("Content-Type:application/octet-stream");
        header("Content-Type:application/download");
        header('Content-Disposition:attachment;filename="' . $file_name . '"');
        header("Content-Transfer-Encoding:binary");
        $objWriter->save('php://output');
        exit();
    }
}

/**
 * 生成CSV
 * @param string $header execl首行标题
 * @param string $list 数据源
 * @param string $file_name 生成文件名称
 * @param string $title 文件标题
 * @param int $start_line 开始行号
 * @param boolean $isput 是否写入文件,不写入文件则直接输出，默认不写入文件
 * @param string $save_path 保存路径
 * @param string $classes Excel2007|Excel2005
 * @return string|boolean
 */
function genCsv($header, $list, $file_name = "data_list")
{
    if (empty($header)) {
        return false;
    }
//    $csv[] = utfToGbk(implode(",", array_values($header)));
    $fileData = utfToGbk(implode(",", array_values($header)));
    $keys = array_keys($header);
    foreach ($list as $row) {
        $_row = [];
        foreach ($keys as $k) {
            $_row[] = "\t" . utfToGbk($row[$k]);
        }
        $fileData .= ("\n" . implode(",", $_row));
    }

//    $fileData = implode("\n", $csv);
    header("Pragma: public");
    header("Expires: 0");
    header("Cache-Control:must-revalidate, post-check=0, pre-check=0");
    header("Content-Type:application/force-download");
    header("Content-type:text/csv");
    header("Content-Type:application/download");
    header('Content-Disposition:attachment;filename="' . $file_name . '.csv"');
    header("Content-Transfer-Encoding:binary");
    echo $fileData;
    exit;
}

function utfToGbk($data)
{
    return $data; //iconv('utf-8', 'GBK', $data);
}

/**
 * IP转地区
 * @param string $ip
 * @param type $fields
 * @return string
 */
function ip2location($ip, $fields = [2, 3, 4], $ipdatabase = null)
{
    if (empty($ip)) {
        return "";
    }
    if (is_null($ipdatabase)) {
        $ipdatabase = cookie("ipdatabase");
    }
    if ($ipdatabase != "en") {
        // 配置数据库路径
        $databaseFile = __DIR__ . DIRECTORY_SEPARATOR . '../../extend/ip/MaxMind/GeoLite2-Country.mmdb';
        $reader = new Reader($databaseFile);
        $record = $reader->get($ip);
        if ($record) {
            $country = $record['country']['names']['zh-CN'] ?? 'Unknown';
            return $country;
        } else {
            return '';
        }
        $reader->close();
//        $res = \ip\GetIpArea::find(trim($ip));
//        if (!isArrayAndNotEmpty($res)) {
//            return "";
//        } else {
//            return implode(" ", array_unique(array_filter($res)));
//        }
    } else {
        $db = new \ip\IP2Location();
        $records = $db->lookup(trim($ip), $fields);
        if (is_array($records)) {
            $str = [];
            $str[] = isset($records['countryName']) && $records['countryName'] != "-" ? "{$records['countryName']}" : "";
            $str[] = isset($records['regionName']) && $records['regionName'] != "-" ? "{$records['regionName']}" : "";
            $str[] = isset($records['cityName']) && $records['cityName'] != "-" ? "{$records['cityName']}" : "";
            $records = array_filter(array_unique($str));
            return is_array($records) ? implode(" ", $records) : "";
        }
        return $records === "-" ? "" : $records;
    }
}

/**
 * IP转地区
 * @param type $ip
 * @param type $fields
 * @return type
 */
function ip2location_arr($ip, $fields = [2, 3, 4], $ipdatabase = null)
{
    if (empty($ip)) {
        return "";
    }
    if (is_null($ipdatabase)) {
        $ipdatabase = cookie("ipdatabase");
    }
    if ($ipdatabase != "en") {
        // 配置数据库路径
        $databaseFile = __DIR__ . DIRECTORY_SEPARATOR . '../../extend/ip/MaxMind/GeoLite2-Country.mmdb';
        $reader = new Reader($databaseFile);
        $record = $reader->get($ip);
        if ($record) {
            return $record;
        } else {
            return '';
        }
        $reader->close();
//        return \ip\GetIpArea::find(trim($ip));
    } else {
        $db = new \ip\IP2Location();
        return $db->lookup(trim($ip), $fields);
    }
}

/**
 * 数字单位转换
 * @param type $number
 * @return type
 */
function get_number_unit($number)
{
    $num = 0;
    $unit = "";
    if ($number > 100000000) {
        $num = $number / 100000000;
        $unit = "亿";
    } elseif ($number > 10000) {
        $num = $number / 10000;
        $unit = "万";
    } else {
        $num = $number;
    }

    return sprintf4f($num, 2) . lang($unit);
}

/**
 * 过滤XSS
 * @staticvar type $obj
 * @param type $val
 * @return type
 */
function removeXSS($val)
{
    static $obj = null;
    if ($obj === null) {
        $obj = new HTMLPurifier();
    }

    // 返回过滤后的数据
    return $obj->purify($val);
}

/**
 * 判断IP是否在某个网络内
 * @param $ip
 * @param $network
 * @return bool
 */
function ip_in_network($ip, $network)
{
    $ip = (double)(sprintf("%u", ip2long($ip)));
    $s = explode('/', $network);
    $network_start = (double)(sprintf("%u", ip2long($s[0])));
    $network_len = pow(2, 32 - $s[1]);
    $network_end = $network_start + $network_len - 1;

    if ($ip >= $network_start && $ip <= $network_end) {
        return true;
    }
    return false;
}

/**
 * 获取最小IP
 * @param type $ip
 * @param type $num
 * @return string
 */
function ip2start($ip, $num = 1, $s = 16)
{
    $_ip = explode(".", $ip);
    if ($num <= 0) {
        return $ip . "/" . $s;;
    } else if ($num >= 4) {
        return "0.0.0.0/" . $s;;
    }

    switch ($num) {
        case 1:
            $ip = "{$_ip[0]}.{$_ip[1]}.{$_ip[2]}.0";
            break;
        case 2:
            $ip = "{$_ip[0]}.{$_ip[1]}.0.0";
            break;
        case 3:
            $ip = "{$_ip[0]}.0.0.0";
            break;
        default:
            $ip = "0.0.0.0";
            break;
    }

    return $ip . "/" . $s;
}

/**
 * 随机生成手机号码
 * @return string
 */
function randomMobile()
{
    $prefix = [
        "134", "135", "136", "137", "138", "139", "147", "150", "151", "152", "157", "158", "159", "172", "178", "182", "183", "184", "187", "188", "195", "197", "198",
        "130", "131", "132", "145", "155", "156", "166", "175", "176", "185", "186", "196",
        "133", "149", "153", "180", "181", "189", "173", "177", "190", "191", "193", "199", "192", "167", "170", "171", "162", "165"
    ];
    $mobile = trim($prefix[array_rand($prefix)]) . random(8, 'number');
    return $mobile;
}

/**
 * 随机生成越南手机号码
 * @return string
 */
function randomVnMobile()
{
    $prefix = [
        81, 82, 83, 84, 85, 32, 33, 34, 35, 36, 37, 38, 39, 56, 58, 57, 59, 76, 77, 78, 79
    ];
    $mobile = trim($prefix[array_rand($prefix)]) . random(7, 'number');
    return $mobile;
}

/**
 * 随机生成泰国手机号码
 * @return string
 */
function randomThbMobile()
{
//    $prefix = [
//        "02", "03", "04", "05", "07"
//    ];
//    $mobile = trim($prefix[array_rand($prefix)]) . random(7, 'number');
    $mobile = "08" . random_int(1, 9) . random(7, 'number');
    return $mobile;
}

/**
 * 随机生成印尼手机号码
 * @return string
 */
function randomIdnMobile()
{
    $mobile = "08" . random(10, 'number');
    return $mobile;
}

/**
 * 随机生成印度手机号码
 * @return string
 */
function randomIndMobile()
{
    $mobile = "09" . random(10, 'number');
    return $mobile;
}

/**
 *
 * @param type $userid
 * @param type $action
 * @param type $timeout
 * @return type
 */
function userLock($userid, $action, $timeout = 5)
{
    $locker = new util\Locker("{$action}:{$userid}");
    if (!$locker->lock($timeout)) {
        $locker = null;
    }
    return $locker;
}

/**
 * 发生邮件
 * @param type $to
 * @param type $name
 * @param type $subject
 * @param type $body
 * @param type $cc
 * @return boolean
 */
function send_mail($to, $name, $subject, $body, $cc = [])
{
    $from = "m19928738415@163.com";

    $host = "smtp.163.com";
    $secure = "ssl";
    $port = "465";
    $username = "m19928738415@163.com";  //<> give errors
    $REDACTED;

    $mail = new PHPMailer(true);                              // Passing `true` enables exceptions
    try {
        //Server settings
        $mail->CharSet = 'UTF-8';
        $mail->SMTPDebug = 0;                                 // Enable verbose debug output
        $mail->isSMTP();                                      // Set mailer to use SMTP
        $mail->Host = $host;  // Specify main and backup SMTP servers
        $mail->SMTPAuth = true;                               // Enable SMTP authentication
        $mail->Username = $username;                 // SMTP username
        $mail->Password = $password;                           // SMTP password
        $mail->SMTPSecure = $secure;                            // Enable TLS encryption, `ssl` also accepted
        $mail->Port = $port;                                    // TCP port to connect to
        //Recipients
        $mail->setFrom($from, "调度系统");
        $mail->addAddress($to, $name);     // Add a recipient
//        $mail->addAddress('ellen@example.com');               // Name is optional
//        $mail->addReplyTo('info@example.com', 'Information');
        if (!empty($cc)) {
            foreach ($cc as $row) {
                $mail->addCC($row['mail'], $row['name']);
            }
        }
        $mail->isHTML(true);                                  // Set email format to HTML
        $mail->Subject = $subject;
        $mail->Body = $body;

        $mail->send();
        return true;
    } catch (Exception $e) {
        Log::write('Message could not be sent. Mailer Error: ' . $mail->ErrorInfo, "error");
        return false;
    }
}
