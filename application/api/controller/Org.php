<?php

namespace app\api\controller;
use think\Db;
use app\common\controller\Api;
use fast\Random;
/**
 * 组织接口
 */
header('Access-Control-Allow-Headers: Content-Type,Content-Length,Accept-Encoding,X-Requested-with, Origin, Authorization,access-control-request-headers'); // 设置允许自定义请求头的字段
//header("Access-Control-Max-Age", "1800");
header("Content-Type: text/html;charset=utf-8");
header('Access-Control-Allow-Methods:POST,GET,OPTIONS,DELETE'); // 允许请求的类型
header('Access-Control-Allow-Credentials: true'); // 设置是否允许发送 cookies
header('Access-Control-Allow-Origin:*');
class Org extends Api
{
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];

    /**
     *
     *
     */
    public function index()
    {
        $this->success('请求成功');
    }

    /**
     * 组织列表
     *
     */
    public function org_list()
    {
        $token = request()->param('token');
        $user = DB::name('user')->where('token',$token)->find();

        if($user['rule_id'] == 3){
            $rule = $user['pid'];
        }else{
            $rule = $user['id'];
        }
        if(!$user){
            $this->error(__('登陆超时'), null, 301);//重定向
        }elseif($user['rule_id'] != 1 && $user['rule_id'] != 3){
            $this->error(__('You have no permission'), null, 403);
        }else{
            $org= DB::name('client_org')->where('create_id',$rule)->select();
            $result = [];
            foreach($org as $key => $value){
                $admin = DB::name('user')->where('id',$value['admin_id'])->find();

                $result[$key]['org_name'] = $value['org_name'];
                $result[$key]['org_type'] = $value['org_type'];
                $result[$key]['goods_price'] = $value['goods_price'];//1:下级自由定价 2:不低于最低定价 3:执行统一价格
                $result[$key]['add_goods'] = $value['add_goods'];//1:允许 2:不允许
                $result[$key]['username'] = $admin['username'];
                $result[$key]['password'] = $admin['passwd'];//只有代理商超级管理员为明码
                $result[$key]['org_id'] = $value['id'];//只有代理商超级管理员为明码
            }
            return json_encode($result,JSON_UNESCAPED_UNICODE);
        }
    }

    public function getEncryptPassword($password, $salt = '')
    {
        return md5(md5($password) . $salt);
    }

    /**
     * 新增组织
     *
     */
    public function org_add()
    {
        $params = request()->param();
        $user = DB::name('user')->where('token',$params['token'])->find();
//        halt($user);
        if(!$user){
            $this->error(__('登陆超时'), null, 301);//重定向
        }elseif($user['rule_id'] != 1){
            $this->error(__('You have no permission'), null, 403);
        }else{
            $error = DB::name('user')->where('username',$params['username'])->find();
            if($error){
                $this->error(__('用户名已被使用'), null, 500);//用户名重复
            }


            $salt = Random::alnum();
            $ip = request()->ip();
            $time = time();
            $user['password'] = $this->getEncryptPassword($params['password'],$salt);
            $user['username'] = $params['username'];
            $user['nickname'] = $params['username'];
            $user['salt'] = $salt;
            $data = [
                'username' => $params['username'],
                'password' => $this->getEncryptPassword($params['password'],$salt),
//                'mobile'   => $mobile,
                'level'    => 1,
                'score'    => 0,
                'avatar'   => '',
            ];
            $add = array_merge($data, [
                'nickname'  => $params['username'],
                'salt'      => $salt,
                'jointime'  => $time,
                'joinip'    => $ip,
                'logintime' => $time,
                'loginip'   => $ip,
                'prevtime'  => $time,
                'status'    => 'normal',
                'rule_id'   => 2,
                'pid'       => $user['id'],
                'org_level' => 2,
                'passwd' => $params['password'],
            ]);
            $admin_id = DB::name('user')->insertGetId($add);

            $org = [
                'org_name' => $params['org_name'],
                'org_type' => $params['org_type'],
                'create_time' => time(),
                'admin_id' => $admin_id,
                'create_id' => $user['id'],
                'goods_price' => $params['goods_price'],
                'add_goods' => $params['goods_add'],
                'org_level' => 2,
                'pid'       => $user['id'],
            ];

            $result = DB::name('client_org')->insertGetId($org);
            if($result){
                DB::name('user')->where('id',$admin_id)->update(['org_id'=>$result]);//修改这名管理员的组织ID
                $domain = [
                    'user_id' => $admin_id,
                    'stock_ids' => '*',
                    'machine_ids' => '*'
                ];
                DB::name('client_domain')->insert($domain);

                //将level1的商品全部复制到level2
                $level1_goods = DB::name('client_goods')->where('org_id',$user['org_id'])->select();
                $level2_goods = [];
                foreach($level1_goods as $key => $value){
                    $level2_goods['user_id'] = $admin_id;
                    $level2_goods['org_level'] = 2;
                    $level2_goods['goods_name'] = $value['goods_name'];
                    $level2_goods['goods_price'] = $value['goods_price'];
                    $level2_goods['createtime'] = time();
                    $level2_goods['updatetime'] = time();
                    $level2_goods['brief_img'] = $value['brief_img'];
                    $level2_goods['type_id'] = $value['type_id'];
                    $level2_goods['small_img'] = $value['small_img'];
                    $level2_goods['content'] = $value['content'];
                    $level2_goods['org_id'] = $result;
                    $level2_goods['level1_goods_id'] = $value['goods_id'];
                    $level2_goods['status'] = $params['goods_price'];//1:下级自由定价 2:不低于最低定价 3:执行统一价格
                    $goods_id = DB::name('client_goods')->insertGetId($level2_goods);

                    if(!empty($value['brief_img'])){
                        $maxb = $goods_id . 'b';
                        copy("./goods_info/$value[goods_id]b.jpg","./goods_info/$maxb.jpg");//复制图片
                        $update['brief_img'] = "/goods_info/$maxb.jpg";
                    }
                    copy("./goods_info/$value[goods_id].jpg","./goods_info/$goods_id.jpg");//复制图片
                    copy("./goods_info/$value[goods_id].txt","./goods_info/$goods_id.txt");//复制txt文件
                    $update['small_img'] = "/goods_info/$goods_id.jpg";
                    DB::name('client_goods')->where('goods_id',$goods_id)->update($update);
                }

                $this->success('请求成功');
            }else{
                $this->error(__('请求失败'), null, 500);//数据库连接错误
            }

        }

    }


    /**
     * 编辑组织
     * 修改下级prg_level更改商品价格
     *
     */
    public function org_edit()
    {
        $params = request()->param();
        $user = $this->auth->getUser();
        $org = DB::name('client_org')
            ->find($params['org_id']);

        //判断是否改变了定价规则(修改level_goods_id不为空的商品价格)  &  是否将可添加功能改为不可(删除其自行添加的商品)
        if($org['add_goods'] == 1 && $params['add_goods'] == 2){
//            $org_ids = DB::name('client_org')
//                ->where('pid',$user['org_id'])
//                ->column('id');

            //如果有设备承装已失效商品?
            $machines = DB::name('machine')->where('org_id',$params['org_id'])->column('machine_id');
            $yet_goods = DB::name('client_goods')->where('org_id',$params['org_id'])->where('level1_goods_id',null)->column('goods_id');
            $isset = DB::name('machine_conf')->where('machine_id','in',$machines)->where('goods_id','in',$yet_goods)->find();
            if(!is_null($isset)){
                $this->error('下级代理正在贩卖自行添加商品');
            }
//            foreach($org_ids as $k => $v){
                //删除所有代理的自行添加商品
                DB::name('client_goods')
//                    ->where('org_id',$v)
                    ->where('org_id',$params['org_id'])
                    ->where('level1_goods_id',null)
                    ->delete();

                $goods_ids = DB::name('client_goods')
//                    ->where('org_id',$v)
                    ->where('org_id',$params['org_id'])
                    ->where('level1_goods_id',null)
                    ->column('goods_id');
                DB::name('machine_conf')->where('goods_id','in',$goods_ids)
                    ->update(['goods_id'=>null]);
//            }
        }

        if($org['goods_price'] != $params['goods_price']){//需要修改商品价格
            $level1_goods_ids = DB::name('client_goods')
                ->field('goods_id,goods_price')
                ->where('org_id',$org['pid'])
                ->select();
            if($params['goods_price'] == 3){
                foreach($level1_goods_ids as $key => $value){
                    DB::name('client_goods')
                        ->where('level1_goods_id',$value['goods_id'])
                        ->update(['goods_price'=>$value['goods_price']]);
                }
            }
            if($params['goods_price'] == 2){
                foreach($level1_goods_ids as $key => $value){
                    DB::name('client_goods')
                        ->where('level1_goods_id',$value['goods_id'])
                        ->where('goods_price','lt',$value['goods_price'])
                        ->update(['goods_price'=>$value['goods_price']]);
                }
            }
        }
        //修改组织 & 修改组织下商品
        $goods_save = [
            'updatetime' => time(),
            'status'     => $params['goods_price']
        ];
        DB::name('client_goods')
            ->where('org_id',$params['org_id'])
            ->update($goods_save);
        $org_save = [
            'goods_price' => $params['goods_price'],
            'add_goods'   => $params['add_goods'],
        ];
        DB::name('client_org')
            ->where('id',$params['org_id'])
            ->update($org_save);
        $this->success();

    }


}
