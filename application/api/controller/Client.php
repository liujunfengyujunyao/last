<?php

namespace app\api\controller;
use think\Db;
use app\common\controller\Api;
use fast\Random;
/**
 * 用户管理
 */
header('Access-Control-Allow-Headers: Content-Type,Content-Length,Accept-Encoding,X-Requested-with, Origin, Authorization,access-control-request-headers'); // 设置允许自定义请求头的字段
//header("Access-Control-Max-Age", "1800");
header("Content-Type: text/html;charset=utf-8");
header('Access-Control-Allow-Methods:POST,GET,OPTIONS,DELETE'); // 允许请求的类型
header('Access-Control-Allow-Credentials: true'); // 设置是否允许发送 cookies
header('Access-Control-Allow-Origin:*');
class Client extends Api
{
//    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];


    /*
     * 用户管理列表
     *
     * */

    public function client_list()
    {
        $user = $this->auth->getUser();
        $auth_ids = DB::name('client_rule')->where('id',$user['rule_id'])->value('auth_ids');
        if(!in_array(39,explode(',',$auth_ids))){
            $this->error(__('You have no permission'), null, 403);
        }
        $result = DB::name('user')->alias('t1')->join('client_rule t2','t1.rule_id=t2.id','LEFT')->where("t1.pid=$user[id] && org_id=$user[org_id]")->field("t1.id as user_id,t1.nickname,t2.rule_name")->select();
        return json_encode($result,JSON_UNESCAPED_UNICODE);
    }

    /*
     * 用户编辑
     *
     * */

    public function client_edit()
    {

    }


    public function getEncryptPassword($password, $salt = '')
    {
        return md5(md5($password) . $salt);
    }


    /*
     * 添加用户  (3)
     *
     * */

    public function client_add()
    {

        $params = request()->param();
        $user = $this->auth->getUser();
        $username = DB::name('user')->where('username',$params['username'])->find();
        if($username){
            $this->error('用戶名已存在');
        }
        if($user['rule_id'] !== 1 && $user['rule_id'] !== 2){

            $this->error(__('You have no permission'), null, 403);
        }
        $salt = Random::alnum();
        $insert = [
            'username' => $params['username'],
            'password' => $this->getEncryptPassword($params['password'],$salt),
            'salt'     => $salt,
            'nickname' => $params['nickname'],
            'rule_id'  => $params['rule_id'],
            'org_level'=> $user['org_level'],
            'pid'      => $user['id'],
            'org_id'   => $user['org_id'],
        ];


        Db::startTrans();


        $user_id = Db::table('fa_user')->insertGetId($insert);
        if($params['rule_id'] == 7 || $params['rule_id'] == 10){//补货管理员,设备管理员
            $insert = [
                'stock_name' => '个人库存',
                'stock_type' => 4,
                'pid' => $user_id,
            ];
            $stock_id = DB::name('stock')->insertGetId($insert);
            $machine_stock = DB::name('stock')->where('machine_id','in',$params['machine_ids'])->column('id');
            array_push($machine_stock,$stock_id);
            $params['stock_ids'] = implode(',',$machine_stock);
        }

        $domain = [
            'user_id' => $user_id,
            'machine_ids' => $params['machine_ids'],
            'stock_ids' => $params['stock_ids']
        ];

        $result = Db::table('fa_client_domain')->insert($domain);
        if($result){
            Db::commit();
            $this->success('保存成功');//code:1
        }else{
            Db::rollback();
            $this->error('事務回滾');//code:0
        }



    }

    /*
     * 人员归属作用域查询  (2)
     *
     * */

    public function client_domain()
    {
        $params = request()->param();
        $user = $this->auth->getUser();
        if($user['rule_id'] !== 1 && $user['rule_id'] !== 2){
            $this->error(__('You have no permission'), null, 403);
        }
        if($params['rule_id'] == 6){//店长
            if($user['rule_id'] == 1){
                DB::name('client_store')->field('id as store_id,store_name')->where('user_id',$user['id'])->select();
            }else{
                $store_ids = DB::name('client_domain')->where('user_id',$user['id'])->value('store_ids');
                $list = DB::name('client_store')->field('id as store_id,store_name')->where('id','in',$store_ids)->select();
            }

        }elseif($params['rule_id'] == 7 || $params['rule_id'] == 10){//补货员or设备管理员
            if($user['rule_id'] == 1){//品牌超級管理員
                $list = DB::name('machine')->field('machine_id,machine_name')->where('user_id',$user['id'])->select();

            }else{//代理超级管理员
                $machine_ids = DB::name('client_domain')->where('user_id',$user['id'])->value('machine_ids');//查询代理超级管理员的作用域
                $list = DB::name('machine')->field('machine_id,machine_name')->where('machine_id','in',$machine_ids)->select();
            }

        }elseif($params['rule_id'] == 8){//库存人员
                $list = DB::name('stock')->field('id,stock_name')->where('pid',$user['id'])->select();
        }else{
                $list = "*";//全部设备 不需要前端显示
        }
        return json_encode($list,JSON_UNESCAPED_UNICODE);
    }

