<?php
/**
 * Created by PhpStorm.
 * User: GoldenBrother
 * Date: 2019/7/13
 * Time: 13:13
 */

namespace app\api\controller;
use think\Controller;
use think\Db;

header('Access-Control-Allow-Headers: Content-Type,Content-Length,Accept-Encoding,X-Requested-with, Origin, Authorization,access-control-request-headers'); // 设置允许自定义请求头的字段
//header("Access-Control-Max-Age", "1800");
header("Content-Type: text/html;charset=utf-8");
header('Access-Control-Allow-Methods:POST,GET,OPTIONS,DELETE'); // 允许请求的类型
header('Access-Control-Allow-Credentials: true'); // 设置是否允许发送 cookies
header('Access-Control-Allow-Origin:*');
class Server extends Controller
{

    public function register()
    {

        //设备首次链接服务器不知道自己的sn(machine_id) 需要从此接口获取审核结果 获取到machine_id后访问其他接口

        $log = file_get_contents('php://input');
        file_put_contents('./uploads/u3.txt', $log, FILE_APPEND);
        $data = json_decode($log,true);
        $machine = DB::name('machine')->where('uuid',$data['uuid'])->find();
        $check = DB::name('machine_check')->where('uuid',$data['uuid'])->find();
        if($machine){
            $P = $machine['machine_id'];
        }elseif($check){
            $P = '等待审核';
        }else{
            $insert =  [
                'uuid' => $data['uuid'],
                'px' => $data['px'],
                'type_id' => $data['type_id'],//福袋机为10
                'createtime' => time(),
            ];

            DB::name('machine_check')->insert($insert);
            $P = '已提交审核';
        }
        $result = [
            'C' => 'rmr',
            'P' => $P,
        ];
        return json_encode($result,JSON_UNESCAPED_UNICODE);
    }

    public function test()
    {
        $data = [
            'uuid' => 'abc',
            'px' => '1080*720',
            'type_id' => 1,
        ];
        halt(json_encode($data,JSON_UNESCAPED_UNICODE));
    }

    public function trimall($str)//删除空格
    {
        $oldchar=array(" ","　","\t","\n","\r");
        $newchar=array("","","","","");
        return str_replace($oldchar,$newchar,$str);
    }


    public function index()
    {
        $params = file_get_contents('php://input');

        $log = 'time:' . date('Y-m-d H:i:s', time()) . PHP_EOL . 'data:' . $params . PHP_EOL . PHP_EOL;
        file_put_contents('./uploads/log.txt', $log, FILE_APPEND);
        $params = $this->trimall($params);
        $params = json_decode($params, true);
//        halt($params);
        if (is_null($params)) {
            $msg = array(
                'errid' => 20000,
                'errmsg' => '协议号错误',
            );
            $data = array(
                'msg' => $msg,
                'machinesn' => intval($params['machinesn']),
            );
            return json_encode($data);
        }

        if ($params['msgtype'] == "receive_message") {


            $type = $params['msg']['C'] ? $params['msg']['C'] : "";//设备端口发送的命令字
            if($type !== 'lg'){
                $is_online = DB::name('machine')
                    ->where('machine_id', $params['machinesn'])
                    ->value('is_online');//状态:0=离线,1=在线,2=故障
                if ($type !== 'lg' && $is_online == 0) {
                    $msg = array(
                        'errid' => 10000,
                        'errmsg' => 'relogin',
                    );
                    $data = array(
                        'msg' => $msg,
                        'machinesn' => intval($params['machinesn']),
                        'cmd' => 'disconnect',
                    );
                    return json_encode($data);
                }
            }



            switch ($type) {
                case 'lg'://登录
                    echo $this->login($params);
                    break;
                case 'dc'://断开链接
                    echo $this->dc($params);
                    break;
                case 'ci'://投币信号
                    echo $this->ci($params);
                    break;
                case 'br'://请求支付二维码
                    echo $this->br($params);
                    break;
                case 'go'://出奖
                    echo $this->go($params);
                    break;
                case 'gl'://记录一局完整游戏
                    echo $this->gl($params);
                    break;
                case 'cr'://设备端补货
                    echo $this->cr($params);
                    break;
                case 'cs'://修改营业政策
                    echo $this->cs($params);
                    break;
                case 'rus'://响应软件升级
                    echo $this->rus($params);
                    break;
                case 'pq'://道具查询
                    echo $this->pq($params);
                    break;
                case 'rrb'://响应重启
                    echo $this->rrb($params);
                    break;
                case 'roc'://响应拉起配置界面
                    echo $this->roc($params);
                    break;
                case 'rui'://响应拉起配置界面
                    echo $this->rui($params);
                    break;
                case 'ds'://出货完成
                    echo $this->ds($params);
                    break;
                default:
                    $data = array(
                        'msgtype' => 'error',
                        'params' => array(
                            'errid' => 4003,
                            'errmsg' => 'msgtype error',
                        ),
                    );

                    $data = json_encode($data, JSON_UNESCAPED_UNICODE);
                    echo $data;
            }
        }elseif($params['msgtype'] == "connection_lost"){//链接服务器发送的通知设备下线通知
            DB::name('machine')->where(['machine_id'=>$params['machinesn']])->update(['is_online'=>0,'last_offline'=>time()]);
        }
    }


