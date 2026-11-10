<?php

/** ========= 数组相关函数 =========== **/

/**
 * 判断是否是一个数组，并且是非空数组
 *
 * @param type $array
 * @return type
 */
function isArrayAndNotEmpty($array) {
    return (is_array($array) && (!empty($array)));
}

/**
 * 自定义数组过滤空数据
 * @param type $arr
 */
function myArrayFilter($arr) {
    return array_filter($arr, function ($value) {
        return '' !== $value && !is_null($value);
    });
}
