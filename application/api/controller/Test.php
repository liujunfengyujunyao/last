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
class Test extends Api
{
    //    protected $noNeedLogin = ['*'];
    protected $noNeedLogin = ['*'];
    protected $noNeedRight = ['*'];
    public function __construct()
    {

        parent::__construct();//调用父类的构造函数 不然$this->error会报错
        $this->params = request()->param();

//            halt(json_decode($this->params,true));
        $token = request()->param('token');
        $this->user = DB::name('user')->where('token',$token)->find();

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

        $this->params = [
            'machine_id' => 10088,
            'new' => [
                [
                    'lat' => 1.1,
                    'lng' => 1.1
                ],
                [
                    'lat' => 2.2,
                    'lng' => 2.2
                ],
                [
                    'lat' => 3.3,
                    'lng' => 3.3
                ],
            ]
        ];
//        halt(json_encode($this->params,JSON_UNESCAPED_UNICODE));


        $insert = [];
        $time = time();
        $max_location = DB::name('machine_conf')
            ->where('machine_id',$this->params['machine_id'])
            ->max('location');//0or最大仓位号
        foreach($this->params['new'] as $key => $value){
            $insert['location'] = $key+1+$max_location;
            $insert['machine_id'] = $this->params['machine_id'];
            $insert['create_time'] = $insert['update_time'] = $time;
            $insert['lat'] = $value['lat'];
            $insert['lng'] = $value['lng'];
            $result = DB::name('machine_conf')->insert($insert);
        }
        if($result){
            $this->success();
        }else{
            $this->error();
        }




    }

    public function machine_conf_edit()
    {

    }

    public function tx()
    {
        $insert = [
            'goods_name' => '第一件商品(可乐)',
            'type_id'    => 1,
            'content'    => '测试覆盖',
        ];
        $insert['id'] = 1;

        $data = json_encode([
            'name' => $insert['goods_name'],
            'category' => DB::name('client_goods_type')
                ->where('type_id',$insert['type_id'])
                ->value('type_name'),
            'description' => $insert['content'],
        ],JSON_UNESCAPED_UNICODE);
        $filename = "./goods_info/" . $insert['id'] . ".txt";
        file_put_contents($filename,$data);



//        file_put_contents($filename,$data);
    }

    public function gl()
    {
        $json = [[1,1],[2,1]];
        halt($json);
        $insert = [
            'unique_order_id' => 12121,
            'amount' => 1,
            'type' => 1,//1微信2支付宝
            'machine_id' => 111,
            'createtime' => time(),
            'serial_number' => 121,
            'goods_list' => json_encode($json,JSON_UNESCAPED_UNICODE),
        ];
        DB::name('order')->insert($insert);
    }

//    public function gls()
//    {
//        $gl = DB::name('order')->where('id',1)->find();
//        $data = json_decode($gl['goods_list']);
//        $count = count($data);//数组个数,循环次数
//        $result = [];
//        for($for=0;$for<$count;$for++){
//            $conf = DB::name('machine_conf')
//                ->where('machine_id',$gl['machine_id'])
//                ->where('goods_id',$data[$for][0])//goods_id  [1]number
//                ->where('number','>',0)
//                ->find();
//            if($conf['number'] < $data[$for][1]){//寻找下一个仓位
//                $continue = 0;
//                DB::name('machine_conf')
//                    ->where('id',$conf['id'])
//                    ->setDec('number',$conf['number']);
//                $remainder = $data[$for][1] - $conf['number'];//余数
//                if($continue = 0)//如果还是有余数
//                $value[count($value)] = [仓位号，数量];
//                $continue++;
//                continue;
//                else{
//                    $value[count($value)]＝[仓位号，数量];
//                }
//
//            }else{
//                $value = [$data[$for][0],$data[$for][1]];
//            }
//            $result[$for] = $value;
//
//
//
//        }
//
//
//
//
//        $result = [
//            'C' => 'or',
//            'P' => $P,
//            'O' => $gl['serial_number'],
//        ];
//
//    }

