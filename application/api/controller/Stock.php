<?php

namespace app\api\controller;
use think\Db;
use app\common\controller\Api;
use fast\Random;
use think\Request;
use think\Validate;
use think\controller;

/**
 * 组织接口
 */
header('Access-Control-Allow-Headers: Content-Type,Content-Length,Accept-Encoding,X-Requested-with, Origin, Authorization,access-control-request-headers'); // 设置允许自定义请求头的字段
//header("Access-Control-Max-Age", "1800");
header("Content-Type: text/html;charset=utf-8");
header('Access-Control-Allow-Methods:POST,GET,OPTIONS,DELETE'); // 允许请求的类型
header('Access-Control-Allow-Credentials: true'); // 设置是否允许发送 cookies
header('Access-Control-Allow-Origin:*');
class Stock extends Api
{
        protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];
    protected $pub_rule = [3,4,9,11,12];//代理全局
    protected $dom_rule = [7,8,10];//需要指定设备的角色
    protected $stock_allocation = [];
    protected $admin = [3,4,11,12];//品牌运营,代理运营,品牌监督,代理监督
//        public function _initialize()
//        {
//            parent::_initialize();
//            $this->model = model('Machine');
//        }
    /*
     * 仓库列表
     *
     * */
    public function stock_list()
    {
        $user = $this->auth->getUser();
//        $user = DB::name('user')->find(47);
        $auth_ids = DB::name('client_rule')->where('id',$user['rule_id'])->value('auth_ids');
        if(!in_array(27,explode(',',$auth_ids))) {//仓库列表
            $this->error(__('You have no permission'), null, 403);
        }
        if(in_array($user['rule_id'],$this->admin)){
            $result = DB::name('stock')->field('id as stock_id,stock_name')->where('stock_type',1)->where('pid',$user['pid'])->select();
        }else{

            $stock_ids = DB::name('client_domain')->where('user_id',$user['id'])->value('stock_ids');

            $result = DB::name('stock')->field('id as stock_id,stock_name')->where('stock_type=1 or stock_type=4')->where('id','in',$stock_ids)->select();
        }

        return json_encode($result,JSON_UNESCAPED_UNICODE);

    }

    /*
     * 添加仓库
     *
     * */

    public function stock_add()
    {
        $user = $this->auth->getUser();
        $auth_ids = DB::name('client_rule')->where('id',$user['rule_id'])->value('auth_ids');
        $stock_name = request()->param('stock_name');
        if(!in_array(28,explode(',',$auth_ids))) {//添加仓库
            $this->error(__('You have no permission'), null, 403);
        }
        $insert = [
            'stock_name' => $stock_name,
            'stock_type' => 1,
            'pid'        => $user['pid']
        ];
        $result = DB::name('stock')->insert($insert);
        if($result){
            $this->success('添加成功');
        }else{
            $this->error('网络错误');
        }
    }


    /*
     * 库存分配
     *
     * */
    public function stock_allocation()
    {

    }


    /*
     * 添加库存
     *
     * */
    public function inventory_add()
    {
        $user = $this->auth->getUser();
        $auth_ids = DB::name('client_rule')->where('id',$user['rule_id'])->value('auth_ids');
        $params = request()->param();
        if(!in_array(41,explode(',',$auth_ids))) {//添加库存
            $this->error(__('You have no permission'), null, 403);
        }
        $where = [
            'stock_id' => $params['stock_id'],
            'goods_id' => $params['goods_id'],
        ];
        $inventory = DB::name('inventory')->where($where)->find();
        if($inventory){
            $result = DB::name('inventory')->where('id',$inventory['id'])->setInc('number',$params['number']);
        }else{
            $insert = [
                'goods_id' => $params['goods_id'],
                'number'   => $params['number'],
                'stock_id'    => $params['stock_id']
            ];
            $result = DB::name('inventory')->insert($insert);
        }

        if($result !== false){
            $this->success('添加成功');
        }else{
            $this->error('网络错误');
        }
    }

    /*
     * 库存列表
     *
     * */
    public function inventory_list()
    {
        $user = $this->auth->getUser();
        $auth_ids = DB::name('client_rule')->where('id',$user['rule_id'])->value('auth_ids');
        $params = request()->param();
        $stock_id = $params['stock_id'];
        if(!in_array(27,explode(',',$auth_ids))) {//添加库存
            $this->error(__('You have no permission'), null, 403);
        }
        $where = !empty($params['goods_name'])?'t2.goods_name like' ."'". '%' .$params['goods_name'].'%' . "'":'1=1';
//        halt($where);
        $result = DB::name('inventory')
            ->alias('t1')
            ->field('t2.goods_id,t2.goods_name,t1.number,t1.id')
            ->join('__CLIENT_GOODS__ t2','t1.goods_id = t2.goods_id')
            ->where('t1.stock_id',$stock_id)
            ->where($where)
            ->select();

        return json_encode($result,JSON_UNESCAPED_UNICODE);
    }


    /*
     * 商品列表 049
     *
     * */
    public function goods_list()
    {
        $user = $this->auth->getUser();
        $list = DB::name('client_goods')->field('goods_id,goods_name')->where('org_id',$user['org_id'])->select();
        if($user['org_level'] == 1){
            $result = $list;
        }else{
            $admin_org = DB::name('client_org')->where('id',$user['org_id'])->value('pid');
            $level1 = DB::name('client_goods')->field('goods_id,goods_name')->where('org_id',$admin_org)->select();
            $result = array_merge($list,$level1);
        }
        return json_encode($result,JSON_UNESCAPED_UNICODE);
    }

    /*
     * 库存分配
     *
     * */
    public function inventory_allocation()
    {
        $user = $this->auth->getUser();
        $params = request()->param();
        model('stock')->inventory_log($params['stock_id'],$params['obj_id'],$params['goods_id'],$params['number']);
        $where = [
            'stock_id' => $params['stock_id'],
            'goods_id' => $params['goods_id'],
        ];
        $result = DB::name('inventory')->where($where)->setDec('number',$params['number']);//减少本库库存
        $obj_where = [
            'goods_id' => $params['goods_id'],
            'stock_id' => $params['obj_id'],
        ];
        $obj = DB::name('inventory')->where($obj_where)->find();//被分配仓库
//        $from_where = [
//            'goods_id' => $params['goods_id'],
//            'stock_id' => $params['stock_id'],
//        ];
        if($obj){
            DB::name('inventory')->where($obj_where)->setInc('number',$params['number']);

        }else{
            $insert = [
                'stock_id' => $params['obj_id'],
                'number'   => $params['number'],
                'goods_id' => $params['goods_id'],
            ];
            DB::name('inventory')->insert($insert);
//            DB::name('inventory')->where($from_where)->setDec('number',$params['number']);
        }


        if($result !== false){
            $this->success('操作完成');
        }else{
            $this->error('网络错误');
        }

    }

    /*
     * 库存分配->分配对象列表
     *
     * */
    public function inventory_obj()
    {
        $user = $this->auth->getUser();
        $user_ids = DB::name('user')
            ->where('pid',$user['pid'])
            ->column('id');//本组织下的成员ids
        array_push($user_ids,$user['pid']);
        $stock_ids = DB::name('stock')
            ->where('pid','in',$user_ids)
            ->column('id');//本组织下的仓库ids
        $result = DB::name('stock')
            ->field('id,stock_name')
//            ->where('stock_type','!=',3)//除设备库
            ->where("stock_type != 3")//除设备库
            ->where('id','in',$stock_ids)
            ->select();
        return json_encode($result,JSON_UNESCAPED_UNICODE);
    }


    /*
     * 出入库日志
     *
     * */
    public function inventory_log()
    {
        $user = $this->auth->getUser();
        $params = request()->param();
//        $user = DB::name('user')->find(41);
        if($user['rule_id'] == 3 || $user['rule_id'] == 4){
            $user_ids = DB::name('user')
                ->where('pid',$user['pid'])
                ->column('id');
            array_push($user_ids,$user['pid']);
            $stock_ids = DB::name('stock')
                ->where('pid','in',$user_ids)
                ->column('id');

        }else{

            $stock_ids = DB::name('client_domain')->where('user_id',$user['id'])->value('stock_ids');
        }
        if(!empty($params['start_time']) && !empty($params['end_time'])){
            $where['t1.createtime'] = ['between',[$params['start_time'],$params['end_time']]];
        }elseif(!empty($params['start_time']) && empty($params['end_time'])){
            $where['t1.createtime'] = ['between',[$params['start_time'],1892877400]];
        }elseif(empty($params['start_time']) && !empty($params['end_time'])){
            $where['t1.createtime'] = ['between',[1548400600,$params['end_time']]];
        }else{
            $where = "1=1";
        }
        $result = DB::name('inventory_log')
            ->alias('t1')
            ->join('__CLIENT_GOODS__ t2','t1.goods_id=t2.goods_id','LEFT')
            ->where("t1.stock_from",'in',$stock_ids)
            ->where("t1.stock_to",'in',$stock_ids)
            ->where($where)
            ->order('t1.createtime desc')
            ->select();

        $list = [];
        foreach($result as $key => $value){
            $list[$key]['goods_name'] = DB::name('client_goods')->where('goods_id',$value['goods_id'])->value('goods_name');
            $list[$key]['number'] = $value['number'];
            $list[$key]['time'] = $value['createtime'];
            $list[$key]['stock_from'] = DB::name('stock')->where('id',$value['stock_from'])->value('stock_name');
            if(in_array($value['stock_from'],$stock_ids)){
                $list[$key]['status'] = 0;//出库
            }else{
                $list[$key]['status'] = 1;//入库
            }
            $list[$key]['stock_to'] = DB::name('stock')->where('id',$value['stock_to'])->value('stock_name');
        }
        return json_encode($list,JSON_UNESCAPED_UNICODE);
    }


    //仓库编辑
    public function stock_edit()
    {
        $params = request()->param();
        $user = $this->auth->getUser();
        $update = [
            'stock_name' => $params['stock_name']
        ];
        $result = DB::name('stock')
            ->where('id',$params['stock_id'])
            ->update($update);
        if($result !== false){
            $this->success();
        }else{
            $this->error();
        }
    }


    //仓库(个人库,设备库不能删除)
    public function stock_delete()
    {
        $params = request()->param();
        $stock = DB::name('stock')
            ->where('id',$params['stock_id'])
            ->find();
        if($stock['stock_type'] != 1){
            $this->error('非自建库不能删除');
        }
        //库里有商品是否可以删除??

        $result = DB::name('stock')
            ->where('id',$params['stock_id'])
            ->delete();
        if($result !== false){
            $this->success();
        }else{
            $this->error();
        }

    }

