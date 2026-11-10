<?php
/**
 * 将unix时间戳转成字符
 * @param int $time unix时间戳，默认为当前时间
 * @param string $format 格式。默认为 Y-m-d H:i:s
 * @return string
 */
function timeToStr($time = NULL, $format = NULL) {
    if (!$time) {
        $time = time();
    }
    if (!$format) {
        $format = 'Y-m-d H:i:s';
    }
    return date($format, $time);
}

/**
 * 获取当前时间的字符串格式
 * @return string
 */
function now() {
    return timeToStr();
}

/**
 * 从unix时间戳获取时间部分
 * @param int $time unix时间戳，默认为当前时间
 * @return string
 */
function getTimeStr($time = NULL) {
    return timeToStr($time, 'H:i:s');
}

/**
 * 从unix时间戳获取日期部分
 * @param int $time unix时间戳，默认为当前时间
 * @return string
 */
function getDateStr($time = NULL) {
    return timeToStr($time, 'Y-m-d');
}

/**
 * 从unix时间戳获取日期部分
 * @param int $time unix时间戳，默认为当前时间
 * @return string
 */
function getYesterdayStr($time = NULL) {
    if(empty($time)){
        $time = strtotime('-1 day');
    }
    return timeToStr($time, 'Y-m-d');
}

/**
 * 从unix时间戳获取日期，并使用中文“年月日”表示
 * @param int $time unix时间戳，默认为当前时间
 * @return string
 */
function getChineseDate($time = NULL) {
    return timeToStr($time, 'Y年m月d日');
}

/**
 * 获取星期
 * @param type $time
 * @return string
 */
function getChineseWeek($time) {
    $weeks = array("日", "一", "二", "三", "四", "五", "六");
    $week = date("w", $time);
    return $weeks[$week];
}

/**
 * 显示某一个时间相当于当前时间在多少秒前，多少分钟前，多少小时前
 * @param int $timeInt time时间戳
 * @param string $format 时间显示格式
 * @return string
 */
function timeFormat($timeInt, $format = 'Y-m-d H:i:s') {
    if (empty($timeInt) || !is_numeric($timeInt) || !$timeInt) {
        return '';
    }
    $d = time() - $timeInt;
    if ($d < 0) {
        return '';
    } else {
        if ($d < 60) {
            return $d . '秒前';
        } else {
            if ($d < 3600) {
                return floor($d / 60) . '分钟前';
            } else {
                if ($d < 86400) {
                    return floor($d / 3600) . '小时前';
                } else {
                    if ($d < (86400 * 7)) {//7天内 
                        return floor($d / 86400) . '天前';
                    } else {
                        return date($format, $timeInt);
                    }
                }
            }
        }
    }
}

/**
 * 显示某一个时间相当于当前时间在多少秒前，多少分钟前，多少小时前
 * @param int $timeInt time时间戳
 * @return string
 */
function dateFormat($timeInt) {
    if (empty($timeInt) || !is_numeric($timeInt) || !$timeInt) {
        return '';
    }
    $d = time() - $timeInt;
    if ($d < 0) {
        return '';
    } else if ($d < 60) {//一分钟内
        return $d . ' 秒前';
    } else if ($d < 3600) {//一小时内
        return floor($d / 60) . ' 分钟前';
    } else if ($d < 86400) {//24小时内
        return floor($d / 3600) . ' 小时前';
    } else if ($d < (86400 * 30)) {//30天内 
        return floor($d / 86400) . ' 天前';
    } else if ($d < (86400 * 365)) {//一年内 
        return floor($d / (86400 * 30)) . ' 月前';
    } else {//一年前
        return floor($d / (86400 * 365)) . ' 年前';
    }
}

/**
 * 把字符串格式的时间日期转换成时间戳
 * @param string $dtime
 * @return int
 */
function getMkTime($dtime) {
    if (!preg_match("/[^0-9]/", $dtime)) {
        return $dtime;
    }
    $mt = strtotime(trim($dtime));
    return !empty($mt) ? $mt : time();
}

/**
 * 去除日期格式后面的毫秒数
 * @param type $datetime
 * @param type $format
 * @return string
 */
function removeMilliseconds($datetime = "", $format = "") {
    if($datetime != "" && $datetime != "1900-01-01 00:00:00.000") {
        return timeToStr(getMkTime($datetime), $format);
    }
    return "";
}

/**
 * 获取某个日期的下一日
 * @param type $time
 * @return type
 */
function getNextDay($time = null) {
    return getdaysAfter($time, 1);
}

/**
 * 获取某个日期的上一日
 * @param type $time
 * @return type
 */
function getPrevDay($time = null) {
    return getdaysAgo($time, 1);
}

/**
 * 获取某个日期的几天前的日期
 * @param type $time
 * @param type $day
 * @return type
 */
function getdaysAgo($time = null, $day = 1) {
    if (is_null($time)) {
        $time = time();
    }
    return strtotime("-{$day} days", getMkTime($time));
}

/**
 * 获取某个日期的几天后的日期
 * @param type $time
 * @param type $day
 * @return type
 */
function getdaysAfter($time = null, $day = 1) {
    if (is_null($time)) {
        $time = time();
    }
    return strtotime("+{$day} days", getMkTime($time));
}

/**
 * 计算：分:秒格式的秒数
 * @param string $minuteStr 00:01
 * @return int
 */
function minute2second($minuteStr) {
    list($minute, $second) = explode(':', $minuteStr);
    return intval($second) + (intval($minute) * 60);
}

/**
 * 计算：时:分:秒格式的秒数
 * @param string $hourStr 00:00:01
 * @return int
 */
function hour2second($hourStr) {
    list($hour, $minute, $second) = explode(':', $hourStr);
    return intval($second) + (intval($minute) * 60) + (intval($hour) * 60 * 60);
}

/**
 * 获取日期数组
 * @param type $dates
 * @param type $format
 */
function getDaysArr($dates, $format = "Ymd") {
    if (!isArrayAndNotEmpty($dates)) {
        return [];
    }
    $start_time = getMkTime($dates[0]);
    $end_time = getMkTime($dates[1]);
    $arr = [];
    for(; $start_time <= $end_time; $start_time+=86400) {
        $arr[] = timeToStr($start_time, $format);
    }

    return array_unique($arr);
}

/**
 * 获取指定日期的当前星期开始和结束的时间戳
 * @param string|int $timestamp 时间戳或日期格式字符串
 * @return type
 */
function getWeek($timestamp = null) {
    if (is_null($timestamp)) {
        $timestamp = time();
    } elseif (is_string($timestamp)) {
        $timestamp = strtotime($timestamp);
    }
    list($y, $m, $d, $w) = explode('-', date('Y-m-d-w', $timestamp));
    if($w == 0) $w = 7; //修正周日的问题
    return [
        mktime(0, 0, 0, $m, $d - $w + 1, $y), mktime(23, 59, 59, $m, $d - $w + 7, $y)
    ];
}