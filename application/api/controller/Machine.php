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
class Machine extends Api
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
     * 设备列表
     *
     * */
    public function machine_list()
    {
        $token = request()->param('token');
        $user = DB::name('user')->where('token', $token)->find();
//        $user = DB::name('user')->find(46);
        $auth_ids = DB::name('client_rule')->where('id', $user['rule_id'])->value('auth_ids');//权限集合
        $auth_arr = explode(',', $auth_ids);

        if (!$user) {
            $this->error(__('登陆超时'), null, 301);//重定向
        } elseif (!in_array(12, $auth_arr)) {//设备列表
            $this->error(__('You have no permission'), null, 403);
        } else {

            if (in_array($user['rule_id'], $this->logo_user)) {//品牌运营/监督管理员
                $admin_ids = DB::name('user')->where("pid = $user[pid] && rule_id = 2")->column('id');

//                array_push($admin_ids,$user['pid']);
//                    array_push($admin_ids,$user['pid']);//将自营加入
                $machine = DB::name('machine')->field('machine_id,machine_name,status,is_online,org_id,logo_id,video_id,address')->where('user_id', 'in', $admin_ids)->select();
//                dump($admin_ids);
//                halt($machine);
                $result = [];
                foreach ($machine as $key => $value) {
                    $result[$key]['is_online'] = $value['is_online'] == 1 ? '在线' : '离线';
                    $result[$key]['status'] = $value['status'] == 0 ? '正常' : '故障';
                    $result[$key]['machine_name'] = $value['machine_name'];
                    $result[$key]['address'] = $value['address'];
                    $result[$key]['org_id'] = $value['org_id'];
                    $result[$key]['machine_id'] = $value['machine_id'];
                    $result[$key]['logo'] = DB::name('material')->where('id',$value['logo_id'])->find();
                    $result[$key]['video'] = DB::name('material')->where('id',$value['video_id'])->find();

                }
                $list = [];
                foreach ($result as $key => $value) {

                    $org_name = DB::name('client_org')->where('id', $value['org_id'])->value('org_name');
//                        $list[$value['org_id']][] = $value;
                    $list[$org_name][] = $value;
                }

                $self = DB::name('machine')->field('machine_id,machine_name,status,is_online,org_id,logo_id,video_id,address')->where('user_id', $user['pid'])->select();

                $self_arr = [];
                foreach ($self as $key => $value) {
//                    halt($value);
                    $self_arr[$key]['is_online'] = $value['is_online'] == 1 ? '在线' : '离线';
                    $self_arr[$key]['status'] = $value['status'] == 0 ? '正常' : '故障';
                    $self_arr[$key]['machine_name'] = $value['machine_name'];
                    $self_arr[$key]['address'] = $value['address'];
                    $self_arr[$key]['org_id'] = $value['org_id'];
                    $self_arr[$key]['machine_id'] = $value['machine_id'];
                    $self_arr[$key]['logo'] = DB::name('material')->where('id',$value['logo_id'])->find();
                    $self_arr[$key]['video'] = DB::name('material')->where('id',$value['video_id'])->find();
                }
                $list['自营'] = $self_arr;
                unset($list['']);

            } elseif (in_array($user['rule_id'], $this->pub_rule)) {//组织全局

                $machine = DB::name('machine')->field('machine_id,machine_name,status,is_online,org_id,logo_id,video_id,address')->where('user_id', $user['pid'])->select();
                $list = [];
                $result = [];
                foreach ($machine as $key => $value) {
                    $list[$key]['is_online'] = $value['is_online'] == 1 ? '在线' : '离线';
                    $list[$key]['status'] = $value['status'] == 0 ? '正常' : '故障';
                    $list[$key]['machine_name'] = $value['machine_name'];
                    $list[$key]['org_id'] = $value['org_id'];
                    $list[$key]['machine_id'] = $value['machine_id'];
                    $list[$key]['logo'] = DB::name('material')->where('id',$value['logo_id'])->find();
                    $list[$key]['video'] = DB::name('material')->where('id',$value['video_id'])->find();
                }
                $result['自营'] = $list;
                $list = $result;
            } else {//组织局部
                $domain = DB::name('client_domain')->where('user_id', $user['id'])->value('machine_ids');
                $machine = DB::name('machine')->field('machine_id,machine_name,status,is_online,org_id,logo_id,video_id,address')->where('machine_id', 'in', $domain)->select();
                $list = [];
                $result = [];
                foreach ($machine as $key => $value) {
                    $list[$key]['is_online'] = $value['is_online'] == 1 ? '在线' : '离线';
                    $list[$key]['status'] = $value['status'] == 0 ? '正常' : '故障';
                    $list[$key]['machine_name'] = $value['machine_name'];
                    $list[$key]['address'] = $value['address'];
                    $list[$key]['org_id'] = $value['org_id'];
                    $list[$key]['machine_id'] = $value['machine_id'];
                    $list[$key]['logo'] = DB::name('material')->where('id',$value['logo_id'])->find();
                    $list[$key]['video'] = DB::name('material')->where('id',$value['video_id'])->find();
                }
                $result['自营'] = $list;
                $list = $result;
            }

            if(!empty(request()->param('where'))){
                foreach($list as $key => $value){
                    $in = 0;
                    foreach($value as $k => $v){
                        if(strpos($v['machine_name'], request()->param('where')) !== false || strpos($v['address'], request()->param('where')) !== false){
                            $arr[$key][$in] = $v;
                            $in++;
                        }
                    }
                }
                $list = $arr;
            }

            //如果数组索引存在"自营" 那么除自营外 其它设备都不能点击
            return json_encode($list, JSON_UNESCAPED_UNICODE);
        }
    }
    /*
     * 设备设置
     *
     * */

    public function machine_option()
    {
        $user = $this->auth->getUser();
        $auth_ids = DB::name('client_rule')->where('id', $user['rule_id'])->value('auth_ids');
        if (!in_array(11, explode(',', $auth_ids))) {//设备管理
            $this->error(__('You have no permission'), null, 403);
        }
        $machine_auth = [14, 40, 16, 17, 15, 18, 19];//设备编辑,货道设置,设备出货,解绑设备,仓位配置,修改设备状态,重启设备
        $where = array_intersect($machine_auth, explode(',', $auth_ids));//取出相同元素
//            halt($where);
        $result = DB::name('client_auth')->field('id as auth_id,auth_name')->where('id', 'in', $where)->select();
        return json_encode($result, JSON_UNESCAPED_UNICODE);
    }


    /*
     * 设备编辑页面
     *
     * */
    public function machine_edit_list()
    {
        $machine_id = request()->param('machine_id');
        $result = DB::name('machine')
            ->field('machine_id,machine_name,address,address2,comment,video_id,logo_id')
            ->where('machine_id', $machine_id)
            ->find();
        $result['video_url'] = DB::name('material')->where('id',$result['video_id'])->value('url');
        $result['logo_url'] = DB::name('material')->where('id',$result['logo_id'])->value('url');
        return json_encode($result, JSON_UNESCAPED_UNICODE);
    }

    /*
     * 设备编辑保存
     *
     * */
    public function machine_edit_save()
    {
        $params = request()->param();
        $user = $this->auth->getUser();
        $auth_ids = DB::name('client_rule')->where('id', $user['rule_id'])->value('auth_ids');
        if (!in_array(34, explode(',', $auth_ids))) {//设备编辑
            $this->error(__('You have no permission'), null, 403);
        }
        $save = $params;
        unset($save['token']);

        $result = DB::name('machine')->where('machine_id', $params['machine_id'])->update($save);
        if ($result !== false) {
            $this->success('保存成功');
        } else {
            $this->error('网络错误');
        }

    }

    /*
     *解绑设备
     *
     * */

    public function machine_delete()
    {
        $machine_id = request()->param('machine_id');
        $user = $this->auth->getUser();
        $auth_ids = DB::name('client_rule')->where('id', $user['rule_id'])->value('auth_ids');
        if (!in_array(15, explode(',', $auth_ids))) {//设备编辑
            $this->error(__('You have no permission'), null, 403);
        }
        $machine = DB::name('machine')->where('machine_id', $machine_id)->find();
        $org = DB::name('client_org')->where('id', $machine['org_id'])->find();

        if ($org['org_level'] == 1) {//删除
            DB::name('machine')->where('machine_id',$machine_id)->update(['user_id'=>0]);
//            $result = DB::name('machine')->where('machine_id', $machine_id)->delete();
        } else {//回到品牌设备列表

            Db::startTrans();
            $level_adminid = DB::name('client_org')->where('id', $org['pid'])->value('admin_id');//品牌超级管理员ID
            DB::table('fa_machine')->where('machine_id', $machine_id)->update(['org_id' => $org['pid'], 'user_id' => $level_adminid]);
            DB::table('fa_stock')->where('machine_id', $machine_id)->update(['pid' => $level_adminid]);//修改库存pid 用于库存管理->仓库->库存分配->选择对象

            $user_ids = DB::table('fa_user')->where('pid', $org['admin_id'])->column('id');
            $domain = DB::table('fa_client_domain')->where('user_id', 'in', $user_ids)->select();
            $save = [];
            foreach ($domain as $key => $value) {//重组machine_ids
                if (in_array($machine_id, explode(',', $value['machine_ids']))) {
                    $save['machine_ids'] = implode(',', delByValue(explode(',', $value['machine_ids']), $machine_id));//删除数组中的元素
                    $result = DB::table('fa_client_domain')->where('user_id', $value['user_id'])->update($save);
                }
            }
            $result = true;
        }
        if ($result) {
            Db::commit();
            $this->success('解绑成功');//code:1
        } else {
            Db::rollback();
            $this->error('解绑失败');//code:0
        }

    }


    /*
     * 设备注册
     *
     * */
    public function machine_binding()
    {
        $user = $this->auth->getUser();
        $auth_ids = DB::name('client_rule')->where('id', $user['rule_id'])->value('auth_ids');
        if (!in_array(13, explode(',', $auth_ids))) {//设备注册
            $this->error(__('You have no permission'), null, 403);
        }
        $params = request()->param();
        $machine = DB::name('machine')
            ->where('uuid', $params['uuid'])
            ->find();
        if (!$machine) {
            $this->error('设备不存在');
        } elseif ($machine['user_id'] != 0) {
            $this->error('请勿重复绑定');
        } else {
            $result = DB::name('machine')
                ->where('uuid', $params['uuid'])
                ->update(['user_id' => $user['pid'], 'binding_time' => time(),'org_id'=>$user['org_id']]);

            $insert = [
                'stock_type' => 3,//设备库
                'pid' => $user['pid'],
                'machine_id' => $machine['machine_id'],
                'stock_name' => $machine['machine_id'] . '设备库',
            ];
            DB::name('stock')->insert($insert);
        }
        if ($result !== false) {
            $this->success('绑定成功');
        } else {
            $this->error('网络错误');
        }


    }

    /*
     * 设备出货  付款后发出开仓指令 设备未返回成功的log
     *
     * */

    public function open_space()
    {

    }

    /*
     * 设备重启
     *
     * */
    public function machine_restart()
    {
        $params = request()->param();

        $user = $this->auth->getUser();
        $auth_ids = DB::name('client_rule')->where('id', $user['rule_id'])->value('auth_ids');
        if (!in_array(19, explode(',', $auth_ids))) {//设备重启
            $this->error(__('You have no permission'), null, 403);
        }

        $machine = DB::name('machine')->where('machine_id', $params['machine_id'])->find();

        if ($machine['is_online'] == 0) {
            $this->error(__('设备离线'));
        }

        $command_insert = [
            'machine_id' => $machine['machine_id'],
            'msgtype' => 'rb',
            'send_time' => time(),
            'content' => '重启',
        ];
        $command_id = DB::name('command_machine')->insertGetId($command_insert);
        $json = array(
            'C' => 'rb',
            'O' => $command_id
        );
        model('Machine')->post_to_server($json, $machine['machine_id']);
        $status = model('Machine')->check_status($command_id);
        if ($status['status'] == 1) {
            $this->success("设备重启成功", null, ['O' => $command_id]);//设备重启成功,返回数据:{"id":"1"}
//                $this->success("设备重启成功");
        } else {
            $this->error(__('设备重启失败'));
        }
    }

    /*
     * 修改设备状态
     *
     * */
    public function machine_status()
    {
        $params = request()->param();

        $user = $this->auth->getUser();
        $auth_ids = DB::name('client_rule')->where('id', $user['rule_id'])->value('auth_ids');
        if (!in_array(18, explode(',', $auth_ids))) {//设备注册
            $this->error(__('You have no permission'), null, 403);
        }

        $result = DB::name('machine')->where('machine_id', $params['machine_id'])->update(['status' => $params['status']]);//0正常 1故障
        if ($result !== false) {
            $this->success('修改成功');
        } else {
            $this->error('修改失败');
        }

    }


    public function test()
    {
        $data = model('Machine')->test();
        echo $data;
    }

    /*
 * 测试设备仓位编辑
 *
 *
 * 新增的仓位前端需要保存在一个新的空数组里 包括第一次添加仓位
 * 或将添加仓位与修改坐标分开
 * */
    public function machine_conf_add()
    {
//        halt($this->params);

        $params = request()->param();
        $insert = [];
        $time = time();
        $max_location = DB::name('machine_conf')
            ->where('machine_id', $params['machine_id'])
            ->max('location');//0or最大仓位号
        foreach ($params['new'] as $key => $value) {
            $insert['location'] = $key + 1 + $max_location;
            $insert['machine_id'] = $params['machine_id'];
            $insert['create_time'] = $insert['update_time'] = $time;
            $insert['lat'] = $value['lat'];
            $insert['lng'] = $value['lng'];
            $result = DB::name('machine_conf')->insert($insert);
        }
        if ($result) {
            $this->success();
        } else {
            $this->error();
        }
    }

    /*
     * 仓位配置列表
     *
     * */
    public function machine_conf_list()
    {
        $params = request()->param();
//        $params = ['machine_id'=>10001];
        $list = DB::name('machine_conf')
            ->field('id,lat,lng')
            ->where('machine_id', $params['machine_id'])
            ->select();
        return json_encode($list, JSON_UNESCAPED_UNICODE);
    }

    /*
     * 修改仓位配置
     *
     * */
    public function machine_conf_edit()
    {
        $params = request()->param();
        $save = $params['save'];
//        $save = [
//            '11' => [
//                'lat' => 10,
//                'lng' => 10,
//            ],
//            '16' => [
//                'lat' => 20,
//                'lng' => 20
//            ],
//        ];
//        halt(json_encode($save,JSON_UNESCAPED_UNICODE));
        foreach ($save as $key => $value) {

            $result = DB::name('machine_conf')
                ->where('id', $key)
                ->update(['lat' => $value['lat'], 'lng' => $value['lng']]);
        }
        if ($result !== false) {
            $this->success();
        } else {
            $this->error();
        }

    }

    //审核设备列表
    public function check_list()
    {
        $result = DB::name('machine_check')
            ->select();
        return json_encode($result);
    }

    //设备审核通过
    public function check_success()
    {
        $params = request()->param();
        $check = DB::name('machine_check')
            ->where('id', $params['id'])
            ->find();
        if ($check['type_id'] == 10) {
            $nick = '盲盒';
        }

        $insert = [
            'uuid' => $check['uuid'],
            'px' => $check['px'],
            'createtime' => time(),
            'type_id' => $check['type_id'],
            'machine_name' => $nick,
        ];
        $time = time();
        $insert['createtime'] = $insert['updatetime'] = $time;
        $result = DB::name('machine')->insertGetId($insert);
        $time = time();
        $conf = [
            [
                'machine_id' => $result,
                'location' => 1,
                'lat' => 400,
                'lng' => 5,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 2,
                'lat' => 380,
                'lng' => 5,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 3,
                'lat' => 350,
                'lng' => 5,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 4,
                'lat' => 310,
                'lng' => 5,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 5,
                'lat' => 285,
                'lng' => 5,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 6,
                'lat' => 240,
                'lng' => 5,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 7,
                'lat' => 215,
                'lng' => 5,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 8,
                'lat' => 180,
                'lng' => 5,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 9,
                'lat' => 145,
                'lng' => 5,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 10,
                'lat' => 115,
                'lng' => 5,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 11,
                'lat' => 400,
                'lng' => 5,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 12,
                'lat' => 380,
                'lng' => 100,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 13,
                'lat' => 350,
                'lng' => 100,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 14,
                'lat' => 310,
                'lng' => 100,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 15,
                'lat' => 285,
                'lng' => 100,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 16,
                'lat' => 240,
                'lng' => 100,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 17,
                'lat' => 215,
                'lng' => 100,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 18,
                'lat' => 180,
                'lng' => 100,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 19,
                'lat' => 145,
                'lng' => 100,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 20,
                'lat' => 115,
                'lng' => 100,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 21,
                'lat' => 400,
                'lng' => 172,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 22,
                'lat' => 380,
                'lng' => 172,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 23,
                'lat' => 350,
                'lng' => 172,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 24,
                'lat' => 310,
                'lng' => 172,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 25,
                'lat' => 285,
                'lng' => 172,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 26,
                'lat' => 240,
                'lng' => 172,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 27,
                'lat' => 215,
                'lng' => 172,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 28,
                'lat' => 180,
                'lng' => 172,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 29,
                'lat' => 145,
                'lng' => 172,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 30,
                'lat' => 115,
                'lng' => 172,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 31,
                'lat' => 400,
                'lng' => 271,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 32,
                'lat' => 380,
                'lng' => 271,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 33,
                'lat' => 350,
                'lng' => 271,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 34,
                'lat' => 310,
                'lng' => 271,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 35,
                'lat' => 285,
                'lng' => 271,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 36,
                'lat' => 240,
                'lng' => 271,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 37,
                'lat' => 215,
                'lng' => 271,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 38,
                'lat' => 180,
                'lng' => 271,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 39,
                'lat' => 145,
                'lng' => 271,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 40,
                'lat' => 115,
                'lng' => 271,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 41,
                'lat' => 400,
                'lng' => 368,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 42,
                'lat' => 380,
                'lng' => 368,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 43,
                'lat' => 350,
                'lng' => 368,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 44,
                'lat' => 310,
                'lng' => 368,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 45,
                'lat' => 285,
                'lng' => 368,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 46,
                'lat' => 240,
                'lng' => 368,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 47,
                'lat' => 215,
                'lng' => 368,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 48,
                'lat' => 180,
                'lng' => 368,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 49,
                'lat' => 145,
                'lng' => 368,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 50,
                'lat' => 115,
                'lng' => 368,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 51,
                'lat' => 400,
                'lng' => 467,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 52,
                'lat' => 380,
                'lng' => 467,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 53,
                'lat' => 350,
                'lng' => 467,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 54,
                'lat' => 310,
                'lng' => 467,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 55,
                'lat' => 285,
                'lng' => 467,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 56,
                'lat' => 240,
                'lng' => 467,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 57,
                'lat' => 215,
                'lng' => 467,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 58,
                'lat' => 180,
                'lng' => 467,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 59,
                'lat' => 145,
                'lng' => 467,
                'create_time' => $time,
            ],
            [
                'machine_id' => $result,
                'location' => 60,
                'lat' => 115,
                'lng' => 467,
                'create_time' => $time,
            ],
        ];

        DB::name('machine_conf')->insertAll($conf);


        if ($result !== false) {
            $machine_statistics = [
                'machine_id' => $result,
                'stat_period' => strtotime(date('Y-m-d', time())),
            ];
            DB::name('machine_day_statistics')->insert($machine_statistics);
            DB::name('machine_check')->where('id', $check['id'])->delete();
            $this->success('审核通过');
        } else {
            $this->error('服务器错误');
        }
    }

    //审核驳回
    public function check_error()
    {
        $params = request()->param();
        $result = DB::name('machine_check')
            ->where('id',$params['id'])
            ->delete();
        if($result !== false){
            $this->success('驳回成功');
        }else{
            $this->error('服务器错误');
        }
    }

    public function check_test()
    {
        $uuid = "18b960f7-4f3d-42b3-90f5-34937b92529a";
        $check = DB::name('machine_check')
            ->where('uuid', $uuid)
            ->find();
        if ($check['type_id'] == 10) {
            $nick = '盲盒';
        }

        $insert = [
            'uuid' => $check['uuid'],
            'px' => $check['px'],
            'createtime' => time(),
            'type_id' => $check['type_id'],
            'machine_name' => $nick,
        ];
        $time = time();
        $insert['createtime'] = $insert['updatetime'] = $time;
        $result = DB::name('machine')->insertGetId($insert);
        if ($result !== false) {
            $machine_statistics = [
                'machine_id' => $result,
                'stat_period' => strtotime(date('Y-m-d', time())),
            ];
            DB::name('machine_day_statistics')->insert($machine_statistics);
            DB::name('machine_check')->where('id', $check['id'])->delete();
            $this->success('审核通过');
        } else {
            $this->error('服务器错误');
        }
    }

    //绑定设备
    public function binding()
    {

        $params = request()->param();

        $user = $this->auth->getUser();
        if(!$user){
            $this->error('token失效');
        }
        $uuid = $params['uuid'];
        $machine = DB::name('machine')
            ->where('uuid',$uuid)
            ->find();
        if(!$machine){
            $this->error('未找到该设备');
        }
        if($machine['user_id']){
            $this->error('重复绑定');
        }
        $update = [
            'user_id' => $user['pid'],
            'binding_time' => time(),
        ];
        DB::name('machine')->where('uuid',$uuid)->update($update);
        $this->success();
    }


    //设备分配
    public function machine_allocation()
    {
        $params = request()->param();
        $user = $this->auth->getUser();
        $machine = DB::name('machine')
            ->where('machine_id',$params['machine_id'])
            ->find();
//halt($machine['user_id']);
        if($machine['user_id'] != $user['pid'] || $machine['user_id'] == 0){
            $this->error('设备信息错误');
        }
        $machine_conf = DB::name('machine_conf')
            ->where('machine_id',$machine['machine_id'])
            ->sum('number');
        if($machine_conf > 0){
            $this->error('设备内商品必须为空');
        }
        $update = [
            'updatetime' => time(),
            'user_id' => DB::name('client_org')->where('id',$params['org_id'])->value('admin_id'),
            'org_id' => $params['org_id'],
        ];
        //将设备配置的商品全部清空
        DB::name('machine_conf')->where('machine_id',$params['machine_id'])->update(['goods_id'=>null,'update_time'=>time()]);
        $result = DB::name('machine')->where('machine_id',$machine['machine_id'])->update($update);
        if($result !== false){
            $this->success();
        }else{
            $this->error();
        }
        //org_id

    }

    //增加素材库素材
    public function material_add()
    {
        $params = request()->param();//type 1=logo_id  2=video_id
        $file = request()->file('file');

        if(!empty($file)){
            $path = $file->validate(['size' => 210780000, 'ext' => 'jpg,gif,png,jpeg,mp4'])->move(ROOT_PATH . 'public' . DS . 'material');
            $url = DS . 'material' . DS . $path->getSaveName();
            $insert = [
                'url' => $url,
                'create_time' => time(),
                'update_time' => time(),
                'type'        => $params['type'],
            ];
            $id = DB::name('material')->insertGetId($insert);
            $url = DB::name('material')->where('id',$id)->value('url');
        }else{
            $this->error();
        }

        if($params['type'] == 1){
            $result = [
                'logo_id' => $id,
                'url' => $url
            ];
        }else{
            $result = [
                'video_id' => $id,
                'url' => $url
            ];
        }
        return json_encode($result,JSON_UNESCAPED_UNICODE);
    }


    //设备管理->出货列表
    public function conf_list()
    {
        $params = request()->param();
        $result = DB::name('machine_conf')
            ->alias('t1')
            ->field('t1.id as conf_id,t1.update_time,t1.number,t2.goods_name')
            ->join('__CLIENT_GOODS__ t2','t1.goods_id=t2.goods_id','LEFT')
            ->where('t1.machine_id',$params['machine_id'])
            ->select();
        return json_encode($result,JSON_UNESCAPED_UNICODE);
    }

    //设备管理>设备设置>设备出货
    public function open()
    {
        $params = request()->param();
        $insert = [
            'machine_id' => $params['machine_id'],
            'conf_id'    => $params['conf_id'],
            'msgtype'    => 'or',
            'send_time'  => time(),
            'content'    => $params['conf_id'] . '出货',
        ];
        $machine_conf = DB::name('machine_conf')->find($params['conf_id']);
        $O = DB::name('command_machine')->insertGetId($insert);
        $P = [
            'ri' => $machine_conf['location'],
            'pm' => $machine_conf['lat'] . ',' . $machine_conf['lng'],
            'num'=> $params['number'],
            'gi' => $machine_conf['goods_id']
        ];
        $json = [
            'C' => 'or',
            'P' => $P,
            'O' => $O
        ];

        model('Machine')->post_to_server($json, $params['machine_id']);

        for($a=0;$a<=14;$a++){
            $isset = DB::name('command_machine')->find($O);
            if($isset){
                $this->success();
                break;
            }else{
                sleep(1);
            }
        }

        $this->error('timeout');
    }

    public function conf_edit()
    {
        $data = request()->param();
//        halt($data);
        $params = $data['json'];
        $machine_conf = DB::name('machine_conf')
            ->where('machine_id',$data['machine_id'])
            ->select();
        foreach($machine_conf as $key => $value){
            if($value['lat'] != $params[$key]['lat'] || $value['lng'] != $params[$key]['lng']){
                DB::name('machine_conf')->where('machine_id',$data['machine_id'])->where('id',$params[$key]['id'])->update(['lat'=>$params[$key]['lat'],'lng'=>$params[$key]['lng'],'update_time'=>time()]);
            }
        }
        $count = count($machine_conf);
        if($count < count($params)){
            //存在新增conf
            for($i=1;$i<=count($params)-$count;$i++){
                $insert[$i-1]['location'] = $count+$i;
                $insert[$i-1]['machine_id'] = $data['machine_id'];
                $insert[$i-1]['lat'] = $params[$count+$i-1]['lat'];
                $insert[$i-1]['lng'] = $params[$count+$i-1]['lng'];
                $insert[$i-1]['create_time'] = time();
                $insert[$i-1]['update_time'] = time();
            }
            DB::name('machine_conf')->insertAll($insert);
        }
        $this->success();

    }





}