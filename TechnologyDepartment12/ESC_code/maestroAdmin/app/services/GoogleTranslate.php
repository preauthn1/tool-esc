<?php

namespace app\services;

use GuzzleHttp\Client;

class GoogleTranslate
{

    /**
     * Retrieves the translation of a text
     *
     * @param  string  $source
     *            Original language of the text on notation xx. For example: es, en, it, fr...
     * @param  string  $target
     *            Language to which you want to translate the text in format xx. For example: es, en, it, fr...
     * @param  string  $text
     *            Text that you want to translate
     *
     * @return string a simple string with the translation of the text in the target language
     */
    public static function translate(string $source, string $target, string $text): string
    {
        // Request translation
        $response = self::requestTranslation($source, $target, $text);

        // Clean translation
        $translation = self::getSentencesFromHtml($response);

        return $translation;
    }


    protected static function requestTranslation($source, $target, $text): string
    {
        // Google translate URL
        $url = "http://translate.google.com/m?tl={$target}&sl={$source}&q={$text}";
        $client = new Client();
        $response = $client->request('GET', $url);
        return $response->getBody()->getContents();
    }

    /**
     * @param $html
     * @return string A single string with the translation
     */
    protected static function getSentencesFromHtml($html): string
    {
        // 定义正则表达式
        $pattern = '/<div class="result-container">(.*?)<\/div>/s';

        // 使用 preg_match 进行匹配
        if (preg_match($pattern, $html, $matches)) {
            // 匹配成功，输出标签内的内容
            return $matches[1];
        } else {
            // 匹配失败
            return '';
        }
    }
}
