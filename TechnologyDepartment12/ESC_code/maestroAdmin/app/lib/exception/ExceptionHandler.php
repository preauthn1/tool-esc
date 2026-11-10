<?php

namespace app\lib\exception;

use think\db\exception\DataNotFoundException;
use think\db\exception\ModelNotFoundException;
use think\exception\Handle;
use think\exception\HttpException;
use think\exception\HttpResponseException;
use think\exception\ValidateException;
use think\facade\Log;
use think\facade\Request;
use Exception;
use think\facade\Env;
use think\Response;
use Throwable;

/*
 * 重写Handle的render方法，实现自定义异常消息
 */

class ExceptionHandler extends Handle
{
    private $code;
    private $msg;
    private $errorCode;
    private $extendedData = [];

    /**
     * 不需要记录信息（日志）的异常类列表
     * @var array
     */
    protected $ignoreReport = [
        HttpException::class,
        HttpResponseException::class,
        ModelNotFoundException::class,
        DataNotFoundException::class,
        ValidateException::class,
    ];

    /**
     * Report or log an exception.
     *
     * @access public
     * @param  Throwable  $exception
     * @return void
     */
    public function report(Throwable $exception): void
    {
        if (!$this->isIgnoreReport($exception)) {
            // 收集异常数据
            if ($this->app->isDebug()) {
                $data = [
                    'file'    => $exception->getFile(),
                    'line'    => $exception->getLine(),
                    'message' => $this->getMessage($exception),
                    'code'    => $this->getCode($exception),
                ];
                $log = "[{$data['code']}]{$data['message']}[{$data['file']}:{$data['line']}]";
            } else {
                $data = [
                    'code'    => $this->getCode($exception),
                    'message' => $this->getMessage($exception),
                ];
                $log = "[{$data['code']}]{$data['message']}";
            }

            if ($this->app->config->get('log.record_trace')) {
                $log .= PHP_EOL.$exception->getTraceAsString();
            }

            try {
                if ($exception instanceof BaseException) {

                } else {
                    $this->app->log->record($log, 'error');
                }
            } catch (Exception $e) {
            }
        }
    }

    public function render($request, Throwable $e): Response
    {
        if ($e instanceof BaseException) {
            //如果是自定义异常，则控制http状态码，不需要记录日志
            //因为这些通常是因为客户端传递参数错误或者是用户请求造成的异常
            //不应当记录日志

            $this->code = $e->code;
            $this->msg = $e->msg;
            $this->errorCode = $e->errorCode;
            $this->extendedData = $e->extendedData;
        } else {
            if ($e instanceof ValidateException) {
                $this->code = 200;
                $this->msg = $e->getMessage();
                $this->errorCode = -1;
            } else {
                if ($e instanceof HttpResponseException) {
                    return $e->getResponse();
                } else {
                    // 如果是服务器未处理的异常，将http状态码设置为500，并记录日志
                    if (Env::get('APP_DEBUG')) {
                        // 调试状态下需要显示TP默认的异常页面，因为TP的默认页面
                        // 很容易看出问题
                        return parent::render($request, $e);
                    }

                    $this->code = 500;
                    $this->msg = 'sorry，we make a mistake. (^o^)Y';
                    $this->errorCode = 999;
                    $this->recordErrorLog($e);
                }
            }
        }

        $request = Request::instance();
        $result = [
            'msg'         => lang($this->msg),
            'code'        => $this->errorCode,
            'data'        => $this->extendedData,
            'request_url' => $request = $request->url()
        ];
        return json($result, $this->code);
    }

    /*
     * 将异常写入日志
     */
    private function recordErrorLog(Throwable $e)
    {
        Log::init([
            'type'  => 'File',
            'path'  => Env::get('runtime_path').'log/',
            'level' => ['error']
        ]);
        Log::record([
            'message' => $e->getMessage(),
            'code'    => $e->getCode(),
            'file'    => $e->getFile(),
            'line'    => $e->getLine(),
        ], 'error');
//        Log::record($e->getMessage(), 'error');
    }
}