    public function login($params)
    {
//        $json = [
//            'P' => [
//                'sn' => 11000,
//            ],
//        ];
//        halt(json_encode($json,JSON_UNESCAPED_UNICODE));
        $data = $params['msg']['P'];
        $machine = DB::name('machine')->find($data['sn']);
        $s = json_encode($data);
        $sign = strtoupper(sha1($s));

//        if(!$machine || ($sign != $params['msg']['O'])){
//
//            $msg = [
//                'C' => 'rlg',
//                'P' => [
//                    'ei' => 10002,
//                ],
//            ];
//            $result = [
//                'msg' => $msg,
//                'machinesn' => $machine['machine_id'],
//                'cmd' => 'disconnect',//错误
//            ];
//        }else{
            $result = [];
            $save['position_lng'] = isset($data['lg'])?$data['lg']:$machine['position_lng'];//经度
            $save['position_lat'] = isset($data['lt'])?$data['lt']:$machine['position_lat'];//维度
            $save['address'] = isset($data['ad'])?$data['ad']:$machine['address'];//高德地图定位
            $save['soft_version'] = isset($data['sv'])?$data['sv']:$machine['soft_version'];//软件版本
            $upload_version = DB::name('app_version')->find($machine['upload_version_id']);
            //
            if(!is_null($machine['upload_version_id'])){
                //存在升级任务  返回升级协议
                $result['nv'] = $upload_version['name'];//版本名称
                $result['ad'] = $upload_version['url'];//版本地址
                $result['ps'] = $upload_version['size'];//版本大小
                $result['dd'] = $upload_version['md5'];//版本md5
            }

//            if($save['soft_version'] == $upload_version['name']){
//                //版本正确
//                DB::name('machine')->where('machine_id',$machine['machine_id'])->update(['upload_version_id'=>null]);
//            }else{
//                $save['soft_version'] = $upload_version['url'];
//            }
//            $conf['stock'] = isset($data['gi'])?$data['gi']:$machine['stock'];//当前库存
//            $save['game_price'] = isset($data['gp'])?$data['gp']:$machine['game_price'];//单次游戏扫码价格
//            $save['coins'] = isset($data['co'])?$data['co']:$machine['coins'];//单次游戏投币数量
//            $save['odds'] = isset($data['od'])?$data['od']:$machine['odds'];//出卡分数
            $save['last_online'] = time();//最后一次登录时间
            $save['is_online'] = 1;
//                $stock = DB::name('machine_conf')->where('machine_id',$machine['id'])->value('stock');
//                if($stock != $data['gi']){
//                    //库存不一致  发送库存数量
//                    $O['gi'] = $stock;
//                }
//                if($machine['game_price'] != $data['gp']){
//                    //游戏价格不一致
//                    $O['gp'] = $machine['game_price'];
//                }
//                if($machine['coins'] != $data['co']){
//                    //投币数量不一致
//                    $O['co'] = $machine['coins'];
//                }
//                if($machine['odds'] != $data['od']){
//                    //出卡分数不一样
//                    $O['od'] = $machine['odds'];
//                }
//            $save['last_login'] = time();

            DB::name('machine')->where("machine_id",$data['sn'])->update($save);
            $arr = DB::name('machine_conf')
                ->where('machine_id',$machine['machine_id'])
                ->where('number','>',0)
                ->column('goods_id');

            $conf = array_unique($arr);//去重
            //排除为null的
            foreach( $conf as $k=>$v){
                if( !$v )
                    unset( $conf[$k] );
            }

            $conf = array_values($conf);

            $res = [];
            foreach($conf as $key => $value){
                $res[$key]['gi'] = $value;
                $res[$key]['gp'] = DB::name('client_goods')->where('goods_id',$value)->value('goods_price');
                $res[$key]['gn'] = DB::name('machine_conf')->where('goods_id',$value)->where('machine_id',$data['sn'])->sum('number');
                $res[$key]['ts'] = DB::name('client_goods')->where('goods_id',$value)->value('updatetime');
            }

//        dump($res);
//            return json_encode($res,JSON_UNESCAPED_UNICODE);
//            $res['a']['lu'] = DB::name('material')->where('id',$machine['logo_id'])->value('url');
//            $res['b']['vu'] = DB::name('material')->where('id',$machine['video_id'])->value('url');
        $result['lu'] = DB::name('material')->where('id',$machine['logo_id'])->value('url');
        $result['vu'] = DB::name('material')->where('id',$machine['video_id'])->value('url');
        $result['sl'] = $res;
        if(!is_null($machine['user_id'])){//是否被绑定
            $result['dp'] = 0;
        }else{
            $result['dp'] = 1;
        }



//        $res['vs'] = DB::name('machine')
//        $res = array_values($res);
//        $result = array_values($result);
//            DB::name('machine_conf')->where('machine_id',$data['sn'])->update($conf);
//            $res = array_values($res);//将对象转为数组
//        dump($res);

            $msg = [
                'C' => 'rlg',
                'P' => $result,
//                    'O' => $O,
            ];
//            return json_encode($msg);
            $result = [
                'msg' => $msg,
                'machinesn' => $machine['machine_id'],
            ];
//        }
        return json_encode($result,JSON_UNESCAPED_UNICODE);

    }