    /*
     * 角色管理列表  (1)
     *
     * */

    public function rule_list()
    {
        $user = $this->auth->getUser();
        $auth_ids = DB::name('client_rule')->where('id',$user['rule_id'])->value('auth_ids');
        if(!in_array(39,explode(',',$auth_ids))){
            $this->error(__('You have no permission'), null, 403);
        }
        if($user['rule_id'] == 1){
            $where = [3,7,8,9,10,11];
        }else{
            $where = [4,7,8,9,10,12];
        }
        $result = DB::name('client_rule')->field('id as rule_id,rule_name')->where('id','in',$where)->select();
        return json_encode($result,JSON_UNESCAPED_UNICODE);
    }

    /*
     * 添加角色(第一版不做)
     *
     * */

    public function rule_add()
    {

    }


    /*
     * 编辑用户
     *
     * */

    public function client_edit_list()
    {
        $user = $this->auth->getUser();
        $params = request()->param();
        $auth_ids = DB::name('client_rule')->where('id',$user['rule_id'])->value('auth_ids');
        if(!in_array(39,explode(',',$auth_ids))){
            $this->error(__('You have no permission'), null, 403);
        }
        $client = DB::name('user')->where('id',$params['user_id'])->find();
        $rule = DB::name('client_rule')->where('id',$client['rule_id'])->find();
        $domain = DB::name('client_domain')->where('user_id',$client['id'])->find();
        $machine_domain = DB::name('machine')->field('machine_id,machine_name')->where('machine_id','in',$domain['machine_ids'])->select();
        $stock_domain = DB::name('stock')->field('id as stock_id,stock_name')->where('id','in',$domain['stock_ids'])->select();
        if($rule['id'] == 8){//库存人员
            $domain = $stock_domain;
        }elseif($rule['id'] == 7 || $rule['id'] == 10){//设备管理员or补货人员
            $domain = $machine_domain;
        }else{
            $domain = '*';
        }
        $result = [
            'user_id'  => $params['user_id'],
            'username' => $client['username'],
            'nickname' => $client['nickname'],
            'rule' => [$rule['id']=>$rule['rule_name']],
            'domain' => $domain
        ];
        return json_encode($result,JSON_UNESCAPED_UNICODE);
    }

    /*
     *
     * 保存编辑用户
     *
     * */

    public function client_edit_save()
    {
        $user = $this->auth->getUser();
        $params = request()->param();
        $auth_ids = DB::name('client_rule')->where('id',$user['rule_id'])->value('auth_ids');
        if(!in_array(39,explode(',',$auth_ids))){
            $this->error(__('You have no permission'), null, 403);
        }
//        $client = DB::name('user')->where('id',$params['user_id'])->find();
//        $domain = DB::name('client_domain')->where('user_id',$client['id'])->find();

        Db::startTrans();

        Db::table('fa_user')->where('id',$params['user_id'])->update(['rule_id'=>$params['rule_id']]);
        $save = [
            'machine_ids' => $params['machine_ids'],
            'stock_ids'   => $params['stock_ids'],
        ];

        $result = Db::table('fa_client_domain')->where('user_id',$params['user_id'])->update($save);
        if($result !== false){
            Db::commit();
            $this->success('保存成功');//code:1
        }else{
            Db::rollback();
            $this->error('事務回滾');//code:0
        }
    }


    //删除用户
    public function client_delete()
    {
        $user_id = request()->param('user_id');
        Db::startTrans();
        $stock_id = DB::name('stock')->where('pid',$user_id)->find();
        if($stock_id){
            DB::name('stock')->where('id',$stock_id)->delete();
            DB::name('inventory')->where('stock_id',$stock_id)->delete();
            DB::name('inventory_log')->where("stock_to=$stock_id or stock_from=$stock_id")->delete();
        }

        $result = DB::name('user')->where('id',$user_id)->delete();
        if($result !== false){
            Db::commit();
            $this->success();
        }else{
            Db::rollback();
            $this->errpr();
        }
    }


    //修改账号密码
    public function update_pswd()
    {
        $params = request()->param();
        $user = $this->auth->getUser();
        if($this->getEncryptPassword($params['old_password'],$user['salt']) != $user['password']){
            $this->error('密码错误');
        }
//        halt($user['id']);
        $salt = Random::alnum();
        $save = ['password' => $this->getEncryptPassword($params['new_password'],$salt),'salt'=>$salt];
        $result = DB::name('user')->where('id',$user['id'])->update($save);
        if($result !== false){
            $this->success('修改成功');
        }else{
            $this->error('网络错误');
        }
    }




}