<?php

namespace app\api\controller;
use think\Db;
use app\common\controller\Api;
use fast\Random;
/**
 * 门店管理
 */
header('Access-Control-Allow-Headers: Content-Type,Content-Length,Accept-Encoding,X-Requested-with, Origin, Authorization,access-control-request-headers'); // 设置允许自定义请求头的字段
//header("Access-Control-Max-Age", "1800");
header("Content-Type: text/html;charset=utf-8");
header('Access-Control-Allow-Methods:POST,GET,OPTIONS,DELETE'); // 允许请求的类型
header('Access-Control-Allow-Credentials: true'); // 设置是否允许发送 cookies
header('Access-Control-Allow-Origin:*');
class Store extends Api
{
//    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];


    /*
     * 门店创建
     *
     * */

    public function store_add()
    {
        $params = request()->param();
        $user = $this->auth->getUser();
        $auth_ids = DB::name('client_rule')->where('id',$user['rule_id'])->value('auth_ids');
        if(!in_array(24,explode(',',$auth_ids))){
            $this->error(__('You have no permission'), null, 403);
        }

        $stock_insert = [
            'stock_name' => '门店仓库',
            'stock_type' => 2,
            'pid'        => $user['pid'],//超级管理员
        ];
        $stock_id = DB::name('stock')->insertGetId($stock_insert);

        $store_insert = [
            'store_name' => $params['store_name'],
            'level'      => $user['org_level'],//1品牌 2代理
            'stock_id'   => $stock_id,
            'create_time'=> time(),
            'user_id'    => $user['pid'],//超级管理员
            'store_address' => $params['store_address']
        ];
        $result = DB::name('client_store')->insert($store_insert);
        if($result){
            $this->success('添加成功');//code:1
        }else{
            $this->error('数据库超时');//code:0
        }

    }


    /*
     * 门店列表
     *
     * */

    public function store_list()
    {
//        $params = request()->param();
        $user = $this->auth->getUser();
        $auth_ids = DB::name('client_rule')->where('id',$user['rule_id'])->value('auth_ids');
        if(!in_array(24,explode(',',$auth_ids))){
            $this->error(__('You have no permission'), null, 403);
        }


        $pid = $user['pid'];//店铺user_id为组织的超级管理员   能查询到这个接口的为pid=超级管理员ID的管理员
        if($user['org_level'] == 1){//组织为品牌
            $first = DB::name('client_org')->where('id',$user['org_id'])->value('org_name');
            $title = $first . "-直营店";

        }else{//组织为代理商
            $brand = DB::name('user')->where('id',$user['id'])->find();
            $first = DB::name('client_org')->where('id',$brand['org_id'])->value('org_name');
            $second = DB::name('client_org')->where('id',$user['org_id'])->value('org_name');
            $title = $first . '-' . $second;

        }

        $result = [
            'title' => $title,
            'list' => DB::name('client_store')->field('store_name,store_address,id as store_id')->where('user_id',$pid)->select()
        ];
        return json_encode($result,JSON_UNESCAPED_UNICODE);

    }
    
    /*
     * 门店编辑
     * 
     * */
    
    public function store_edit()
    {
        $params = request()->param();
        $save = [
            'store_name' => $params['store_name'],
            'store_address' => $params['store_address']
        ];
        $result = DB::name('client_store')->where('id',$params['store_id'])->update($save);
        if($result !== false){
            $this->success('修改成功');//code:1
        }else{
            $this->error('数据库超时');//code:0
        }
    }

}