    //断开连接
    public function dc($params)
    {
        $machinesn = $params['machinesn'];
        $result = [
            'msg' => null,
            'machinesn' => $machinesn,
            'cmd' => 'disconnect',//错误
        ];
        $update = [
            'last_offline' => time(),
            'is_online' => 0
        ];

        DB::name('machine')->where('machine_id',$machinesn)->update($update);
        return json_encode($result,JSON_UNESCAPED_UNICODE);
    }

    //注意 : 需要映射外网才能访问
    public function notify()
    {
        $log = file_get_contents('php://input');
//        file_put_contents('./uploads/notify.txt', $log, FILE_APPEND);
        //私钥
        $private_Key = "MIIEugIBADANBgkqhkiG9w0BAQEFAASCBKQwggSgAgEAAoIBAQDAVCOCDnslcJceuxavrLswc9WPU9b7yBTVadL8dPVD+Qqpd1xcFQm1FyxIZRbgEAV4MT8oSdhMYqV7bKSyt5PrT9oU5bzJytdJQwxe3eX7WYMldHNv9EHr1uJAQhgWPwqRndRoKHiCxcgy6ps10HGE8Qj0IsAyTL/Og6idcYekVlbVj9w0kotq0kPmRkda0wS8lYD6mH6qq9C36FnEWV3qVKdcO/hJ2AG9e5m75HuAU99BbfwYr0uStZcimpYLtOj0/Cn4v5B//Gthc/Cgf3LJ5FuiKmPKoxfnNoB4TB5ALRcDaovacT7SsMhXFwbfRkt2OfZVYqFtiiuyzUYefU+ZAgMBAAECgf90cn0NQbdN892Lvbr+opazv26OWTTRPVNf47LbJ/VYMnFCKgLBvfsiqeUl8A7pmsm0/BxBSHStywxmrmEJ1By7XJ2uCWtEwouW0AGtbqzQgmHlS5yZLEq9gF18iogK8CB2ChmQ9vAAPb/5FBLlgk85Lrc9Gc1EpzN61jxBF3wJAy/2AL0Q+NYpq6TOWXWoEYFnjQtStq7AaJOh4/K0RhmFvVapyXL4i7fWddWW2jZ//AzIOe5ok5VD7YdxPKXRSxCjlS5JTDVDAZ3KY72i4+oVpeqffF5XR3MdAai+66wHI3eH0QKf6Qz56wyH9yFwSzBEValeWV29SP+MOhjcqI0CgYEA8NxL2kzVh8Kdygkm9pJB3Gxd9ZUPw9oKEdWusZSKLvIs36KPY6qYB5xsF03lmZoe0HvtBLUL03J/D2BVDChHbv2pT5wxKkHU0vkw5ojRiEnMpWbvE6skndeZEA1DD6E4+RSL10siAjXoSKifHzaEu7s1Km30hWqsRBdzXir3gNMCgYEAzGrpqkFnSnQq4sepnL9v247ikjYJi80tly1tjMdkJww9exX1EOSgSMXtXgMof99GUTipFBHe8PRtX4I+yI9K/I4zxRtaYgP+gZ7BVgYe98E6ZNrGD/8LNbJfDbsBwrtYDE/Y23hRbLJOPN/+PocF5LA+uJMuIni1DDfh7MJgymMCgYB+cskHtCqt+UgpVyCzdhlJhULWuQjrwz5iGpJ5/AeHmfBg/9DTfC4QYNiGa4jMWRMwVL8cJ4gr3AJEqkg797F43YbTmqZdDu6SS+yWOuH18PiVJTMCWmkAzL04ph28yOFGMrkvr+wMyQxHiO7wzghlHmVM/yjOGjCSFtWkbF4/rQKBgDI+8VKdIvOFHGmD5GgYEjmopH6F89C+TT+EthHNjQugEZiorAVL/S4GILNkGVddHV6ni7/YKLGXky7Px/jqZ+cuWQFRGOVQ0AUybZlkhcYmY+EYeWjDKxE21/B7EBK6lAjqs4Y2y+To6xxBfrAF5mfw/mnGG6fzfaUUM19L5Bi7AoGAVI8iQ5NP0iZtCdSnQPkjKZMDifwVLwdfcaEjRYop7cfe9IYak+QPC/LQGkjKH5G8t2OAsbC9wExwM3Lhd9DKRBDlqcCPxaTD5Wxq1UDXDcARWarWOpDF7l3Gt7StAsGo9QRb8d0w9CRFLCDzxj1CKGwVz12XfrpL/OdVqtHe/EI=";
        //公钥
        $public_Key = "MIIBIjANBgkqhkiG9w0BAQEFAAOCAQ8AMIIBCgKCAQEA6p0XWjscY+gsyqKRhw9MeLsEmhFdBRhT2emOck/F1Omw38ZWhJxh9kDfs5HzFJMrVozgU+SJFDONxs8UB0wMILKRmqfLcfClG9MyCNuJkkfm0HFQv1hRGdOvZPXj3Bckuwa7FrEXBRYUhK7vJ40afumspthmse6bs6mZxNn/mALZ2X07uznOrrc2rk41Y2HftduxZw6T4EmtWuN2x4CZ8gwSyPAW5ZzZJLQ6tZDojBK4GZTAGhnn3bg5bBsBlw2+FLkCQBuDsJVsFPiGh/b6K/+zGTvWyUcu+LUj2MejYQELDO3i2vQXVDk7lVi2/TcUYefvIcssnzsfCfjaorxsuwIDAQAB";
        $source = $_REQUEST["response"];
//        $source = "ELZmUQCJ1NxqSBJ6m_J5tfCUa76EgO2DLb31qw9hQHd5KugnB8yB3kGh0OV-kQwXFmEo0G30LNn_ewthcil1AAaXdNPHe8vHKYBlJQmXZ9z7qnFk41uFpL-6uZRrvOUrNSg4MPzeRZHvLCkpa9rCe-le6vaOl63Nr-wyA-KCIToUxMpLHUYnHG-h5cuOOk08YFqU0Mrjwu2ce9Tczfh3vL2YnELUSHKzUadu_3PYCsnSvl_NiHCF1d7Wc8nIH1l6nftE5hwPcRnC64u3PUAssDTvrF9it3tcHel7ngW3_DJVA8agZJPGKJACw2_yVwAgtYuPcGznG-GUwsXxp294hg%24Ewm3Ehpk9fvosxlV2r-I5Fbw9dPv0wgjFc4RcHDxiN2YeZc3AU86tvp_nB7XGykY2Dv5P-d5YZsv0ET_9GLoXS4IMEBdO2eXNFZpjGpidW6Z3HTWljZldYfOTGGBfFvVHhYw9gosrCKbXeKIlQLa2ZWQer4IJw0M-TBIoRcFYwoWZ3sbD8vsFCSQxLZ6xMU0_tdXvUNKdTYeoRDDax751N8mfV8RxlrvlBaDMVqURsLipqTNOBA8Ss3YGH7d8un8R9SCDAq7uEcWwBLBkXO6c2qfHOJJqsoeevecETEF9TUSjX8lfFn1hVRCtyMHm5MOx1Ifk1JCGAXnmAwiQQ1uk8Xv1a4lJZWX9CSYKCssdBgcRQMf23Gg2Xu01lcC5ENRjGxxv6Jo_MBmgVV8MAseOa9suxywYK1RYuHPYv-7WY103t_3dSkK9O2lRv4JdtVm0hXghK8sg52Ops4cDEo6J6LyCTU5_yWfB1CM6dy22MQwhqgr0Nrop_Mx9HVC1AXov_W3ePAC5AByvWUyegsZUk4SzBkk3sWVeiTn0XyQpANe3zrA5KVUtYEdLeKIlbI3Pd4HIV_ItHOFBiVZGYX3pcS8EuZGJyLpVxhwq5xtiTVsitN600L4zK9cjaJmAnXVdsxP3272Nd3u4_AJ3UbaJK3EjzC4gWdSBvk52xH_cZbkhFUmpWWjsCfSciAQo12Ry8A-V8ARgqQWb4HClgMwNlW-xeSd19GWot-83d7Wxfti7PuQwGskz49a40HD_i0SSCD9By2_oIyMLjb4vMwPT7PttSWrW9hyuW6ladS3dtvf91stWusnwVad9vsDpWqj4OiRwAu5r-PRsMq5VzDMpdsJ_e0R1WFTbnU820vk-RPz1UZ3cwobFMxn08CMfE1Yo1UQzkawcDq26GBUhs5ul47wGj1fnZb3NvxIKjcAgDA10zAtfF7FVDvh-PJdvRZW5pcMZVASj1Z3twNODFsH1MAbUjJDsMfEX8DLMIHMkeCMwvEqIVz9lFxNsB8vqL-H4LXyXcXyIXiQN84lrrmFbl9Bjzf1JV0JhW_YO14_78mqLc3rwaZRbROk-mymt1VxWdc7G_CNAm6EpkEB80KzqhCzRLmfA0NyUfDaWh0xt5INo27ol-TJaCxq-OgdLYVjnDdMxYWX5jZUyChrvMEyrLledPQDJmMx_7cyghaxRcvAy3-v2U02Wfg_8Y3VKizDI8fUfL2Jwv5sK8ll9k5WCJu7Z5x5hi81ULmk175AKSPxdfWVe977bP27BO93g4yFGvJek-8UrHjl9xvHFvumqQ%24AES%24SHA256&customerIdentification=OPR%3A10026912451response=ELZmUQCJ1NxqSBJ6m_J5tfCUa76EgO2DLb31qw9hQHd5KugnB8yB3kGh0OV-kQwXFmEo0G30LNn_ewthcil1AAaXdNPHe8vHKYBlJQmXZ9z7qnFk41uFpL-6uZRrvOUrNSg4MPzeRZHvLCkpa9rCe-le6vaOl63Nr-wyA-KCIToUxMpLHUYnHG-h5cuOOk08YFqU0Mrjwu2ce9Tczfh3vL2YnELUSHKzUadu_3PYCsnSvl_NiHCF1d7Wc8nIH1l6nftE5hwPcRnC64u3PUAssDTvrF9it3tcHel7ngW3_DJVA8agZJPGKJACw2_yVwAgtYuPcGznG-GUwsXxp294hg%24Ewm3Ehpk9fvosxlV2r-I5Fbw9dPv0wgjFc4RcHDxiN2YeZc3AU86tvp_nB7XGykY2Dv5P-d5YZsv0ET_9GLoXS4IMEBdO2eXNFZpjGpidW6Z3HTWljZldYfOTGGBfFvVHhYw9gosrCKbXeKIlQLa2ZWQer4IJw0M-TBIoRcFYwoWZ3sbD8vsFCSQxLZ6xMU0_tdXvUNKdTYeoRDDax751N8mfV8RxlrvlBaDMVqURsLipqTNOBA8Ss3YGH7d8un8R9SCDAq7uEcWwBLBkXO6c2qfHOJJqsoeevecETEF9TUSjX8lfFn1hVRCtyMHm5MOx1Ifk1JCGAXnmAwiQQ1uk8Xv1a4lJZWX9CSYKCssdBgcRQMf23Gg2Xu01lcC5ENRjGxxv6Jo_MBmgVV8MAseOa9suxywYK1RYuHPYv-7WY103t_3dSkK9O2lRv4JdtVm0hXghK8sg52Ops4cDEo6J6LyCTU5_yWfB1CM6dy22MQwhqgr0Nrop_Mx9HVC1AXov_W3ePAC5AByvWUyegsZUk4SzBkk3sWVeiTn0XyQpANe3zrA5KVUtYEdLeKIlbI3Pd4HIV_ItHOFBiVZGYX3pcS8EuZGJyLpVxhwq5xtiTVsitN600L4zK9cjaJmAnXVdsxP3272Nd3u4_AJ3UbaJK3EjzC4gWdSBvk52xH_cZbkhFUmpWWjsCfSciAQo12Ry8A-V8ARgqQWb4HClgMwNlW-xeSd19GWot-83d7Wxfti7PuQwGskz49a40HD_i0SSCD9By2_oIyMLjb4vMwPT7PttSWrW9hyuW6ladS3dtvf91stWusnwVad9vsDpWqj4OiRwAu5r-PRsMq5VzDMpdsJ_e0R1WFTbnU820vk-RPz1UZ3cwobFMxn08CMfE1Yo1UQzkawcDq26GBUhs5ul47wGj1fnZb3NvxIKjcAgDA10zAtfF7FVDvh-PJdvRZW5pcMZVASj1Z3twNODFsH1MAbUjJDsMfEX8DLMIHMkeCMwvEqIVz9lFxNsB8vqL-H4LXyXcXyIXiQN84lrrmFbl9Bjzf1JV0JhW_YO14_78mqLc3rwaZRbROk-mymt1VxWdc7G_CNAm6EpkEB80KzqhCzRLmfA0NyUfDaWh0xt5INo27ol-TJaCxq-OgdLYVjnDdMxYWX5jZUyChrvMEyrLledPQDJmMx_7cyghaxRcvAy3-v2U02Wfg_8Y3VKizDI8fUfL2Jwv5sK8ll9k5WCJu7Z5x5hi81ULmk175AKSPxdfWVe977bP27BO93g4yFGvJek-8UrHjl9xvHFvumqQ%24AES%24SHA256&customerIdentification=OPR%3A10026912451";
        $json = decrypt($source, $private_Key, $public_Key);
        $data = json_decode($json, true);
        $order = DB::name('order')->where(['unique_order_id'=>$data['uniqueOrderNo']])->find();
        if($order['status'] != 0){
            exit();
        }
        /*上线需要解除注释*/
//        $unique = Db::connect('db2')->name('wk_order')->where(['unique_order_id' => $data['uniqueOrderNo'], 'order_status' => 2])->find();
//        if($unique){
//            exit();
//        }
//        Db::connect('db2')->name('wk_order')->where(['unique_order_id' => $data['uniqueOrderNo'], 'order_status' => 1])->setField('order_status', 2);//这个需要老大配置DB2的链接
//        Db::connect('db2')->name('wk_order')->where(['unique_order_id' => $data['uniqueOrderNo'], 'order_status' => 1])->update(['order_status'=>2]);//这个需要老大配置DB2的链接
        $log = $data['uniqueOrderNo'];
        file_put_contents('./uploads/notify.txt', $log, FILE_APPEND);
//            DB::content('db2')->name('wk_order')->where(['unique_order_id' => $data['uniqueOrderNo'], 'order_status' => 1])->setField('order_status', 2);//修改pay支付平台订单数据
        DB::name('order')->where('unique_order_id',$data['uniqueOrderNo'])->update(['status'=>1,'paytime'=>time()]);//修改订单状态
        $stat_period = strtotime(date("Y-m-d"),time());
        DB::name('machine_day_statistics')->where("machine_id",$order['machine_id'])->where('stat_period',$stat_period)->setInc('online_income',$order['amount']);//修改营业数据
        $json = array(
            'C' => 'or',
            'P' => ror($order['id']),
            'O' => $data['uniqueOrderNo']
        );
        $msg = array(
            'msg'=>$json,
            'msgtype'=>'send_message',
            'machinesn'=>intval($order['machine_id']),
        );
        //halt($msg);
//        $url = 'https://www.goldenbrother.cn:23233/account_server';
        $url = 'http://www.goldenbrother.cn:33333/account_server';
        $res = post_curls($url,$msg);

        $log = 'time:' . date('Y-m-d H:i:s', time()) . PHP_EOL . 'data:' . json_encode($json,JSON_UNESCAPED_UNICODE) . PHP_EOL . PHP_EOL;
        file_put_contents('./uploads/callback.txt', $log, FILE_APPEND);

        //写入command_machine
        $add = [
            'machine_id' => $order['machine_id'],
            'send_time' => time(),
            'msgtype' => 'or',
            'content' => $data['uniqueOrderNo'],
        ];
        DB::name('command_machine')->insert($add);
        DB::name('order')->where('unique_order_id',$data['uniqueOrderNo'])->update(['conf_list'=>json_encode($json['P'],JSON_UNESCAPED_UNICODE)]);//补充conf_list

        echo "SUCCESS";

    }

