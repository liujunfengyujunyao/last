<?php

namespace app\api\controller;
use think\Db;
use app\common\controller\Api;

/**
 * 首页接口
 */
header('Access-Control-Allow-Headers: Content-Type,Content-Length,Accept-Encoding,X-Requested-with, Origin, Authorization,access-control-request-headers'); // 设置允许自定义请求头的字段
//header("Access-Control-Max-Age", "1800");
header("Content-Type: text/html;charset=utf-8");
header('Access-Control-Allow-Methods:POST,GET,OPTIONS,DELETE'); // 允许请求的类型
header('Access-Control-Allow-Credentials: true'); // 设置是否允许发送 cookies
header('Access-Control-Allow-Origin:*');
class Index extends Api
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];

    /**
     * 首页
     *
     */



    public function index()
    {
        $token = request()->param('token');
        $user = DB::name('user')->where('token',$token)->find();
        if(!$user){
            $this->error(__('登陆超时'), null, 301);//用户名重复
        }
        $auth_ids = DB::name('client_rule')->where('id',$user['rule_id'])->value('auth_ids');
        $auth = DB::name('client_auth')->where('id','in',$auth_ids)->select();
        $this->success(__('Logged in successful'), $auth);
    }


    //主页显示信息接口
    public function home()
    {
        $user = $this->auth->getUser();
        $machine_ids = DB::name('machine')
            ->where('user_id',$user['pid'])
            ->column('machine_id');
        if($user['rule_id'] == 7){
            $machine_ids = DB::name('client_domain')->where('user_id',$user['id'])->value('machine_ids');
        }
//        dump($user['rule_id'])
//        dump($user['pid']);
//        halt($machine_ids);
        $stat_period = strtotime(date('Y-m-d',time()));
        $where_month['stat_period'] = ['between',[strtotime(date("Y-m-d H:i:s",mktime(0, 0 , 0,date("m"),1,date("Y")))),strtotime(date("Y-m-d H:i:s",mktime(23,59,59,date("m"),date("t"),date("Y"))))]];
        $where_today = $stat_period;
        $where_yesterday = $stat_period - 60*60*24;
        $result = [
        'month_today' => DB::name('machine_day_statistics')->where('machine_id','in',$machine_ids)->where($where_month)->sum('online_income'),
        'today_today' => DB::name('machine_day_statistics')->where('machine_id','in',$machine_ids)->where('stat_period',$where_today)->sum('online_income'),
        'yesterday'   => DB::name('machine_day_statistics')->where('machine_id','in',$machine_ids)->where('stat_period',$where_yesterday)->sum('online_income'),
        'machine_count' => DB::name('machine')->where('machine_id','in',$machine_ids)->count(),
        'online_count' => DB::name('machine')->where('machine_id','in',$machine_ids)->where('is_online',1)->count(),
        ];
//        $result = [
//            'month_today' => null,
//            'today_today' => null,
//            'yesterday'   => null,
//            'machine_count' => null,
//        ];
        return json_encode($result,JSON_UNESCAPED_UNICODE);

    }


}
