<?php

/**
 * 验证邮箱
 * @param string $email

 * @return boolean
 */
function validEmail($email) {
    $isValid = true;
    $atIndex = strrpos($email, "@");
    if (is_bool($atIndex) && !$atIndex) {
        $isValid = false;
    } else {
        $domain = substr($email, $atIndex + 1);
        $local = substr($email, 0, $atIndex);
        $localLen = strlen($local);
        $domainLen = strlen($domain);
        if ($localLen < 1 || $localLen > 64) {
            // local part length exceeded
            $isValid = false;
        } else if ($domainLen < 1 || $domainLen > 255) {
            // domain part length exceeded
            $isValid = false;
        } else if ($local[0] == '.' || $local[$localLen - 1] == '.') {
            // local part starts or ends with '.'
            $isValid = false;
        } else if (preg_match('/\\.\\./', $local)) {
            // local part has two consecutive dots
            $isValid = false;
        } else if (!preg_match('/^[A-Za-z0-9\\-\\.]+$/', $domain)) {
            // character not valid in domain part
            $isValid = false;
        } else if (preg_match('/\\.\\./', $domain)) {
            // domain part has two consecutive dots
            $isValid = false;
        } else if (!preg_match('/^(\\\\.|[A-Za-z0-9!#%&`_=\\/$\'*+?^{}|~.-])+$/', str_replace("\\\\", "", $local))) {
            // character not valid in local part unless
            // local part is quoted
            if (!preg_match('/^"(\\\\"|[^"])+"$/', str_replace("\\\\", "", $local))) {
                $isValid = false;
            }
        }
        if ($isValid && !(checkdnsrr($domain, "MX") || checkdnsrr($domain, "A"))) {
            // domain not found in DNS
            //$isValid = false;
        }
    }
    return $isValid;
}

/**
 * 验证邮编
 * @param string $val
 * @return boolean
 */
function validPost($val) {
    return validRegexp('/^[0-9]\d{5}$/', $val);
}

/**
 * 验证手机号码
 * @param string $val
 * @return boolean
 */
function validMobile($val) {
    return validRegexp('/^1[3-9]\d{9}$/', $val);
}

/**
 * 验证港澳台手机号码
 * @param string $val
 * @return boolean
 */
function validHMTMobile($val) {
    return validRegexp('/^([6|9])\d{7}$|^[0][9]\d{8}$|^[6]([8|6])\d{5}$/', $val);
}

/**
 * 验证身份证号码
 * @param string $val
 * @return boolean
 */
function validIdCard($val) {
    return validRegexp('/(^\d{15}$)|(^\d{18}$)|(^\d{17}(\d|X|x)$)/', $val);
}

/**
 * 验证数字
 * @param string $val
 * @return boolean
 */
function validNumber($val) {
    return validRegexp('/^[0-9]\d*$/', $val);
}

/**
 * 验证字母
 * @param string $val
 * @return boolean
 */
function validLetter($val) {
    return validRegexp('/^[A-Za-z]+$/', $val);
}

/**
 * 验证数字与字母
 * @param string $val
 * @return boolean
 */
function validNumberLetter($val) {
    return validRegexp('/^[A-Za-z0-9]+$/', $val);
}

/**
 * 验证中文
 * @param string $val
 * @return boolean
 */
function validChinese($val) {
    return validRegexp('/^[\x{4e00}-\x{9fa5}]+$/u', $val);
}

/**
 * 验证中文，英文
 * @param string $val
 * @return boolean
 */
function validLetterChinese($val) {
    $pattern = "/^[a-zA-Z(\x{4e00}-\x{9fa5})]{1,}$/u"; //中文，英文匹配
    return validRegexp($pattern, $val);
}

/**
 * 验证中文，数字，英文
 * @param string $val
 * @return boolean
 */
function validLetterNumberChinese($val) {
    $pattern = "/^[a-zA-Z0-9(\x{4e00}-\x{9fa5})]{1,}$/u"; //中文，数字，英文匹配
    return validRegexp($pattern, $val);
}

/**
 * 根据正则验证文本
 * @param string $val
 * @return boolean
 */
function validRegexp($regexp, $val) {
    return !!preg_match($regexp, $val);
}

/**
 * 验证是否是TRC地址
 * @param $address
 * @return bool
 */
function isValidTRCAddress($address): bool
{
    // TRON地址通常是34个字符长
    if (strlen($address) != 34) {
        return false;
    }

    // TRON地址以"T"开头
    if (substr($address, 0, 1) != "T") {
        return false;
    }

    // TRON地址剩余的字符应该是Base58编码，这里简化检查只确保是数字或字母
    if (!ctype_alnum(substr($address, 1))) {
        return false;
    }

    return true;
}

/**
 * 验证是否是BEP20地址
 * @param $address
 * @return bool
 */
function isValidBEPAddress($address): bool
{
    // BEP20地址正则表达式
    $pattern = '/^0x[a-fA-F0-9]{40}$/';
    return validRegexp($pattern, $address);
}
