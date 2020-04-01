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
class Group extends Api
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


    //创建群组
    public function group_add()
    {
        $user = $this->auth->getUser();
        $params = request()->param();
        $insert = [
            'group_name' => $params['group_name'],
            'user_id' => $user['pid'],
        ];
        $result = DB::name('group')->insert($insert);
        if ($result) {
            $this->success();
        } else {
            $this->error();
        }
    }

    //编辑群组(改名)
    public function group_edit()
    {
        $user = $this->auth->getUser();
        $params = request()->param();
        $result = DB::name('group')
            ->where('id', $params['group_id'])
            ->update(['group_name' => $params['group_name']]);
        if ($result !== false) {
            $this->success();
        } else {
            $this->error();
        }

    }

    //群组列表    全部设备列表查询machine/machine_list
    public function group_list()
    {
        $user = $this->auth->getUser();
//        $user = DB::name('user')->find(41);
        $group = DB::name('group')
            ->where('user_id', $user['pid'])
            ->select();
        $result = [];
        $arr = [];
        foreach ($group as $key => $value) {
            $result[$key]['group_id'] = $value['id'];
            $result[$key]['group_name'] = $value['group_name'];

            $result[$key]['machine_list'] = DB::name('machine')
                ->field(
                    'machine_id,machine_name')
                ->where('machine_id', 'in', $value['machine_ids'])
                ->select();//存在于此分组的设备(selected)

//            array_push($arr,DB::name('machine')->where('machine_id','in',$value['machine_ids'])->column('machine_id'));//将所有已经被分配到群组的machine_id集合到一个数组中
//            $arr = $arr + DB::name('machine')->where('machine_id', 'in', $value['machine_ids'])->column('machine_id');
            $arr = array_merge($arr,DB::name('machine')->where('machine_id', 'in', $value['machine_ids'])->column('machine_id'));

        }
        $machine_ids = DB::name('machine')->where('user_id', $user['pid'])->column('machine_id');
        $diff = array_merge(array_diff($arr, $machine_ids), array_diff($machine_ids, $arr));

        $result['not_yet_list'] = DB::name('machine')
            ->field('machine_id,machine_name')
            ->where('machine_id', 'in', $diff)
            ->select();
        return json_encode($result, JSON_UNESCAPED_UNICODE);
    }


    //提交存在于此分组的设备ID   选中的设备集合
    public function group_select()
    {
        $params = request()->param();
        $update = [
            'machine_ids' => $params['machine_ids']
        ];
        $result = DB::name('group')
            ->where('id', $params['group_id'])
            ->update($update);
        if ($result !== false) {
            $this->success();
        } else {
            $this->error();
        }
    }

    public function arr()
    {
        $a = [7];
        $b = [9];
        dump(array_merge($a,$b));
    }












}