    public function shuzu()
    {
        $order = DB::name('order')->where('id',1)->find();//假设一个订单
        $data = [[
            [1,8],
            [2,11],
        ]];
        $remainder = 0;//数组多余元素
        foreach($data as $key => $value){
            $conf = DB::name('machine_conf')
                ->where('goods_id',$value[$key][0])
                ->where('machine_id',$order['machine_id'])
                ->where('number','>=',$value[$key][1])
                ->find();
            if($conf){//如果一个仓位可以将货全部出出
                DB::name('machine_conf')
                    ->where('id',$conf['id'])
                    ->setDec('number',$value[$key][1]);
                $result[$key+$remainder] = [
                    'ri' => $conf['location'],
                    'pm' => $conf['lat'] . ',' . $conf['lng'],
                    'num' => $value[$key][1],
                ];
            }else{

                for($a=0;$a<100;$a++){

                    $remainder++;
                    $machine_conf = DB::name('machine_conf')
                        ->where('goods_id',$value[$key][0])
                        ->where('machine_id',$order['machine_id'])
                        ->where('number','>',0)
                        ->find();

                    if($value[$key][1] - $machine_conf['number'] > 0){//如果依然不够
                        DB::name('machine_conf')
                            ->where('id',$machine_conf['id'])
                            ->setDec('number',$machine_conf['number']);
                        $value[$key][1] = $value[$key][1] - $machine_conf['number'];
                        $result[$key+$remainder] = [
                            'ri' => $machine_conf['location'],
                            'pm' => $machine_conf['lat'] . ',' . $machine_conf['lng'],
                            'number' => $machine_conf['number'],
                        ];
                        continue; //进入下次for循环
                    }else{
                        DB::name('machine_conf')
                            ->where('id',$machine_conf['id'])
                            ->setDec('number',$value[$key][1]);
                        $result[$key+$remainder] = [
                            'ri' => $machine_conf['location'],//仓位号
                            'pm' => $machine_conf['lat'] . ',' . $machine_conf['lng'],//仓位坐标
                            'number' => $value[$key][1],//数量
                        ];
                        break;
                    }

                }

            }
        }
        halt($result);
    }


    public function shuzu2($arr)
    {
        $order = DB::name('order')->where('id',1)->find();//假设一个订单
        $data = [
            [1,8],
            [2,11],
        ];
        $remainder = 0;//数组多余元素
        foreach($data as $key => $value){
            $conf = DB::name('machine_conf')
                ->where('goods_id',$value[0])
                ->where('machine_id',$order['machine_id'])
                ->where('number','>=',$value[1])
                ->find();
            if($conf){//如果一个仓位可以将货全部出出

                DB::name('machine_conf')
                    ->where('id',$conf['id'])
                    ->setDec('number',$value[1]);
                $result[$remainder] = [
                    'ri' => $conf['location'],
                    'pm' => $conf['lat'] . ',' . $conf['lng'],
                    'num' => $value[1],
                ];
                $remainder++;
            }else{
                for($a=0;$a<10;$a++){
                    $machine_conf = DB::name('machine_conf')
                        ->where('goods_id',$value[0])
                        ->where('machine_id',$order['machine_id'])
                        ->where('number','>',0)
                        ->find();
                    if($machine_conf['number']>=$value[1]){//够了  终止循环 开始下个商品遍历
                        DB::name('machine_conf')
                            ->where('id',$machine_conf['id'])
                            ->setDec('number',$value[1]);
                        $result[$remainder] = [
                            'ri' => $machine_conf['location'],
                            'pm' => $machine_conf['lat'] . ',' . $machine_conf['lng'],
                            'number' => $value[1],
                        ];
                        $remainder++;
                        break;
                    }else{
                        $for_conf = DB::name('machine_conf')
                            ->where('goods_id',$value[0])
                            ->where('machine_id',$order['machine_id'])
                            ->where('number','>',0)
                            ->find();
//                        dump($for_conf);
                        DB::name('machine_conf')
                            ->where('id',$for_conf['id'])
                            ->setDec('number',$for_conf['number']);
                        $value[1] = $value[1] - $for_conf['number'];

                        $result[$remainder] = [
                            'ri' => $machine_conf['location'],
                            'pm' => $machine_conf['lat'] . ',' . $machine_conf['lng'],
                            'number' => $machine_conf['number'],
                        ];
                        $remainder++;
                        continue;
                    }
                }
                continue;
            }
        }

        return $result;
    }

