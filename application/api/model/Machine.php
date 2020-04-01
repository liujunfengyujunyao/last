<?php

namespace app\api\model;

use think\Model;
use think\Db;
use app\common\controller\Api;
class Machine extends Model
{

    

    

    // 表名
    protected $name = 'machine_check';
    
    // 自动写入时间戳字段
    protected $autoWriteTimestamp = 'int';

    // 定义时间戳字段名
    protected $createTime = 'createtime';
    protected $updateTime = false;
    protected $deleteTime = false;

    // 追加属性
    protected $append = [

    ];

    //向服务器发送数据
    public function post_to_server($msg,$machine_id){

        $data = array(
            'msg'=>$msg,
            'msgtype'=>'send_message',
            'machinesn'=>intval($machine_id),
        );
        $url = 'https://www.goldenbrother.cn:23233/account_server';
        $url = 'http://www.goldenbrother.cn:33333/account_server';
        //halt($data);
        $res = post_curls($url,$data);
//        post_curls
//        return $res;
    }


    //轮询请求状态  type:0返回数组，1返回json数组
    public function check_status($commandid){
        if(!$commandid){
            $error = array(
                'status'=>0,
                'msg'=>'参数错误',
            );
            return $error;
        }
        $data = array(
            'status'=>0,
            'msg'=>'设备无响应'
        );
        for($x=0; $x<=2; $x++){//轮询查找是否返回成功
            //查询出对应的command
            $command = DB::name('command_machine')->where(['commandid'=>$commandid])->find();
            if ($command['status'] == 1) {
                //status=1为执行成功
                //成功之后操作
//                switch ($command['msgtype']) {
//                    case 'change_priority':
//                        Db::name('machine')->where(['machine_id'=>$command['machine_id']])->setField('priority',$command['content']);
//                        break;
//                    case 'open_room':
//                        # code...
//                        break;
//                    case 'update_firmware':
//                        # code...
//                        break;
//                    case 'get_room_status':
//                        # code...
//                        break;
//                }
                $data = ['status'=>1,'msg'=>'操作成功'];
            }elseif($command['status'] == 0){
                sleep(2);//延迟2s
            }
        }
        return $data;
    }
    public function test()
    {
        return 11;
    }

    function api_auth($userid,$authid)
    {
        $user = \think\Db::name('user')->where('id',$userid)->find();
        $auth_ids = \think\Db::name('client_rule')->where('id',$user['rule_id'])->value('auth_ids');
        $org = \think\Db::name('client_org')->field('goods_price,add_goods,org_level')->where('id',$user['org_id'])->find();
        if(!in_array($authid,explode(',',$auth_ids))) {//添加商品分类
            return ['code'=>0];
        }else{
            return ['code'=>1];
        }
    }
    

    







}
