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
class Statistics extends Api
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];
    protected $pub_rule = [3, 4, 9, 11, 12];//代理全局
    protected $dom_rule = [7, 8, 10];//需要指定设备的角色
    protected $logo_user = [3, 11];//品牌运营/监督管理员
    protected $dl_user = [4, 12];//品牌运营/监督管理员

//        public function _initialize()
//        {
//            parent::_initialize();
//            $this->model = model('Machine');
//        }
    /*
     * 销售统计
     *
     * */
    public function sell_statistics()
    {
        $user = $this->auth->getUser();
//        $user = DB::name('user')->find(9);
        $params = request()->param();
        $stat_period = strtotime(date('Y-m-d',time()));
        $where['stat_period'] = ['between',[$params['start_time'],$params['end_time']]];
//        if($params['where'] == 'yesterday'){
//            $where['stat_period'] = $stat_period - 60*60*24;
//        }elseif($params['where'] == 'month'){
//            $where['stat_period'] = ['between',[strtotime(date("Y-m-d H:i:s",mktime(0, 0 , 0,date("m"),1,date("Y")))),strtotime(date("Y-m-d H:i:s",mktime(23,59,59,date("m"),date("t"),date("Y"))))]];
//        }elseif($params['where'] == 'year'){
//            $where['stat_period'] = ['between',[strtotime(date('Y-01-01')),strtotime(date('Y-12-31'))]];
//        }else{
//            $where['stat_period'] = $stat_period;
//        }
        if(in_array($user['rule_id'], $this->logo_user) || in_array($user['rule_id'], $this->dl_user) || $user['rule_id'] == 9){
            $all_machine = DB::name('machine')->where('user_id',$user['pid'])->column('machine_id');
//        $all_machine = DB::name('machine')->where($where_machine)->column('machine_id');
            $all_income = DB::name('machine_day_statistics')->where('machine_id','in',$all_machine)->sum('online_income');

            if($user['org_level'] == 1){//一级
                $level1_org = $user['org_id' ];
                $level1_machine_ids = DB::name('machine')
                    ->where('org_id',$level1_org)
                    ->select();//直营设备ids
                $level2_org = DB::name('client_org')
                    ->where('pid',$level1_org)
                    ->column('id');//二级组织id集合
                $level2_machine_ids = DB::name('machine')
                    ->where('org_id','in',$level2_org)
                    ->select();//下级设备ids集合
                $org2 = [];//下级收入统计
                foreach($level2_machine_ids as $key => $value){//遍历每一个二级组织
                    $org2[$key]['org_id'] = $value['org_id'];
                    $org2[$key]['machine_id'] = $value['machine_id'];
                    $org2[$key]['machine_name'] = $value['machine_name'];
                    $org2[$key]['income'] = DB::name('machine_day_statistics')->where('machine_id',$value['machine_id'])->where($where)->sum('online_income');
                }

                $org1 = [];//直营收入统计
                foreach($level1_machine_ids as $k => $v){
                    $org1[$k]['machine_id'] = $v['machine_id'];
                    $org1[$k]['machine_name'] = $v['machine_name'];
                    $org1[$k]['income'] = DB::name('machine_day_statistics')->where('machine_id',$v['machine_id'])->where($where)->sum('online_income');

                }
                $org2_list = DB::name('client_org')->field('id as org_id,org_name,admin_id')->where('pid',$level1_org)->select();

                //返回设备分组
                $level1_group = DB::name('group')->where('user_id',$user['pid'])->select();
                $level2_group = [];
                foreach($org2_list as $k => $v){
                    $level2_group[$k]['org_id'] = $v['org_id'];
                    $level2_group[$k]['level2_group'] = DB::name('group')->where('user_id',$v['admin_id'])->select();
                }


                $result = [
                    'level1' => $org1,
                    'level2' => $org2,
                    'org_list' => $org2_list,
                    'level1_group' => $level1_group,
                    'level2_group' => $level2_group,
                    'all_income' => $all_income
                ];

                return json_encode($result,JSON_UNESCAPED_UNICODE);

            }else{
                $machine_ids = DB::name('machine')
                ->where('org_id',$user['org_id'])
//                    ->where($where_machine)
                    ->select();
                $level1_group = DB::name('group')->where('user_id',$user['pid'])->select();
                $org2 = [];
                foreach($machine_ids as $key => $value){
                    $org2[$key]['machine_id'] = $value['machine_id'];
                    $org2[$key]['machine_name'] = $value['machine_name'];
                    $org2[$key]['income'] = DB::name('machine_day_statistics')->where('machine_id',$value['machine_id'])->where($where)->sum('online_income');
                }

                $result = [
                    'level1' => $org2,
                    'level1_group' => $level1_group,
                    'all_income' => $all_income
                ];
                return json_encode($result,JSON_UNESCAPED_UNICODE);
            }
        }else{
            $all_machine = DB::name('client_domain')->where('user_id',$user['id'])->value('machine_ids');
//        $all_machine = DB::name('machine')->where($where_machine)->column('machine_id');
            $all_income = DB::name('machine_day_statistics')->where('machine_id','in',$all_machine)->sum('online_income');
            $org2 = [];
            $machine_ids = DB::name('machine')->where('machine_id','in',$all_machine)->select();
            foreach($machine_ids as $key => $value){
                $org2[$key]['machine_id'] = $value['machine_id'];
                $org2[$key]['machine_name'] = $value['machine_name'];
                $org2[$key]['income'] = DB::name('machine_day_statistics')->where('machine_id',$value['machine_id'])->where($where)->sum('online_income');
            }
            $result = [
                'level1' => $org2,
                'all_income' => $all_income
            ];
            return json_encode($result,JSON_UNESCAPED_UNICODE);
        }


    }


    //退款统计
    public function refund_statistics()
    {
        $user = $this->auth->getUser();
//        $user = DB::name('user')->find(46);
        $params = request()->param();
        $where['refund_time'] = ['between',[$params['start_time'],$params['end_time']]];
        if(in_array($user['rule_id'], $this->logo_user) || in_array($user['rule_id'], $this->dl_user )|| $user['rule_id'] == 9){
            $all_machine = DB::name('machine')->where('user_id',$user['pid'])->column('machine_id');
            $all_refund = DB::name('order')->where('machine_id','in',$all_machine)->sum('refund_amount');
            if($user['org_level'] == 1){//一级
                $level1_org = $user['org_id' ];
                $level1_machine_ids = DB::name('machine')
                    ->where('org_id',$level1_org)
                    ->select();//直营设备ids
                $level2_org = DB::name('client_org')
                    ->where('pid',$level1_org)
                    ->column('id');//二级组织id集合
                $level2_machine_ids = DB::name('machine')
                    ->where('org_id','in',$level2_org)
                    ->select();//下级设备ids集合
                $org2 = [];//下级收入统计
                foreach($level2_machine_ids as $key => $value){//遍历每一个二级组织
                    $org2[$key]['org_id'] = $value['org_id'];
                    $org2[$key]['machine_id'] = $value['machine_id'];
                    $org2[$key]['machine_name'] = $value['machine_name'];
                    $org2[$key]['refund'] = DB::name('order')->where('machine_id',$value['machine_id'])->where($where)->sum('refund_amount');
                }
                $org1 = [];//直营收入统计
                foreach($level1_machine_ids as $k => $v){
                    $org1[$k]['machine_id'] = $v['machine_id'];
                    $org1[$k]['machine_name'] = $v['machine_name'];
                    $org1[$k]['refund'] = DB::name('order')->where('machine_id',$v['machine_id'])->where($where)->sum('refund_amount');
                }

                $org2_list = DB::name('client_org')->field('id as org_id,org_name,admin_id')->where('pid',$level1_org)->select();

                //返回设备分组
//            $level1_group = DB::name('group')->where('user_id',$user['pid'])->value('machine_ids');
                $level1_group = DB::name('group')->where('user_id',$user['pid'])->select();
                $level2_group = [];
                foreach($org2_list as $k => $v){
//
                    $level2_group[$k]['org_id'] = $v['org_id'];
//                $level2_group[$k]['level2_group'] = DB::name('group')->where('user_id',$v['admin_id'])->value('machine_ids');
                    $level2_group[$k]['level2_group'] = DB::name('group')->where('user_id',$v['admin_id'])->select();
                }
                $result = [
                    'level1' => $org1,//自营
                    'level2' => $org2,//下级
                    'org_list' => $org2_list,//下级代理列表
                    'level1_group' => $level1_group,//
                    'level2_group' => $level2_group,//
                    'all_refund' => $all_refund,
                ];
//            halt($result);
                return json_encode($result,JSON_UNESCAPED_UNICODE);
            }else{

                $machine_ids = DB::name('machine')
                    ->where('org_id',$user['org_id'])
                    ->select();

                $org2 = [];
                foreach($machine_ids as $key => $value){
                    $org2[$key]['machine_id'] = $value['machine_id'];
                    $org2[$key]['machine_name'] = $value['machine_name'];
                    $org2[$key]['refund'] = DB::name('order')->where('machine_id',$value['machine_id'])->where($where)->sum('refund_amount');
                }

                $level1_group = DB::name('group')->where('user_id',$user['pid'])->select();
                $result = [
                    'level1' => $org2,
                    'level1_group' => $level1_group,
                    'all_refund' => $all_refund,
                ];
                return json_encode($result,JSON_UNESCAPED_UNICODE);
            }
        }else{

            $machine_ids = DB::name('client_domain')
                ->where('user_id',$user['id'])
                ->value('machine_ids');
            $org2 = [];
            $machines = DB::name('machine')->where('machine_id','in',$machine_ids)->select();
            foreach($machines as $key => $value){
                $org2[$key]['machine_id'] = $value['machine_id'];
                $org2[$key]['machine_name'] = $value['machine_name'];
                $org2[$key]['refund'] = DB::name('order')->where('machine_id',$value['machine_id'])->where($where)->sum('refund_amount');
            }
            $all_refund = DB::name('order')->where($where)->where('machine_id','in',$machine_ids)->sum('refund_amount');
            $result = [
                'level1' => $org2,
                'all_refund' => $all_refund
            ];
            return json_encode($result,JSON_UNESCAPED_UNICODE);
        }

    }

    //商品销售排行
    public function goods_statistics()
    {
        $user = $this->auth->getUser();
//        $user = DB::name('user')->find(9);
        $params = request()->param();
        $machine_ids = DB::name('machine')->where('org_id',$user['org_id'])->column('machine_id');
        if(!empty($params['machine_id'])){
            $machine_ids = $params['machine_id'];
        }
        $where['time'] = ['between',[$params['start_time'],$params['end_time']]];
        $where['machine_id'] = ['in',$machine_ids];
        if(in_array($user['rule_id'], $this->logo_user) || in_array($user['rule_id'], $this->dl_user)|| $user['rule_id'] == 9){
//            if($user['org_level'] == 1){//一级
                $goods_ids = DB::name('client_goods')
                    ->where('org_id',$user['org_id'])
                    ->select();//一级品牌商的商品集合
                $result = [];
                foreach ($goods_ids as $key => $value){
//                $result[$key]['goods_id'] = $value['goods_id'];
                    $result[$key]['goods_name'] = $value['goods_name'];
                    $result[$key]['number'] = DB::name('goods_statistics')->where($where)->where('goods_id',$value['goods_id'])->sum('number');
                    $result[$key]['amount'] = DB::name('goods_statistics')->where($where)->where('goods_id',$value['goods_id'])->sum('amount');
                }
//            }
            //二位数组排序  根据result . number
        }else{
            $machine_ids = DB::name('client_domain')->where('user_id',$user['id'])->value('machine_ids');
            if(!empty($params['machine_id'])){
                $machine_ids = $params['machine_id'];
            }
            $ids = array_unique(DB::name('machine_conf')->where('machine_id','in',$machine_ids)->column('goods_id'));
            $goods_ids = DB::name('client_goods')
                ->where('goods_id','in',$ids)
                ->select();
            $result = [];
            foreach ($goods_ids as $key => $value){
//                $result[$key]['goods_id'] = $value['goods_id'];
                $result[$key]['goods_name'] = $value['goods_name'];
                $result[$key]['number'] = DB::name('goods_statistics')->where($where)->where('goods_id',$value['goods_id'])->sum('number');
                $result[$key]['amount'] = DB::name('goods_statistics')->where($where)->where('goods_id',$value['goods_id'])->sum('amount');
            }

        }
        $last_names = array_column($result,'number');
        array_multisort($last_names,SORT_DESC,$result);
        return json_encode($result,JSON_UNESCAPED_UNICODE);

    }


    //每日生成设备统计
    public function machine_statistics_create()
    {
        $machine = DB::name('machine')->column('machine_id');
        $insert = [];
        $stat_period = strtotime(date('Y-m-d'),time());
        foreach($machine as $key => $value){
            $insert[$key]['machine_id'] = $value;
            $insert[$key]['stat_period'] = $stat_period;
            $insert[$key]['online_income'] = 0;
            $insert[$key]['offline_income'] = 0;
            $insert[$key]['gift_count'] = 0;
        }
        DB::name('machine_day_statistics')->insertAll($insert);
    }

}