    public function b()
    {
        $json = json_encode([
            'C' => '1',
            'p' => 1,
        ],JSON_UNESCAPED_UNICODE);
        $log = 'time:' . date('Y-m-d H:i:s', time()) . PHP_EOL . 'data:' . $json . PHP_EOL . PHP_EOL;
        file_put_contents('./uploads/callback.txt', $log, FILE_APPEND);


    }

    public function post()
    {
        $msg = "test";
        $url = 'http://www.goldenbrother.cn:33333/account_server';
        $res = post_curls($url,$msg);
        halt($res);
    }

    public function js()
    {
        halt(ror(8));
    }

    public function xm()
    {
        $xml_data = "<?xml version=\"1.0\" encoding=\"utf-8\"?>
<soap:Envelope xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\" xmlns:soap=\"http://schemas.xmlsoap.org/soap/envelope/\">
  <soap:Body>
    <selectVendorPUInfoJson xmlns=\"http://tempuri.org/\">
      <FCTCode>CG19071529</FCTCode>
      <FVendorCode>120032</FVendorCode>
    </selectVendorPUInfoJson>
  </soap:Body>
</soap:Envelope>";

//        $xml_data = simplexml_load_string($xml_data);
//        print_r($xml_data);
        $url = 'http://272h935a67.qicp.vip:18888/YXInterface/YXWebService.asmx'; //接收xml数据的文件
        $header[] = "Content-type: text/xml";      //定义content-type为xml,注意是数组
        $ch = curl_init ($url);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
        curl_setopt($ch, CURLOPT_HTTPHEADER,$header);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch,CURLOPT_POSTFIELDS, $xml_data);
        $response = curl_exec($ch);
        if(curl_errno($ch)){
            $this->error('网络错误');
            print curl_error($ch);
        }

        curl_close($ch);
//        halt($response);
        $data = xml_to_array($response);
//        halt();

//        halt($data);
        $order = json_decode($data['selectVendorPUInfoJsonResponse']['selectVendorPUInfoJsonResult'],true);
        halt($order);
    }

    public function l()
    {
        $xml_data = "<?xml version=\"1.0\" encoding=\"utf-8\"?>
<soap:Envelope xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\" xmlns:soap=\"http://schemas.xmlsoap.org/soap/envelope/\">
  <soap:Body>
    <selectPUInfoJson xmlns=\"http://tempuri.org/\">
      <FCTCode>CG19071529</FCTCode>
    </selectPUInfoJson>
  </soap:Body>
</soap:Envelope>";

//        $xml_data = simplexml_load_string($xml_data);
//        print_r($xml_data);
        $url = 'http://272h935a67.qicp.vip:18888/YXInterface/YXWebService.asmx'; //接收xml数据的文件
        $header[] = "Content-type: text/xml";      //定义content-type为xml,注意是数组
        $ch = curl_init ($url);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
        curl_setopt($ch, CURLOPT_HTTPHEADER,$header);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch,CURLOPT_POSTFIELDS, $xml_data);
        $response = curl_exec($ch);
        if(curl_errno($ch)){
            $this->error('网络错误');
            print curl_error($ch);
        }

        curl_close($ch);
//        halt($response);
        $data = xml_to_array($response);
//        halt($data);

//        halt($data);
        $order = json_decode($data['selectPUInfoJsonResponse']['selectPUInfoJsonResult'],true);
        halt($order);
    }

    public function p()
    {
        $xml_data = "<request>
  <transname>GetPatInfoByCardNo</transname>
   <argument> 
            <CardNo>000355917</CardNo>
            <MZHM>19070101002</MZHM>
            <BRID>25478</BRID>
        </argument>
</request>";

//        $xml_data = simplexml_load_string($xml_data);
//        print_r($xml_data);

        $url = 'http://interface.bdetyy.tianshizhaohu.net:20002/webservice/n_webservice.asmx/'; //接收xml数据的文件
        $header[] = "Content-type: text/xml";      //定义content-type为xml,注意是数组
        $ch = curl_init ($url);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
        curl_setopt($ch, CURLOPT_HTTPHEADER,$header);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch,CURLOPT_POSTFIELDS, $xml_data);
        $response = curl_exec($ch);
        if(curl_errno($ch)){
            $this->error('网络错误');
            print curl_error($ch);
        }

        curl_close($ch);
        halt($response);
        $data = xml_to_array($response);
        halt($data);

