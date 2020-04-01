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
class Goods extends Api
{
        protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];
    protected $pub_rule = [3,4,9,11,12];//代理全局
    protected $dom_rule = [7,8,10];//需要指定设备的角色
    protected $logo_user = [3,11];//品牌运营/监督管理员

//        public function _initialize()
//        {
//            parent::_initialize();
//            $this->model = model('Machine');
//        }
        /*
         * 商品列表
         *
         * */
        public function goods_list()
        {
            $user = $this->auth->getUser();
//            $user = DB::name('user')->find(46);
            $params = request()->param();
            $auth_ids = DB::name('client_rule')->where('id',$user['rule_id'])->value('auth_ids');
            $org = DB::name('client_org')->field('goods_price,add_goods,org_level,pid')->where('id',$user['org_id'])->find();
//            halt($org);
            if(!in_array(33,explode(',',$auth_ids))) {//商品列表
                $this->error(__('You have no permission'), null, 403);
            }

            if(!empty($params['key'])){
                $where['t1.goods_name'] = ['like',"%".$params['key']."%"];
            }else{
                $where = "1=1";
            }
//            halt($where);
            if($org['org_level'] == 1){//品牌运营
                $result = [
                    'add_goods' => 1,//可以增加商品
//                    'edit_goods' => 1,
                    'edit_list' => DB::name('client_goods')
                        ->alias('t1')
                        ->field('t1.goods_price,t1.goods_id,t1.goods_name,t1.brief_img,t2.type_name,t1.small_img,t1.content')
                        ->join('__CLIENT_GOODS_TYPE__ t2','t1.type_id = t2.type_id','RIGHT')
                        ->where('t1.user_id',$user['pid'])
                        ->where($where)
                        ->select(),
                    'view_list' => '',
                    'type_list'  => DB::name('client_goods_type')->field('type_id,type_name')->where('user_id',$user['pid'])->select()
                ];
                foreach($result['edit_list'] as $key => &$value){
                    $value['detail_img'] = DB::name('detail_img')->where('goods_id',$value['goods_id'])->column('url');
                }
            }else{
                $level1_admin = DB::name('client_org')->where('id',$org['pid'])->value('admin_id');
                $result = [
                    'add_goods' => $org['add_goods'],//1允许 2不允许
                    'edit_list' => DB::name('client_goods')
                        ->alias('t1')
                        ->field('t1.goods_price,t1.goods_id,t1.goods_name,t1.brief_img,t2.type_name,t1.small_img,t1.content,t2.type_id,t1.status')
                        ->join('__CLIENT_GOODS_TYPE__ t2','t1.type_id = t2.type_id','RIGHT')
                        ->where('t1.user_id',$user['pid'])
                        ->where($where)
                        ->select(),
                    'view_list' => DB::name('client_goods')
                        ->alias('t1')
                        ->field('t1.goods_price,t1.goods_id,t1.goods_name,t1.brief_img,t2.type_name,t1.small_img,t1.content,t2.type_id')
                        ->join('__CLIENT_GOODS_TYPE__ t2','t1.type_id = t2.type_id','RIGHT')
                        ->where('t1.user_id',$level1_admin)
                        ->where($where)
                        ->select(),
                    'type_list'  => DB::name('client_goods_type')->field('type_id,type_name')->where('user_id',$user['pid'])->select()
                ];
                foreach($result['edit_list'] as $key => &$value){
                    $value['detail_img'] = DB::name('detail_img')->where('goods_id',$value['goods_id'])->column('url');
                }
                foreach($result['view_list'] as $k => &$v){
                    $value['detail_img'] = DB::name('detail_img')->where('goods_id',$v['goods_id'])->column('url');
                }

            }

            return json_encode($result,JSON_UNESCAPED_UNICODE);

        }

        /*
        * 添加商品
        *
        * */
        public function goods_add()
        {
            $user = $this->auth->getUser();

            $params = request()->param();

            $auth_ids = DB::name('client_rule')->where('id',$user['rule_id'])->value('auth_ids');
            //运营管理员添加商品
            $org = DB::name('client_org')->field('goods_price,add_goods,org_level')->where('id',$user['org_id'])->find();
            if(!in_array(35,explode(',',$auth_ids))) {//添加商品
                $this->error(__('You have no permission'), null, 403);
            }
            $org_level = DB::name('client_org')->where('id',$user['org_id'])->value('org_level');
            if($org_level == 1){//品牌运营  暂时使用内置权限 不再判断细分权限
                $level = 'level1';
            }else{
                if($org['add_goods'] == 2){//不能自行添加商品
                    $this->error(__('You have no permission'), null, 403);
                }
                $level = 'level2';
            }
            $small = request()->file('small_img');
            $small_url = $brief_url = '';
            $goods_id = DB::name('client_goods')
                ->max('goods_id');
            $goods_id = $goods_id + 1;
            if (!empty($small)){
//                $small_img = $small->validate(['size' => 210780000, 'ext' => 'jpg,gif,png,jpeg'])->move(ROOT_PATH . 'public' . DS . 'uploads' . DS . $level);
                $small_img = $small->validate(['size' => 210780000, 'ext' => 'jpg'])->move(ROOT_PATH . 'public' . DS . 'goods_info' . DS . $goods_id,$goods_id);
//                $small_url = DS . 'uploads' . DS . 'level1' . DS . $small_img->getSaveName();
                $small_url = DS . 'goods_info' . DS . $goods_id . DS . $small_img->getSaveName();
            }
            $brief = request()->file('brief_img');
            if (!empty($brief)){

//                $brief_img = $brief->validate(['size' => 210780000, 'ext' => 'jpg,gif,png,jpeg'])->move(ROOT_PATH . 'public' . DS . 'uploads' . DS . $level);
                $brief_img = $brief->validate(['size' => 210780000, 'ext' => 'jpg'])->move(ROOT_PATH . 'public' . DS . 'goods_info' . DS . $goods_id,$goods_id . 'b');
                $brief_url = DS . 'goods_info' . DS . $goods_id . DS . $brief_img->getSaveName();
            }
            $detail_arr = [];//详情图对象
            //多图上传
            $detail = request()->file('detail_img');
            if(!empty($detail)){
                $file_time = date('YmdHis');
                foreach($detail as $k => $v){
                    $detail_img = $v->validate(['size' => 210780000, 'ext' => 'jpg'])->move(ROOT_PATH . 'public' . DS . 'goods_info' . DS . $goods_id,$file_time);
                    $file_time++;
                    $detail_url = DS . 'goods_info' . DS . $goods_id . DS . $detail_img->getSaveName();
                    //添加到detail_img表
                    $add = [
                        'goods_id' => $goods_id,
                        'url' => $detail_url,
                        'name' => $detail_img->GetSaveName(),
                        'update_time' => time(),
                    ];
                    DB::name('detail_img')->insert($add);
                    array_push($detail_arr,$add['name']);//txt中的数组
                }
            }


            $insert = [
                'goods_name' => $params['goods_name'],
                'type_id'    => $params['type_id'],
                'goods_price'=> $params['goods_price'],
                'small_img'  => $small_url,
                'brief_img'  => $brief_url,
                'content'    => $params['content'],
                'user_id'    => $user['pid'],
                'org_level'  => $org['org_level'],
                'org_id'     => $user['org_id'],
                'createtime' => time(),
                'updatetime' => time(),
            ];

            $result = DB::name('client_goods')->insertGetId($insert);//一级图片地址
            $filename = "./goods_info/$result/" . $result . ".txt";
            $data = json_encode([
                'name' => $insert['goods_name'],
                'category' => DB::name('client_goods_type')
                ->where('type_id',$insert['type_id'])
                ->value('type_name'),
                'description' => $insert['content'],
                'detail_img' => $detail_arr,
            ],JSON_UNESCAPED_UNICODE);
            file_put_contents($filename,$data);

            //复制给下级
            if($user['org_level'] == 1){
                $level2 = DB::name('client_org')->where('pid',$user['org_id'])->select();
                if(!is_null($level2)){
                    foreach($level2 as $key => $value){
                        $add = [
                            'goods_name' => $params['goods_name'],
                            'type_id'    => $params['type_id'],
                            'goods_price'=> $params['goods_price'],
                            'small_img'  => $small_url,//暂时
                            'brief_img'  => $brief_url,//暂时
                            'content'    => $params['content'],
                            'user_id'    => $value['admin_id'],
                            'org_level'  => 2,
                            'org_id'     => $value['id'],
                            'createtime' => time(),
                            'updatetime' => time(),
                            'level1_goods_id' => $result,
                            'status'     => $value['goods_price'],//定价规则
                        ];
                        $goods_id = DB::name('client_goods')->insertGetId($add);//二级商品ID
                        mkdir("./goods_info/$goods_id",0777,true);
                        if(!is_null($brief_url)){
                            $goods_idb = $goods_id . 'b';
                            $resultb = $result . 'b';
//                            mkdir("./goods_info/$goods_id",0777,true);
                            copy("./goods_info/$result/$resultb.jpg","./goods_info/$goods_id/$goods_idb.jpg");//复制图片
                            $update['brief_img'] = "/goods_info/$goods_id/$goods_idb.jpg";
                        }
                        copy("./goods_info/$result/$result.jpg","./goods_info/$goods_id/$goods_id.jpg");//复制图片
                        copy("./goods_info/$result/$result.txt","./goods_info/$goods_id/$goods_id.txt");//复制文本
                        $update['small_img'] = "/goods_info/$goods_id/$goods_id.jpg";
                        DB::name('client_goods')->where('goods_id',$goods_id)->update($update);

                        //多图复制
                        $detail_url = DB::name('detail_img')->where('goods_id',$result)->select();
                        if(!is_null($detail_url)){
                            foreach($detail_url as $key => $value){
                                copy("./goods_info/$result/$value[name]","./goods_info/$goods_id/$value[name]");//复制图片
                                $add = [
                                    'goods_id' => $goods_id,
                                    'name' => $value['name'],
                                    'url'  => "/goods_info/$goods_id/$value[name]",
                                    'update_time' => time()
                                ];
                                DB::name('detail_img')->insert($add);
                            }
                        }


                    }

                }
            }




            if($result){
                $this->success('添加完成');
            }else{
                $this->error('网络错误');
            }
        }

        /*
         * 添加商品分类列表
         *
         * */

        public function type_add()
        {
            $user = $this->auth->getUser();
            $params = request()->param();
            if(model('goods')->api_auth($user['id'],36)['code'] == 0) $this->error(__('You have no permission'), null, 403);
            if(model('goods')->type_add($user,$params)['code'] == 1){
                $this->success('新增完成');
            }else{
                $this->error('网络错误');
            }
        }

        /*
         *
         * 商品分类列表
         *
         * */
        public function goods_type()
        {
            $user = $this->auth->getUser();

            if(model('goods')->api_auth($user['id'],36)['code'] == 0) $this->error(__('You have no permission'), null, 403);
            return model('goods')->type_list($user);
        }


