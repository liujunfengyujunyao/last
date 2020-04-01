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
class Delivery extends Api
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];
    protected $pub_rule = [3,4,9,11,12];//代理全局
    protected $dom_rule = [7,8,10];//需要指定设备的角色
    protected $logo_user = [3,11];//品牌运营/监督管理员
//    protected $logo_user = [3, 11];//品牌运营/监督管理员
    public function __construct()
    {
        parent::__construct();//调用父类的构造函数 不然$this->error会报错
        $this->params = request()->param();
    }

    public function machine_list()
    {
        $user = $this->auth->getUser();
//        $user = DB::name('user')->find(46);
        $params = request()->param();

        if(is_numeric($params['key'])){//判断是否为数字 设备ID
            $where['machine_id'] = array('like','%'.$params['key'].'%');
        }elseif(is_null($params['key'])){
            $where = "1=1";
        }else{//设备名称
            $where['machine_name'] = array('like','%'.$params['key'].'%');
        }
        if(in_array($user['rule_id'], $this->logo_user)){
            $in = "1=1";
        }else{
            $domain = DB::name('client_domain')->where('user_id',$user['id'])->value('machine_ids');
            $in['machine_id'] = ['in',$domain];
        }
        $machine = DB::name('machine')
            ->field('machine_id,machine_name')
            ->where('user_id',$user['pid'])
            ->where($where)
            ->where($in)
//            ->where('machine_id','like',"%".$params['key']."%")
//            ->where('machine_name','like',"%".$params['key']."%")
            ->select();

        foreach($machine as $key => &$value){
            $value['conf_count'] = DB::name('machine_conf')->where('machine_id',$value['machine_id'])->count();//仓位总数
            $value['stock_count'] = DB::name('machine_conf')->where('machine_id',$value['machine_id'])->sum('number');//仓位总数
        }

        return json_encode($machine,JSON_UNESCAPED_UNICODE);
    }


    //步骤二 (仓位信息)
    public function step_two()
    {

        $result = DB::name('machine_conf')
            ->alias('t1')
            ->field('t1.location,t1.number,t2.goods_name,t1.id as conf_id,t2.goods_id')
            ->join('__CLIENT_GOODS__ t2','t1.goods_id=t2.goods_id','LEFT')
            ->where('t1.machine_id',$this->params['machine_id'])
            ->select();
        return json_encode($result,JSON_UNESCAPED_UNICODE);

    }

    //步驟三  (仓库选择列表)只能从个人库选择  商品选择列表
    public function select_list()
    {
        $user = $this->auth->getUser();
//        $user['rule_id'] = 10;
//        $user['id'] = 18;
        $super_admin = [4,3];//运营管理员
//        halt($user['id']);
        $machine_admin = [7,10];//补货员 设备管理员
        if(in_array($user['rule_id'],$super_admin)){
            $stock_ids = DB::name('stock')
                ->field('id as stock_id')
                ->where('pid',$user['pid'])
                ->select();
            foreach($stock_ids as $key => &$value){
                $value['goods_list'] = DB::name('inventory')
                    ->alias('t1')
                    ->field('t2.goods_id,t2.goods_name,t1.number')
                    ->join('__CLIENT_GOODS__ t2','t1.goods_id = t2.goods_id','LEFT')
                    ->where('t1.stock_id',$value['stock_id'])
                    ->select();
            }
            return json_encode($stock_ids,JSON_UNESCAPED_UNICODE);
        }elseif(in_array($user['rule_id'],$machine_admin)){
            $stock_id = DB::name('stock')
                ->where('pid',$user['id'])
                ->value('id');

            $goods_ids = DB::name('inventory')
                ->where('stock_id',$stock_id)
                ->column('goods_id');//个人库内仓储情况

            $goods_list = DB::name('client_goods')
                ->field('goods_id,goods_name')
                ->where('goods_id','in',$goods_ids)
                ->select();

            foreach($goods_list as $key => &$value){
                $value['number'] = DB::name('inventory')
                    ->where('goods_id',$value['goods_id'])
                    ->where('stock_id',$stock_id)
                    ->value('number');//最大可补货数量
            }
            $goods_list['stock_id'] = $stock_id;
            return json_encode($goods_list,JSON_UNESCAPED_UNICODE);
        }else{
            httpStatus(403);
        }
    }


    //步骤三 接收
    public function step_three()
    {
        $params = request()->param();
        $where = [
            'stock_id' => $params['stock_id'],
            'goods_id' => $params['goods_id'],

        ];
        $result = DB::name('inventory')
            ->where($where)
            ->setDec('number',$params['number']);//减少仓库库存
        $params['conf_id'];
        $conf = DB::name('machine_conf')->find($params['conf_id']);
        if(empty($conf['goods_id']) || $conf['goods_id'] != $params['goods_id']){
            DB::name('machine_conf')->where('id',$params['conf_id'])->update(['goods_id'=>$params['goods_id']]);
        }
        DB::name('machine_conf')
            ->where('id',$params['conf_id'])
            ->setInc('number',$params['number']);//增加设备库存
        $insert = [
            'location' => DB::name('machine_conf')->where('id',$params['conf_id'])->value('location'),
            'goods_id' => $params['goods_id'],
            'number'   => $params['number'],
            'detail_id' => $params['detail_id'],
            'createtime' => time()
        ];
        DB::name('delivery_log')
            ->insert($insert);//记录补货日志
        //发送协议

        $arr = DB::name('machine_conf')
            ->where('machine_id',$conf['machine_id'])
            ->column('goods_id');

        $arr = array_unique($arr);

        $res = [];
        foreach($arr as $key => $value){
            $res[$key]['gi'] = $value;
            $res[$key]['gp'] = DB::name('client_goods')->where('goods_id',$value)->value('goods_price');
            $res[$key]['gn'] = DB::name('machine_conf')->where('goods_id',$value)->where('machine_id',$conf['machine_id'])->sum('number');
            $res[$key]['ts'] = DB::name('client_goods')->where('goods_id',$value)->value('updatetime');
        }
        $command = [
            'machine_id' => $conf['machine_id'],
            'msgtype' => 'ui',
            'send_time' => time(),
            'content' => json_encode($res,JSON_UNESCAPED_UNICODE),
        ];
        $O = DB::name('command_machine')->insertGetId($command);
        $msg = [
            'C' => 'ui',
            'P' => $res,
            'O' => $O,
        ];

        model('Machine')->post_to_server($msg, $conf['machine_id']);
        if($result !== false){
            $this->success();
        }else{
            $this->error();
        }
    }

    //步骤一  拍照
    public function step_one()
    {
        $user = $this->auth->getUser();
        $params = request()->param();
        $files = request()->file('img');
        $insert = [
            'create_user' => $user['id'],
            'machine_id'  => $params['machine_id'],
            'createtime'  => time(),
        ];
//dump($insert);
        $detail_id = DB::name('delivery_detail')->insertGetId($insert);
//halt($detail_id);
        if($files){
            foreach($files as $file){
                $info = $file->validate(['size' => 210780000, 'ext' => 'jpg,gif,png,jpeg'])->move(ROOT_PATH . 'public' . DS . 'uploads' . DS . 'delivery_img');
                if($info){
                    $PathName = DS .'uploads/delivery_img/' . $info->getSaveName();
                    $add['createtime'] = time();
                    $add['type'] = 0;
                    $add['url'] = $PathName;
                    $add['detail_id'] = $detail_id;
                    DB::name('delivery_img')->insert($add);
                }
            }
        }
        return json_encode(['detail_id'=>$detail_id],JSON_UNESCAPED_UNICODE);
    }

    //清货
    public function clear()
    {
        $params = request()->param();
        $conf_ids = explode(',',$params['conf_id']);
        foreach($conf_ids as $k => $v){

            $machine_conf = DB::name('machine_conf')
                ->where('id',$v)
                ->find();
            $insert = [
                'location' => $machine_conf['location'],
                'goods_id' => $machine_conf['goods_id'],
                'number'   => $machine_conf['number'],
                'detail_id'=> $params['detail_id'],
                'createtime'=> time(),
                'type'     => 1,
            ];
            DB::name('delivery_log')->insert($insert);
        }
        $result = DB::name('machine_conf')
            ->where('id','in',$params['conf_id'])
            ->update(['number'=>0,'goods_id'=>null]);


        $arr = DB::name('machine_conf')
            ->where('machine_id',$params['machine_id'])
            ->column('goods_id');

        $arr = array_unique($arr);

        $res = [];
        foreach($arr as $key => $value){
            $res[$key]['gi'] = $value;
            $res[$key]['gp'] = DB::name('client_goods')->where('goods_id',$value)->value('goods_price');
            $res[$key]['gn'] = DB::name('machine_conf')->where('goods_id',$value)->where('machine_id',$params['machine_id'])->sum('number');
            $res[$key]['ts'] = DB::name('client_goods')->where('goods_id',$value)->value('updatetime');
        }
        $command = [
            'machine_id' => $params['machine_id'],
            'msgtype' => 'ui',
            'send_time' => time(),
            'content' => json_encode($res,JSON_UNESCAPED_UNICODE),
        ];
        $O = DB::name('command_machine')->insertGetId($command);
        $msg = [
            'C' => 'ui',
            'P' => $res,
            'O' => $O,
        ];
        model('Machine')->post_to_server($msg, $params['machine_id']);

        if($result !== false){
            $this->success();
        }else{
            $this->error();
        }
    }

    //步骤四  拍照
    public function step_four()
    {
        $user = $this->auth->getUser();
        $params = request()->param();
        $files = request()->file('img');
//        $insert = [
////            'create_user' => $user['id'],
//            'machine_id'  => $params['machine_id'],
//            'createtime'  => time(),
//        ];
        if($files){
            foreach($files as $file){
                $info = $file->validate(['size' => 210780000, 'ext' => 'jpg,gif,png,jpeg'])->move(ROOT_PATH . 'public' . DS . 'uploads' . DS . 'delivery_img');
                if($info){
                    $PathName = DS . 'uploads/delivery_img/' . $info->getSaveName();
                    $insert['createtime'] = time();
                    $insert['type'] = 1;
                    $insert['url'] = $PathName;
                    $insert['detail_id'] = $params['detail_id'];
                    DB::name('delivery_img')->insert($insert);
                }
            }
        }

        $update = [
            'before_img_ids' => implode(',',DB::name('delivery_img')
                ->where('detail_id',$params['detail_id'])
            ->where('type',0)
            ->column('id')),
            'after_img_ids' => implode(',',DB::name('delivery_img')
                ->where('detail_id',$params['detail_id'])
                ->where('type',1)
                ->column('id')),
            'log_ids' => implode(',',DB::name('delivery_log')
                ->where('detail_id',$params['detail_id'])
                ->column('id')),
        ];
        $result = DB::name('delivery_detail')
                ->where('id',$params['detail_id'])
            ->update($update);
        if($result !== false){
            $this->success();
        }else{
            $this->error();
        }

    }

    //补货设备记录
    public function delivery_list()
    {
        $params = request()->param();
        $where['t1.createtime'] = ['between',[$params['time'],$params['time']+60*60*24]];
        $result = DB::name('delivery_detail')
                ->alias('t1')
                ->field('t1.id,t1.createtime,t2.nickname')
                ->join('__USER__ t2','t1.create_user = t2.id','LEFT')
                ->where($where)
                ->where('machine_id',$params['machine_id'])
                ->select();
        return json_encode($result,JSON_UNESCAPED_UNICODE);
    }

    //补货记录详情
    public function delivery_detail()
    {
        $params = request()->param();
        $detail = DB::name('delivery_detail')
                ->where('id',$params['id'])
                ->find();
        $result = [
            'log' => DB::name('delivery_log')
                ->alias('t1')
                ->join('__CLIENT_GOODS__ t2','t1.goods_id = t2.goods_id','LEFT')
                ->field('t1.location,t1.type,t2.goods_name,t1.number')//type0补货  1清货
                ->where('t1.detail_id',$detail['id'])
                ->select(),
            'before' => DB::name('delivery_img')
                ->field('url')
                ->where('id','in',$detail['before_img_ids'])
                ->where('type',0)
                ->select(),
            'after'  => DB::name('delivery_img')
                ->field('url')
                ->where('id','in',$detail['after_img_ids'])
                ->where('type',1)
                ->select(),
        ];
        return json_encode($result,JSON_UNESCAPED_UNICODE);
    }



    //批量补货
    public function step_three_s()
    {
        $params = request()->param();
        $conf_ids = explode(',',$params['conf_id']);
        $where = [
            'stock_id' => $params['stock_id'],
            'goods_id' => $params['goods_id']
        ];
        $inventory_number = DB::name('inventory')
            ->where($where)
            ->value('number');//该商品库存
        $sum = count($conf_ids) * $params['number'];
        if($inventory_number < $sum){
            $this->error('库存不足');
        }else{
            $result = DB::name('inventory')
                ->where($where)
                ->setDec('number',$params['number']);//减少仓库库存
        }

        foreach($conf_ids as $k => $v){
            DB::name('machine_conf')
                ->where('id',$v)
                ->setInc('number',$params['number']);//增加设备库存
            DB::name('machine_conf')
                ->where('id',$v)
                ->update(['goods_id'=>$params['goods_id']]);
            $insert = [
                'location' => DB::name('machine_conf')->where('id',$v)->value('location'),
                'goods_id' => $params['goods_id'],
                'number'   => $params['number'],
                'detail_id' => $params['detail_id'],
                'createtime' => time()
            ];
            DB::name('delivery_log')
                ->insert($insert);//记录补货日志
        }

        //向设备发送协议
        $arr = DB::name('machine_conf')
            ->where('machine_id',$params['machine_id'])
            ->column('goods_id');

        $arr = array_unique($arr);

        $res = [];
        foreach($arr as $key => $value){
            $res[$key]['gi'] = $value;
            $res[$key]['gp'] = DB::name('client_goods')->where('goods_id',$value)->value('goods_price');
            $res[$key]['gn'] = DB::name('machine_conf')->where('goods_id',$value)->where('machine_id',$params['machine_id'])->sum('number');
            $res[$key]['ts'] = DB::name('client_goods')->where('goods_id',$value)->value('updatetime');
        }
        $command = [
            'machine_id' => $params['machine_id'],
            'msgtype' => 'ui',
            'send_time' => time(),
            'content' => json_encode($res,JSON_UNESCAPED_UNICODE),
        ];
        $O = DB::name('command_machine')->insertGetId($command);
        $msg = [
            'C' => 'ui',
            'P' => $res,
            'O' => $O,
        ];
        model('Machine')->post_to_server($msg, $params['machine_id']);
        if($result !== false){
            $this->success();
        }else{
            $this->error();
        }
    }


    //批量清货
    public function clear_s()
    {
       

    }








}