//        halt($data);
        $order = json_decode($data['selectPUInfoJsonResponse']['selectPUInfoJsonResult'],true);
        halt($order);
    }



    //厂商列表接口
    public function delivery_code_list()
    {
        $xml_data = "<?xml version=\"1.0\" encoding=\"utf-8\"?>
<soap:Envelope xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\" xmlns:soap=\"http://schemas.xmlsoap.org/soap/envelope/\">
  <soap:Body>
    <selectVendorInfoJson xmlns=\"http://tempuri.org/\" />
  </soap:Body>
</soap:Envelope>";

//        $xml_data = simplexml_load_string($xml_data);
//        print_r($xml_data);
        $url = 'http://272h935a67.qicp.vip:18888/YXInterface/YXWebService.asmx'; //接收xml数据的文件
        $header[] = "Content-type: text/xml";      //定义content-type为xml,注意是数组
        $ch = curl_init ($url);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
        curl_setopt($ch, CURLOPT_HTTPHEADER,$header);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch,CURLOPT_POSTFIELDS, $xml_data);
        $response = curl_exec($ch);
        if(curl_errno($ch)){
            $this->error('网络错误');
            print curl_error($ch);
        }

        curl_close($ch);
//        halt($response);
        $data = xml_to_array($response);
//        halt($data);

//        halt($data);
        $list = json_decode($data['selectVendorInfoJsonResponse']['selectVendorInfoJsonResult'],true);
        foreach ($list as $key => $value){
            $add = [
                'coding' => $value['FVendorCode'],
                'name'   => $value['FVendorName'],
                'fulle_name' => $value['FVendorFullname'],
                'code' => rand('100000','999999')//验证码
            ];
            DB::name('delivery_code')->insert($add);
        }
        halt($list);
    }

    //厂商订单列表接口
    public function order_list()
    {
        $xml_data = "<?xml version=\"1.0\" encoding=\"utf-8\"?>
<soap:Envelope xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\" xmlns:soap=\"http://schemas.xmlsoap.org/soap/envelope/\">
  <soap:Body>
    <selectVendorPUInfoJson xmlns=\"http://tempuri.org/\">
      <FVendorCode>120032</FVendorCode>
    </selectVendorPUInfoJson>
  </soap:Body>
</soap:Envelope>";

//        $xml_data = simplexml_load_string($xml_data);
//        print_r($xml_data);
        $url = 'http://272h935a67.qicp.vip:18888/YXInterface/YXWebService.asmx'; //接收xml数据的文件
        $header[] = "Content-type: text/xml";      //定义content-type为xml,注意是数组
        $ch = curl_init ($url);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
        curl_setopt($ch, CURLOPT_HTTPHEADER,$header);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch,CURLOPT_POSTFIELDS, $xml_data);
        $response = curl_exec($ch);
        if(curl_errno($ch)){
            $this->error('网络错误');
            print curl_error($ch);
        }

        curl_close($ch);
//        halt($response);
        $data = xml_to_array($response);
        halt($data);
        if($data['selectVendorPUInfoJsonResponse']['selectVendorPUInfoJsonResult']){
            halt(1);
        }else{
            halt(2);
        }
        halt($data);
        $list = json_decode($data['selectVendorPUInfoJsonResponse']['selectVendorPUInfoJsonResult'],true);
        halt($list);

