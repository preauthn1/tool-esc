<?php

namespace app\command;

use app\common\model\UserModel;
use Firebase\JWT\JWT;
use think\console\Command;
use think\console\Input;
use think\console\Output;

class TestToken extends Command
{

    protected function configure()
    {
        $this->setName('test_token')->setDescription('测试');
    }


    protected function execute(Input $input, Output $output)
    {
        $user_list = UserModel::where('status', 1)->field('id,login_key')->group('id')->limit(1, 5000)->select();
        $token_list = [];
        $len = 0;
        foreach ($user_list as $user) {
            if (!empty($user['login_key'])) {
                print_r($user['login_key']);
                $len++;
                $token = $this->generateTokens($user['id'], $user['login_key']);
                $level_id = 3;
                $REDACTED;
                if ($len % 10 == 0) {
                    $level_id = 6;
                    $REDACTED;
                }
                $token_list[] = [
                    'header' => [
                        'Authorization' => $token
                    ],
                    'httpdata' => [
                        "level_id" => $level_id . '',
                        "password" => $password
                    ]
                ];
//                $tokenInfo = (array)JWT::decode($token, new Key($jwtKey, 'HS256'));
//                print_r($token);
//                print_r($tokenInfo);
//                exit();
            }
        }
        print_r("token_list:".count($token_list)."\n");
        file_put_contents('token.json', json_encode($token_list));
        exit();
    }

    /**
     * 生成token
     * @param $user_id
     * @param $login_key
     * @return array
     * @throws \think\exception\DbException
     */
    private function generateTokens($user_id, $login_key): string
    {
        // 生成新的login_key
//        $login_key = random(32);
//        // 更新用户的login_key
//        $user = UserModel::get($user_id);
//        if ($user) {
//            $user->login_key = $login_key;
//            $user->save();
//        }
        $jwtKey = "REDACTED";
        return JWT::encode([
            'user_id' => $user_id,
            'create_time' => time(),
            'expires_in' => 24 * 15 * 3600,
            'login_key' => $login_key
        ], $jwtKey, 'HS256');
    }
}