    //返回支付二维码
    public function br($params)
    {
        $machinesn = $params['machinesn'];
        $data = $params['msg']['P'];
        $O = $params['msg']['O'];//设备端生成的唯一订单号  用于识别支付成功信息
        foreach ($data['gl'] as $key => $value){
            $number = DB::name('machine_conf')->where('goods_id',$value[0])->sum('number');
            if($number < $value[1]){
                $msg = [
                    'C' => 'rbr',
                    'P' => 'number error',
                    'O' => $O,//将客户端生成的订单号传回客户端 用于识别请求
                ];
                $result = [
                    'msg' => $msg,
                    'machinesn' => $machinesn,
                ];
                return json_encode($result,JSON_UNESCAPED_SLASHES);
            }
        }
        if($data['pm'] == 'wx'){
            $data['pm'] = 2;
        }
        if($data['pm'] == 'ap'){
            $data['pm'] = 1;
        }
        $post = [
            'type' => $data['pm'],
            'user_id' => 1,//易宝的商户ID
            'amount' => floatval($data['tl']),
            'goods_name' => '收款',
            'sn' => null,
//                'notify' => "http://" .  $_SERVER['HTTP_HOST'] . "/api/server/notify",
//            'notify' => "http://liujunfeng.imwork.net:28246/api/server/notify",
            'notify' => "http://blind.goldenbrother.cn/api/server/notify",
            'goodsDesc' => null,
        ];
        $url = "http://www.wakapay.cn/index.php/api/yeepay/pay";
//            $url = "http://192.168.1.144:1161/api/yeepay/pay";
        $return = json_curl($url,$post);
//halt($return);
        $arr = json_decode($return,true)['data'];

        $result = [
            'qr' => $arr['url'],
            'uid' => $arr['unique_order_id'],//服务器生成的支付唯一编码
        ];

//            halt($this->qrcode($result['qr']));

        $insert = [
            'unique_order_id' => $result['uid'],
            'amount' => $post['amount'],
            'type' => $post['type'],//1支付宝2微信
            'machine_id' => $machinesn,
            'createtime' => time(),
            'serial_number' => $O,
            'goods_list' => json_encode($data['gl'],JSON_UNESCAPED_UNICODE),
        ];

        DB::name('order')->insert($insert);
//            halt($result);
        $msg = [
            'C' => 'rbr',
            'P' => $result['qr'],
            'O' => $O,//将客户端生成的订单号传回客户端 用于识别请求
        ];
        $result = [
            'msg' => $msg,
            'machinesn' => $machinesn,
        ];
//            halt($result);
        return json_encode($result,JSON_UNESCAPED_SLASHES);

    }