//
    }

    //订单详情
    public function order_detail()
    {
        $a = 'CG1901561';
        $xml_data = "<?xml version=\"1.0\" encoding=\"utf-8\"?>
<soap:Envelope xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\" xmlns:soap=\"http://schemas.xmlsoap.org/soap/envelope/\">
  <soap:Body>
    <selectPUInfoJson xmlns=\"http://tempuri.org/\">
      <FCTCode>$a</FCTCode>
    </selectPUInfoJson>
  </soap:Body>
</soap:Envelope>";

//        $xml_data = simplexml_load_string($xml_data);
//        print_r($xml_data);
        $url = 'http://272h935a67.qicp.vip:18888/YXInterface/YXWebService.asmx'; //接收xml数据的文件
        $header[] = "Content-type: text/xml";      //定义content-type为xml,注意是数组
        $ch = curl_init ($url);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
        curl_setopt($ch, CURLOPT_HTTPHEADER,$header);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch,CURLOPT_POSTFIELDS, $xml_data);
        $response = curl_exec($ch);
        if(curl_errno($ch)){
            $this->error('网络错误');
            print curl_error($ch);
        }

        curl_close($ch);
        $data = xml_to_array($response);
        $order = json_decode($data['selectPUInfoJsonResponse']['selectPUInfoJsonResult'],true);