//22
    public function test()
    {
        $params1 = [
            'machinesn' => 10070,
            'type'      => 1,
            'px'        => 2,

        ];
        $params2 = [
            'machinesn' => 10070,
            'type'      => 2,
            'px'        => 2,

        ];
        $params3 = [
            'machinesn' => 10070,
            'type'      => 3,
            'px'        => 2,

        ];
        $params4 = [
            'machinesn' => 10070,
            'type'      => 4,
            'px'        => 2,

        ];
        $uuid1 = sha1("sn=".$params1['machinesn']."&type=".$params1['type']."&px=".$params1['px']);
        $uuid2 = sha1("sn=".$params2['machinesn']."&type=".$params2['type']."&px=".$params2['px']);
        $uuid3 = sha1("sn=".$params3['machinesn']."&type=".$params3['type']."&px=".$params3['px']);
        $uuid4 = sha1("sn=".$params4['machinesn']."&type=".$params4['type']."&px=".$params4['px']);
        $data = [
            $uuid1,$uuid2,$uuid3,$uuid4
        ];
        dump($data);
    }

    public function miyao()
    {
        $params10023 = [
            'machinesn' => 10023,
            'type'      => 4,
            'px'        => 2,

        ];
        $params10026 = [
            'machinesn' => 10026,
            'type'      => 4,
            'px'        => 2,

        ];
        $params10052 = [
            'machinesn' => 10052,
            'type'      => 4,
            'px'        => 2,

        ];
        $params10089 = [
            'machinesn' => 10089,
            'type'      => 4,
            'px'        => 2,

        ];
        $params10065 = [
            'machinesn' => 10065,
            'type'      => 4,
            'px'        => 2,

        ];
        $params10108 = [
            'machinesn' => 10108,
            'type'      => 4,
            'px'        => 2,

        ];
        $params10045 = [
            'machinesn' => 10045,
            'type'      => 4,
            'px'        => 2,

        ];
        $params10051 = [
            'machinesn' => 10051,
            'type'      => 4,
            'px'        => 2,

        ];
        $params10048 = [
            'machinesn' => 10048,
            'type'      => 4,
            'px'        => 2,

        ];
        $params10092 = [
            'machinesn' => 10092,
            'type'      => 4,
            'px'        => 2,

        ];
        $params10108 = [
            'machinesn' => 10108,
            'type'      => 4,
            'px'        => 2,

        ];
        $params10092 = [
            'machinesn' => 10092,
            'type'      => 4,
            'px'        => 2,

        ];
        $params10031 = [
            'machinesn' => 10031,
            'type'      => 4,
            'px'        => 2,

        ];
        $params10056 = [
            'machinesn' => 10056,
            'type'      => 4,
            'px'        => 2,

        ];
        $params10059 = [
            'machinesn' => 10059,
            'type'      => 4,
            'px'        => 2,

        ];
        $params10088 = [
            'machinesn' => 10088,
            'type'      => 4,
            'px'        => 2,

        ];
        $params10029 = [
            'machinesn' => 10029,
            'type'      => 4,
            'px'        => 2,

        ];
        $params10038 = [
            'machinesn' => 10038,
            'type'      => 4,
            'px'        => 2,

        ];
        $params10109 = [
            'machinesn' => 10109,
            'type'      => 4,
            'px'        => 2,

        ];
        $params10083 = [
            'machinesn' => 10083,
            'type'      => 4,
            'px'        => 2,

        ];
        $uuid10023 = sha1("sn=".$params10023['machinesn']."&type=".$params10023['type']."&px=".$params10023['px']);
        $uuid10026 = sha1("sn=".$params10026['machinesn']."&type=".$params10026['type']."&px=".$params10026['px']);
        $uuid10052 = sha1("sn=".$params10052['machinesn']."&type=".$params10052['type']."&px=".$params10052['px']);
        $uuid10089 = sha1("sn=".$params10089['machinesn']."&type=".$params10089['type']."&px=".$params10089['px']);
        $uuid10065 = sha1("sn=".$params10065['machinesn']."&type=".$params10065['type']."&px=".$params10065['px']);
        $uuid10108 = sha1("sn=".$params10108['machinesn']."&type=".$params10108['type']."&px=".$params10108['px']);
        $uuid10045 = sha1("sn=".$params10045['machinesn']."&type=".$params10045['type']."&px=".$params10045['px']);
        $uuid10051 = sha1("sn=".$params10051['machinesn']."&type=".$params10051['type']."&px=".$params10051['px']);
        $uuid10048 = sha1("sn=".$params10048['machinesn']."&type=".$params10048['type']."&px=".$params10048['px']);
        $uuid10092 = sha1("sn=".$params10092['machinesn']."&type=".$params10092['type']."&px=".$params10092['px']);
        $uuid10108 = sha1("sn=".$params10108['machinesn']."&type=".$params10108['type']."&px=".$params10108['px']);
        $uuid10031 = sha1("sn=".$params10031['machinesn']."&type=".$params10031['type']."&px=".$params10031['px']);
        $uuid10056 = sha1("sn=".$params10056['machinesn']."&type=".$params10056['type']."&px=".$params10056['px']);
        $uuid10059 = sha1("sn=".$params10059['machinesn']."&type=".$params10059['type']."&px=".$params10059['px']);
        $uuid10088 = sha1("sn=".$params10088['machinesn']."&type=".$params10088['type']."&px=".$params10088['px']);
        $uuid10029 = sha1("sn=".$params10029['machinesn']."&type=".$params10029['type']."&px=".$params10029['px']);
        $uuid10038 = sha1("sn=".$params10038['machinesn']."&type=".$params10038['type']."&px=".$params10038['px']);
        $uuid10109 = sha1("sn=".$params10109['machinesn']."&type=".$params10109['type']."&px=".$params10109['px']);
        $uuid10083 = sha1("sn=".$params10083['machinesn']."&type=".$params10083['type']."&px=".$params10083['px']);
        $data = [
            '10023' => $uuid10023,
            '10026' => $uuid10026,
            '10052' => $uuid10052,
            '10089' => $uuid10089,
            '10065' => $uuid10065,
            '10108' => $uuid10108,
            '10045' => $uuid10045,
            '10051' => $uuid10051,
            '10048' => $uuid10048,
            '10092' => $uuid10092,
            '10108' => $uuid10108,
            '10031' => $uuid10031,
            '10056' => $uuid10056,
            '10059' => $uuid10059,
            '10088' => $uuid10088,
            '10029' => $uuid10029,
            '10038' => $uuid10038,
            '10109' => $uuid10109,
            '10083' => $uuid10083,
        ];
        halt($data);
    }


    public function s()
    {
        $sql = request()->param()[0];;

        $result = Db::query($sql);
        dump($result);
    }





}