    //响应重启
    public function rrb($params)
    {
//            $data = $params['msg']['P'];
        $O = $params['msg']['O'];
        $save = [
            'receive_time' => time(),
            'status' => 1,
        ];
        DB::name('command_machine')->where('commandid',$O)->update($save);
    }

    //响应拉起配置页面
    public function roc($params)
    {
        $O = $params['msg']['O'];
        $save = [
            'receive_time' => time(),
            'status' => 1,
        ];
        DB::name('command_machine')->where('commandid',$O)->update($save);
    }

    //出货完成响应
//    public function ds($params)
//    {
//        $O = $params['msg']['O'];
//        $P = $params['msg']['P'];
//        DB::name('order')->where('unique_order_id',$O)->update(['error'=>0]);
//
//        $order = DB::name('order')->where('unique_order_id',$O)->find();
//
//        $k = 0;
//        foreach($P as $key => $value){
//            $arr[$k] = array_values($value);
//            $k++;
//        }//处理成没有键值的二维数组
//
//        foreach ($arr as $key => $value) {
//            if (!in_array($value, json_decode($order['goods_list'],true))) {
//                $error[] = $value;
//            }
//        }//两个数组不同
//        if(empty($error)){//正常
//            $update = [
//                'error' => 1,
//            ];
//        }else{//部分成功
//            $update = [
//                'error' => 2,
//                'receive_msg' => json_encode($error,JSON_UNESCAPED_UNICODE),
//            ];
//        }
//        DB::name('order')->where('unique_order_no',$O)->update($update);
//    }