halt($order);

    }

    public function delivery()
    {
//        $a=$this->params['order_coding'];
        $a='NO18120859';
        $xml_data = "<?xml version=\"1.0\" encoding=\"utf-8\"?>
<soap:Envelope xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xmlns:xsd=\"http://www.w3.org/2001/XMLSchema\" xmlns:soap=\"http://schemas.xmlsoap.org/soap/envelope/\">
  <soap:Body>
    <selectPUInfoJson xmlns=\"http://tempuri.org/\">
      <FCTCode>$a</FCTCode>
    </selectPUInfoJson>
  </soap:Body>
</soap:Envelope>";

//        $xml_data = simplexml_load_string($xml_data);
//        print_r($xml_data);
        $url = 'http://272h935a67.qicp.vip:18888/YXInterface/YXWebService.asmx'; //接收xml数据的文件
        $header[] = "Content-type: text/xml";      //定义content-type为xml,注意是数组
        $ch = curl_init ($url);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
        curl_setopt($ch, CURLOPT_HTTPHEADER,$header);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch,CURLOPT_POSTFIELDS, $xml_data);
        $response = curl_exec($ch);
        if(curl_errno($ch)){
            $this->error('网络错误');
            print curl_error($ch);
        }

        curl_close($ch);
        $data = xml_to_array($response);
        $order = json_decode($data['selectPUInfoJsonResponse']['selectPUInfoJsonResult'],true);
        halt($order);
    }



        public function get_this_month(){
            $y = date("Y", time()); //年
            $m = date("m", time()); //月
            $d = date("d", time()); //日
            $t0 = date('t'); // 本月一共有几天
            $r=array();
            $r['start_month'] = mktime(0, 0, 0, $m, 1, $y); // 创建本月开始时间
            $r['end_month'] = mktime(23, 59, 59, $m, $t0, $y); // 创建本月结束时间
            return $r;
        }

        public function e()
        {


            $org = DB::name('machine_day_statistics')->where('machine_id',11000)->where('stat_period','between',[1574265600,1774265600])->sum('online_income');
            dump($org);
        }

        public function q()
        {


            $a = [
                [
                    'ri' => 11,
                    'rs' => 1,
                ],
                [
                    'ri' => 12,
                    'rs' => 1,
                ],
//                [
//                    'ri' => 13,
//                    'rs' => 1,
//                ],

            ];
            $k = 0;
            foreach ($a as $key => $value) {
                $arr[$k] = array_values($value);
                $k++;
            }


            $b = [
                [
                    11, 1
                ],
                [
                    12, 2
                ],
                [
                    13, 2
                ],

            ];
            $c = [];
            foreach ($arr as $key => $value) {
                if (!in_array($value, $b)) {
                    $c[] = $value;
                }
            }
            if(empty($c)){
                dump(1);
            }else{
                dump(2);
            }
            dump($b);
            dump($arr);
            dump($c);
        }

        public function tui()
        {
            $post = [
                'id' => '1001201911150000001275642528',
                'amount' => 0.01
            ];
            $url = "http://www.wakapay.cn/index.php/api/yeepay/refund";
//            $url = "http://192.168.1.144:1161/api/yeepay/pay";
            $return = json_curl($url,$post);
            halt($return);
        }

        public function c()
        {
//            halt(1);
            $value['goods_id'] = 1;
            $max = 100;

            copy("./goods_info/$value[goods_id].jpg","./goods_info/$max.jpg");
        }

        public function ror()
        {
            $machine['machine_id'] = 11000;
            $arr = DB::name('machine_conf')
                ->where('machine_id',$machine['machine_id'])
                ->column('goods_id');

            $conf = array_unique($arr);
            foreach( $conf as $k=>$v){
                if( !$v )
                    unset( $conf[$k] );
            }
            halt($conf);
        }

        public function t()
        {

            $stat_period = strtotime(date("Y-m-d"),time());
            halt($stat_period);
            
        }

        public function tr()
        {
            $ordeR_id = 222;
            $data = ror($ordeR_id);
            halt($data);
        }

        public function gou()
        {
            $data = [[1,8],[2,1]];
            $error = 'number error';
            foreach ($data as $key => $value){
                $number = DB::name('machine_conf')->where('goods_id',$value[0])->sum('number');
                if($number < $value[1]){
                    return json_encode($error,JSON_UNESCAPED_UNICODE);
                }
            }

            halt(1313);
        }

        public function ds()
        {

            $O = $params['msg']['O'] = '1001201911300000001312193205';
            $P = $params['msg']['P'] = [[2,3]];
            DB::name('order')->where('unique_order_id',$O)->update(['error'=>0]);

            $order = DB::name('order')->where('unique_order_id',$O)->find();

            $k = 0;
            foreach($P as $key => $value){
                $arr[$k] = array_values($value);
                $k++;
            }//处理成没有键值的二维数组
//halt($arr);
            $error = null;
            foreach ($arr as $key => $value) {
                if (!in_array($value, json_decode($order['goods_list'],true))) {
                    $error[] = $value;
                }
            }//两个数组不同
//            halt($error);
            if(is_null($error)){//正常
                $update = [
                    'error' => 1,
                ];
            }else{//部分成功
                $update = [
                    'error' => 2,
                    'receive_msg' => json_encode($error,JSON_UNESCAPED_UNICODE),
                ];
            }
            DB::name('order')->where('unique_order_id',$O)->update($update);
        }

        public function s()
        {
            $a = [[1,1],[2,1]];
            $b = [[1,1],[2,2]];
            if($a == $b){
                halt(1);
            }else{
                halt(2);
            }
        }


        //11000的设备测试接口 (2,3,4,5,6,7)
        public function machine_conf_test()
        {
            $params = request()->param();
            foreach($params as $key => $value){
                $update = [
                    'update_time' => time(),
                    'number' => $value['number'],
                    'lat' => $value['lat'],
                    'lng' => $value['lng']
                ];
                DB::name('machine_conf')->where('id',$value['id'])->update($update);
            }
            $this->success();



        }
        //11000配置列表
        public function test_list()
        {
            $data = DB::name('machine_conf')
                ->field('id,location,goods_id,number,lat,lng')
                ->where('machine_id',11000)
                ->select();
//            dump($data);
            return json_encode($data,JSON_UNESCAPED_UNICODE);
        }

        public function po()
        {
            $arr = [
                [
                    'gi' => 2,
                    'gp' => '0.01',
                    'gn' => 5,
                    'ts' => 1574835842
                ],
                [
                    'gi' => 2,
                    'gp' => '0.01',
                    'gn' => 5,
                    'ts' => 1574835842
                ],
                [
                    'gi' => 2,
                    'gp' => '0.01',
                    'gn' => 5,
                    'ts' => 1574835842
                ],
            ];
            $data = [
                'C' => 'ui',
                'P' => $arr,
                'O' => 13,
            ];

            $res = model('Machine')->post_to_server($data, 11001);
            halt($res);

        }

        public function ex()
        {
            $data = DB::name('order')->where('id',510)->value('goods_list');
            dump(json_decode($data,true));
        }

        public function fuquanzhishang()
        {
            $data = DB::name('machine')->find(1123);
            $params = request()->params();

            $this->success();
            $this->error();
        }

        public function ps()
        {
            $password = 'admin';
            $a = 'FaoHNJ';
            $data = $this->getEncryptPassword($password, $a);
            halt($data);

        }

        public function conf()
        {
            $result = 1;
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

            ];
            DB::name('machine_conf')->insertAll($conf);
        }

     

    public function sha()
    {
        $data = [
            'sn' => 10,
            'ts' => 1577261258,
            'access_token' => "bfeb0658-7cd0-4b17-8842-d92013a9f60a"
        ];
        $sha = json_encode($data);
        echo $sha;
        $result = sha1($sha);

        halt($result);
    }

    public function daxiao()
    {
        $a = "bfeb0658-7cd0-4b17-8842-d92013a9f60a";
        $a = strtoupper($a);
        dump($a);
        $b = "ABCD";
        if($a == $b){
            echo 1;
        }else{
            echo 2;
        }
    }

    public function shouce()
    {
        $data = [
            'sn' => 123456789,
            'ts' => 1523434995,
            'access_token' => "12345678"
        ];
        $json = json_encode($data);
        $sha = sha1($json);
        dump($json);
        dump($sha);
    }

    public function str()
    {

        $str = "{\"sn\":10,\"ts\":1577261258,\"access_token\":\"bfeb0658-7cd0-4b17-8842-d92013a9f60a\"}";
        halt(sha1($str));
    }

    public function pic()
    {
        $data = DB::name('client_goods')->field('goods_price')->find(1);
        dump($data);
    }

    public function arr()
    {
        $data='[{\"37,2\"}]';
        $arr = json_decode($data);
        halt($arr);
        return json_decode($data);
    }

    public function password()
    {

        echo md5(md5(147258369) . '6gKBEH');
//        'a5d8cf02d289ff7afb306231747519e9';
    }


    public function edit_pswd()
    {
        $params['brief_url'] = "/goods_info/1.jpg";
        $a = substr($params['brief_url'],strripos($params['brief_url'],"6")+1);

        halt($a);
    }

    public function color()
    {
        $result = "color";
        echo "<font color=#85807>墙面颜色</font>";
    }

    public function di()
    {
        mkdir("./goods_info/666",0777,true);
    }

