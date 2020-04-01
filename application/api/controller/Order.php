<?php

namespace app\api\controller;
use think\Db;
use app\common\controller\Api;
use fast\Random;
use think\Request;
//use app\api\model\machine;
/**
 * 组织接口
 */
header('Access-Control-Allow-Headers: Content-Type,Content-Length,Accept-Encoding,X-Requested-with, Origin, Authorization,access-control-request-headers'); // 设置允许自定义请求头的字段
//header("Access-Control-Max-Age", "1800");
header("Content-Type: text/html;charset=utf-8");
header('Access-Control-Allow-Methods:POST,GET,OPTIONS,DELETE'); // 允许请求的类型
header('Access-Control-Allow-Credentials: true'); // 设置是否允许发送 cookies
header('Access-Control-Allow-Origin:*');
class Order extends Api
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];
    protected $pub_rule = [3, 4, 9, 11, 12];//代理全局
    protected $dom_rule = [7, 8, 10];//需要指定设备的角色
    protected $logo_user = [3, 11];//品牌运营/监督管理员


//        public function _initialize()
//        {
//            parent::_initialize();
//            $this->model = model('Machine');
//        }
    /*
     * 订单列表
     *
     * */
    public function order_list()
    {
        $user = $this->auth->getUser();

        $rule_ids = [3,4,11,12,9];//运营管理员
        $params = request()->param();//默认时间为今天时间戳
        $where['createtime'] = ['between',[$params['start_time'],$params['end_time']]];
        if(in_array($user['rule_id'],$rule_ids)){//
            $machine_ids = DB::name('machine')->where('org_id',$user['org_id'])->column('machine_id');
        }else{
            $machine_ids = DB::name('client_domain')->where('user_id',$user['id'])->value('machine_ids');
        }
        if(!empty($params['unique_order_id'])){
            $where['unique_order_id'] = ['like',"%".$params['unique_order_id']."%"];
        }


        $result = DB::name('order')
            ->where($where)
            ->where('machine_id','in',$machine_ids)
            ->order('createtime desc')
            ->select();
        //status0:未支付 status1:已支付 error0:未到到设备返回(不正常) error1:订单正常 error2:订单部分正常 error3:手动补正
        return json_encode($result,JSON_UNESCAPED_UNICODE);
    }

    //退款
    public function refund()
    {
        $user = $this->auth->getUser();
        $params = request()->param();
        $post = [
            'id' => $params['unique_order_id'],
//            'id' => $params['unique_order_id'] = '1001202001120000001407780997',
//            'amount' => $params['amount'] = 137,
            'amount' => $params['amount'],
        ];
//        halt($post);
        $url = "http://www.wakapay.cn/index.php/api/yeepay/refund";
//            $url = "http://192.168.1.144:1161/api/yeepay/pay";
        $return = json_curl($url,$post);
//halt($return);
        $arr = json_decode($return,true);

        if($arr['code'] == 0){
            return json_encode($arr,JSON_UNESCAPED_UNICODE);
        }else{
            $stat_period = strtotime(date('Y-m-d'),time());
            $machine_id = DB::name('order')->where('unique_order_id',$post['id'])->value('machine_id');
            $where = [
                'stat_period' => $stat_period,
                'machine_id' => $machine_id
            ];
            DB::name('order')->where('unique_order_id',$params['unique_order_id'])->update(['error'=>3,'refund_user'=>$user['id'],'refund_amount'=>$params['amount'],'refund_time'=>time()]);//
//            DB::name('machine_day_statistics')->where($where)->setInc('refund_statistics',$post['amount']);//修改设备统计->退款统计金额
            $this->success();
        }
    }

    




}