//        /*
//         * 删除商品分类
//         *
//         * */
//        public function goods_type_delete()
//        {
//            $user = $this->auth->getUser();
//            $params = request()->param();
//            if(model('goods')->api_auth($user['id'],35)['code'] == 0) $this->error(__('You have no permission'), null, 403);
//            $code = model('goods')->goods_type_delete($params)['code'];
//            if($code == 1){
//                $this->success('删除成功');
//            }elseif($code == 2){
//                $this->error('不能删除有下级商品的分类');
//            }else{
//                $this->error('网络错误');
//            }
//        }
//
//        public function goods_delete()
//        {
//            $user = $this->auth->getUser();
//            $params = request()->param();
//            if(model('goods')->api_auth($user['id'],35)['code'] == 0) $this->error(__('You have no permission'), null, 403);
//            $code = model('goods')->goods_delete($params)['code'];
//            if($code == 1){
//                $this->success('删除成功');
//            }elseif($code == 2){
//                $this->error('不能删除有下级商品的分类');
//            }else{
//                $this->error('网络错误');
//            }
//        }


        /*
         * 商品编辑(没有编辑  直接新增商品)
         *
         * */
//        public function goods_edit()
//        {
//            $user = $this->auth->getUser();
//            $params = request()->param();
//            if(model('goods')->api_auth($user['id'],35)['code'] == 0) $this->error(__('You have no permission'), null, 403);
//            $org = DB::name('client_org')->field('goods_price,add_goods,org_level')->where('id',$user['org_id'])->find();
//            $org_level = DB::name('client_org')->where('id',$user['org_id'])->value('org_level');
//            if($org_level == 1){//品牌运营  暂时使用内置权限 不再判断细分权限
//                $level = 'level1';
//            }else{
//                if($org['add_goods'] == 2){//不能自行添加商品
//                    $this->error(__('You have no permission'), null, 403);
//                }
//                $level = 'level2';
//            }
//            $small = request()->file('small_img');
//            $small_url = $brief_url = '';
//            if (!empty($small)){
//                $small_img = $small->validate(['size' => 210780000, 'ext' => 'jpg,gif,png,jpeg'])->move(ROOT_PATH . 'public' . DS . 'uploads' . DS . $level);
//                $small_url = DS . 'uploads' . DS . 'level1' . DS . $small_img->getSaveName();
//            }
//            $brief = request()->file('brief_img');
//            if (!empty($brief)){
//
//                $brief_img = $brief->validate(['size' => 210780000, 'ext' => 'jpg,gif,png,jpeg'])->move(ROOT_PATH . 'public' . DS . 'uploads' . DS . $level);
//                $brief_url = DS . 'uploads' . DS . 'level1' . DS . $brief_img->getSaveName();
//            }
//            $save = [
//                'goods_name' => $params['goods_name'],
//                'type_id'    => $params['type_id'],
//                'goods_price'=> $params['goods_price'],
//                'small_img'  => $small_url,
//                'brief_img'  => $brief_url,
//                'content'    => $params['content'],
//                'updatetime' => time(),
//            ];
//            $result = DB::name('client_goods')->where('id',$params['id'])->update($save);
//            if($result !== false){
//                //发送协议
//
//                $this->success('修改完成');
//            }else{
//                $this->error('网络错误');
//            }
//        }


        //编辑商品信息(名称种类价格简介)
        public function goods_detail_edit()
        {
            $user = $this->auth->getUser();
            $params = request()->param();
            if(model('goods')->api_auth($user['id'],35)['code'] == 0) $this->error(__('You have no permission'), null, 403);
            $org = DB::name('client_org')->field('goods_price,add_goods,org_level')->where('id',$user['org_id'])->find();
            $org_level = DB::name('client_org')->where('id',$user['org_id'])->value('org_level');
            $good = DB::name('client_goods')->where('goods_id',$params['goods_id'])->find();
            $time = time();
            if($good['status'] == 3 ){
                $this->error('不允许修改');
            }
            $save = [
                'goods_name' => $params['goods_name'],
                'goods_price' => $params['goods_price'],
                'updatetime' => time(),
                'type_id' => $params['type_id'],
                'content' => $params['content']
            ];
            $result = DB::name('client_goods')
                ->where('goods_id',$params['goods_id'])
                ->update($save);
            if($result !== false){
                $filename = "./goods_info/$params[goods_id]/" . $params['goods_id'] . ".txt";
                $data = json_encode([
                    'name' => $save['goods_name'],
                    'category' => DB::name('client_goods_type')
                        ->where('type_id',$save['type_id'])
                        ->value('type_name'),
                    'description' => $save['content'],
                ],JSON_UNESCAPED_UNICODE);
                file_put_contents($filename,$data);
                $level2_goods = DB::name('client_goods')
                    ->where('level1_goods_id',$params['goods_id'])
                    ->select();
                if(!empty($level2_goods)){//修改下级代理 继承的相同商品 并向装载此物品的设备发送协议
                    foreach($level2_goods as $key => $value){
                        copy("./goods_info/$params[goods_id]/$params[goods_id].txt","./goods_info/$value[goods_id]/$value[goods_id].txt");//复制文本
                        DB::name('client_goods')->where('goods_id',$value['goods_id'])->update($save);//用户客户端识别商品是否经过修改
                        $machine_ids = DB::name('machine_conf')
                            ->alias('t1')
                            ->join('__MACHINE__ t2','t1.machine_id=t2.machine_id','LEFT')
                            ->where('t1.goods_id',$value['goods_id'])
                            ->where('t2.is_online',1)
                            ->column('t1.machine_id');//存放受影响商品并在线的设备
                        $machine_ids = array_unique($machine_ids);
                        $json = $command = [];
                        foreach($machine_ids as $k => $v){
                            $P = [
                                'gi' => $value['goods_id'],
                                'gt' => $value['updatetime'],
                                'gp' => $value['goods_price'],
                            ];
                            $command = [
                                'machine_id' => $v,
                                'msgtype' => 'ug',
                                'send_time' => time(),
                                'content' => json_encode(['P'=>$P],JSON_UNESCAPED_UNICODE),
                            ];
                            $O = DB::name('command_machine')->insertGetId($command);
                            $json = [
                                'C' => 'ug',
                                'P' => $P,
                                'O' => $O,
                            ];
                            model('Machine')->post_to_server($json,$v);
                        }
                    }
                }
                //发送(更新商品信息)协议
                $machine_ids = DB::name('machine_conf')
                    ->alias('t1')
                    ->join('__MACHINE__ t2','t1.machine_id=t2.machine_id','LEFT')
                    ->where('t1.goods_id',$params['goods_id'])
                    ->where('t2.is_online',1)
                    ->column('t1.machine_id');
                $machine_ids = array_unique($machine_ids);
                $json = $command = [];
                foreach($machine_ids as $key => $value){
                    $P = [
                        'gi' => $params['goods_id'],
                        'gt' => $time,
                        'gp' => $params['goods_price']
                    ];
                    $command = [
                        'machine_id' => $value,
                        'msgtype' => 'ug',
                        'send_time' => time(),
                        'content' => json_encode(['P'=>$P],JSON_UNESCAPED_UNICODE),
                    ];
                    $O = DB::name('command_machine')->insertGetId($command);
                    $json = [
                        'C' => 'ug',
                        'P' => $P,
                        'O' => $O,
                    ];
                    model('Machine')->post_to_server($json,$value);
                }

                $this->success();
            }else{
                $this->error();
            }
        }

        //编辑商品图片(缩略图详情图)
        public function goods_pic_edit()
        {
            $user = $this->auth->getUser();
            $params = request()->param();
            $level2 = DB::name('client_goods')->where('level1_goods_id',$params['goods_id'])->column('goods_id');
            //新增删除详情图参数[]
            if(!empty($params['delete_img'])){
//                $params['delete_img'] = implode(',',$params['delete_img']);
                $delete_img = DB::name('detail_img')->where('url','in',$params['delete_img'])->where('goods_id',$params['goods_id'])->select();
                foreach($delete_img as $key => $value){
                    unlink("./goods_info/$value[goods_id]/$value[name]");
                    DB::name('detail_img')->where('id',$value['id'])->delete();

                    if($level2){
                        foreach($level2 as $k => $v){
                            unlink("./goods_info/$v/$value[name]");//删除二级商品的该详情图
                            DB::name('detail_img')->where('goods_id',$v)->where('name',$value['name'])->delete();
                        }

                    }
                }
            }



            if(model('goods')->api_auth($user['id'],35)['code'] == 0) $this->error(__('You have no permission'), null, 403);
            $good = DB::name('client_goods')->where('goods_id',$params['goods_id'])->find();
            if($good['status'] == 3 ){
                $this->error('不允许修改');
            }
            if(!is_null($params['brief_url'])){
                $brief_url = DS . substr($params['brief_url'],strripos($params['brief_url'],"6")+1);
            }
            if(!is_null($params['small_url'])){
                $small_url = DS . substr($params['small_url'],strripos($params['small_url'],"6")+1);
            }

//            $brief_url = $params['brief_url'];
//            $small_url = $params['small_url'];
            $small = request()->file('small_img');
            $brief = request()->file('brief_img');
            $detail = request()->file('detail_img');
            if (!empty($small)){
//                $small_img = $small->validate(['size' => 210780000, 'ext' => 'jpg,gif,png,jpeg'])->move(ROOT_PATH . 'public' . DS . 'uploads' . DS . $level);
                $small_img = $small->validate(['size' => 5242880, 'ext' => 'jpg'])->move(ROOT_PATH . 'public' . DS . 'goods_info' . DS . $params['goods_id'],$params['goods_id']);
//                $small_url = DS . 'uploads' . DS . 'level1' . DS . $small_img->getSaveName();
                $small_url = DS . 'goods_info' . DS . $params['goods_id'] . DS . $small_img->getSaveName();
            }

            if (!empty($brief)){
//                $brief_img = $brief->validate(['size' => 210780000, 'ext' => 'jpg,gif,png,jpeg'])->move(ROOT_PATH . 'public' . DS . 'uploads' . DS . $level);
                $brief_img = $brief->validate(['size' => 5242880, 'ext' => 'jpg'])->move(ROOT_PATH . 'public' . DS . 'goods_info' . DS . $params['goods_id'],$params['goods_id'] . 'b');
                $brief_url = DS . 'goods_info' . DS . $params['goods_id'] . DS . $brief_img->getSaveName();
            }
            $detail_arr = DB::name('detail_img')->where('goods_id',$params['goods_id'])->column('name');//如果为空会返回空数组
            if(!empty($detail)){
                $file_time = date('YmdHis');
                foreach($detail as $key => $value){
                    $detail_img = $value->validate(['size' => 5242880, 'ext' => 'jpg'])->move(ROOT_PATH . 'public' . DS . 'goods_info' . DS . $params['goods_id'],$file_time);
                    if($level2){
                        foreach($level2 as $k => $v){
                            copy("./goods_info/$params[goods_id]/$file_time.jpg","./goods_info/$v/$file_time.jpg");//复制图片
                            $add = [
                                'goods_id' => $v,
                                'name' => $file_time,
                                'url'  =>  DS . 'goods_info' . DS . $v . DS . $file_time,
                                'update_time' => time(),
                            ];
                            DB::name('detail_img')->insert($add);
                        }

                    }
                    $file_time++;
                    $detail_url = DS . 'goods_info' . DS . $params['goods_id'] . DS . $detail_img->getSaveName();
                    $add = [
                        'goods_id' => $params['goods_id'],
                        'name' => $detail_img->getSaveName(),
                        'url' => $detail_url,
                        'update_time' => time()
                    ];
                    DB::name('detail_img')->insert($add);
                    array_push($detail_arr,$detail_img->getSaveName());
                }
            }
            $old_json = file_get_contents("http://" . $_SERVER['HTTP_HOST']."/goods_info/$params[goods_id]/" . $params['goods_id'] . ".txt");
            $old_arr = json_decode($old_json,true);
            $filename = "./goods_info/$params[goods_id]/" . $params['goods_id'] . ".txt";
            $data = json_encode([
                'name' => $old_arr['name'],
                'category' => $old_arr['category'],
                'description' => $old_arr['description'],
                'detail_img' => $detail_arr,
            ],JSON_UNESCAPED_UNICODE);
            file_put_contents($filename,$data);//重写txt文件

            $result = DB::name('client_goods')
                ->where("goods_id = $params[goods_id] or level1_goods_id = $params[goods_id]")
//                ->update(['small_img'=>$small_url,'brief_img'=>$brief_url,'updatetime'=>time()]);
                ->update(['updatetime'=>time()]);
            if($result !== false){
                //发送(更新商品信息)协议
                $machine_ids = DB::name('machine_conf')
                    ->alias('t1')
                    ->join('__MACHINE__ t2','t1.machine_id=t2.machine_id','LEFT')
//                    ->join('__CLIENT_GOODS__ t3','t3.goods_id=t1.goods_id','LEFT')
                    ->where('t1.goods_id',$params['goods_id'])
//                    ->where("t1.goods_id = $params[goods_id] or t3.level1_goods_id = $params[goods_id]")
                    ->where('t2.is_online',1)
                    ->column('t1.machine_id');
                $machine_ids = array_unique($machine_ids);
//                dump($machine_ids);
                $json = $command = [];
                if($machine_ids != []){
//                    halt(1);
                    foreach($machine_ids as $key => $value){
//                        halt($value);
                        $command = [
                            'machine_id' => $value,
                            'msgtype' => 'ug',
                            'send_time' => time(),
                            'content' => json_encode(['P'=>$params['goods_id']],JSON_UNESCAPED_UNICODE),
                        ];
                        $O = DB::name('command_machine')->insertGetId($command);
                        $json = [
                            'C' => 'ug',
                            'P' => $params['goods_id'],
                            'O' => $O,
                        ];
                        model('Machine')->post_to_server($json,$value);
                    }
                }



                $level2_goods = DB::name('client_goods')
                    ->where('level1_goods_id',$params['goods_id'])
                    ->select();
//                halt($level2_goods);
                if(!empty($level2_goods)){//修改下级代理 继承的相同商品 并向装载此物品的设备发送协议
//                    halt($level2_goods);
                    foreach($level2_goods as $key => $value){
                        copy("./goods_info/$params[goods_id]/$params[goods_id].jpg","./goods_info/$value[goods_id]/$value[goods_id].jpg");//复制图片
                        copy("./goods_info/$params[goods_id]/$params[goods_id]b.jpg","./goods_info/$value[goods_id]/$value[goods_id]b.jpg");//复制图片
//                        $detail_url = DB::name('detail_img')->where('goods_id',$params['goods_id'])->select();//品牌运营商的详情图
//                        if(!is_null($detail_url)){
//                            foreach($detail_url as $k => $v){
//                                copy("./goods_info/$params[goods_id]/$v[name].jpg","./goods_info/$value[goods_id]/$v[name].jpg");//复制图片
//                                $isset = DB::name('detail_img')->where('goods_id',$value['goods_id'])->where('name',$v['name'])->find();//二级存在和一级商品相同的详情图
//                                if(is_null($isset)){//不存在,添加
//                                    $insert = [
//                                        'goods_id' => $value['goods_id'],
//                                        'name'     => $v['name'],
//                                        'url'      => "/goods_info/$value[goods_id]/$v[name].jpg",
//                                        'update_time' => time(),
//                                     ];
//                                    DB::name('detail_url')->insert($insert);
//                                }else{//修改
//                                    $update = [
//                                        'name' => $v['name'],
//                                        'url'  => "/goods_info/$value[goods_id]/$v[name].jpg",
//                                        'update_time' => time(),
//                                    ];
//                                    DB::name('detail_img')->where('goods_id',$value['goods_id'])->where('name',)
//                                }
//                            }
//                        }
                        $filename2 = "./goods_info/$value[goods_id]/" . $value['goods_id'] . ".txt";
                        file_put_contents($filename2,$data);//重写txt文件
                        DB::name('client_goods')
                            ->where('goods_id',$value['goods_id'])
                            ->update(['updatetime'=>time()]);//用户客户端识别商品是否经过修改
//                        dump($value);
                        $machine_ids = DB::name('machine_conf')
                            ->alias('t1')
                            ->join('__MACHINE__ t2','t1.machine_id=t2.machine_id','LEFT')
                            ->where('t1.goods_id',$value['goods_id'])
                            ->where('t2.is_online',1)
                            ->column('t1.machine_id');//存放受影响商品并在线的设备
                        $machine_ids = array_unique($machine_ids);
                        $json = $command = [];
                        foreach($machine_ids as $k => $v){
                            $command = [
                                'machine_id' => $v,
                                'msgtype' => 'ug',
                                'send_time' => time(),
                                'content' => json_encode(['P'=>$value['goods_id']],JSON_UNESCAPED_UNICODE),
                            ];
                            $O = DB::name('command_machine')->insertGetId($command);
                            $json = [
                                'C' => 'ug',
                                'P' => $value['goods_id'],
                                'O' => $O,
                            ];
                            model('Machine')->post_to_server($json,$v);
                        }
                    }
                }
                $this->success();
            }else{
                $this->error();
            }


        }

        public function goods_detail()
        {
//            $user = $this->auth->getUser();
//            $params = request()->param();
//            $result = DB::name('client_goods')
//                ->field('')
//                ->where('goods_id',$params['goods_id'])
//                ->find();
//            $_SERVER = DB::name('client_goods')
//                ->
//                ->select();
//
//            return json_encode($result);
        }

        //修改商品种类名称
        public function goods_type_edit()
        {
            $user = $this->auth->getUser();
            $params = request()->param();
            $update = [
                'type_name' => $params['type_name'],
                'updatetime' => time(),
            ];
            $result = DB::name('client_goods_type')
                ->where('type_id',$params['type_id'])
                ->update($update);
            if($result !== false){
                $this->success();
            }else{
                $this->error();
            }
        }







}