        public function ds($params)
        {

            $O = $params['msg']['O'];
            $P = $params['msg']['P'];
            DB::name('order')->where('unique_order_id',$O)->update(['error'=>0]);
            DB::name('command_machine')->where('content',$O)->update(['status'=>1,'receive_time'=>time()]);


            $order = DB::name('order')->where('unique_order_id',$O)->find();

//            if($P == json_decode($order['goods_list'],true)){
            if($P == json_decode($order['conf_list'],true)){
                $update = [
                    'error' => 1,
                ];
            }else{
                $update = [
                    'error' => 2,
                    'receive_msg' => json_encode($P,JSON_UNESCAPED_UNICODE),
                ];
            }

            DB::name('order')->where('unique_order_id',$O)->update($update);
        }

        //设备响应软件升级
        public function rus($params)
        {
            $O = $params['msg']['O'];
            $P = $params['msg']['P'];
            $P['ei'] = isset($P['ei'])?$P['ei']:null;//错误代码
            $P['ts'] = isset($P['ts'])?$P['ts']:null;//有值代表更新成功,更新后的版本号

            DB::name('command_machine')->where('id',$O)->update(['status'=>1,'receive_time'=>time(),'content'=>$P['ei']]);
            if(!is_null($P['ts'])){
                DB::name('machine')->where('machine_id',$params['machinesn'])->update(['soft_version'=>$P['ts'],'upload_version_id'=>null]);
            }
        }

}