//
//    public function tr()
//    {
//        truncate table fa_client_goods;
//        truncate table fa_delivery_detail;
//        truncate table fa_delivery_img;
//        truncate table fa_delivery_log;
//        truncate table fa_goods_statistics;
//        truncate table fa_inventory;
//        truncate table fa_inventory_log;
//        truncate table fa_machine_conf;
//        truncate table fa_machine;
//        truncate table fa_machine_day_statistics;
//        truncate table fa_order;
//        truncate table fa_stock;
//        truncate table fa_client_domain;
//        delete from fa_user where id>2;
//        delete from fa_client_org where id>2;
//
//    }

        public function md()
        {
            $url = DB::name('client_goods')->where('goods_id',1)->value('small_img');
//            halt($url);
            $data = $_SERVER["DOCUMENT_ROOT"] . "/" . $url;
//            halt($data);
            dump(md5_file($data));

//            $file = "http://192.168.0.102:9999/goods_info/1.jpg";
            $size = filesize($data);
            dump($size);
        }

        public function qew()
        {
            $liujing = "./dasdas";
            if(!is_dir($liujing)){
                mkdir(iconv("UTF-8", "GBK", $liujing),0777,true);
            }
        }


        public function tt()
        {
            $user_id = 2;
            $res = DB::name('user')
                ->alias('t1')
//                ->field('t1.id,t2.machine_id')
                ->join('__MACHINE__ t2','t1.id=t2.user_id','RIGHT')
                ->where('t1.id',$user_id)
                ->select();
            dump($res);
        }

        public function ff()
        {
            $data = DB::name('machine')->where("user_id",1000)->column('machine_id');
            halt($data);

        }

        //修改账号密码
        public function update_pswd()
        {
               
        }

}




