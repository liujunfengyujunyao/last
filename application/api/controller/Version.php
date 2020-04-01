<?php
/**
 * Created by PhpStorm.
 * User: GoldenBrother
 * Date: 2020/1/6
 * Time: 14:40
 */

namespace app\api\controller;
use think\Db;
use app\common\controller\Api;
use fast\Random;
header('Access-Control-Allow-Headers: Content-Type,Content-Length,Accept-Encoding,X-Requested-with, Origin, Authorization,access-control-request-headers'); // 设置允许自定义请求头的字段
//header("Access-Control-Max-Age", "1800");
header("Content-Type: text/html;charset=utf-8");
header('Access-Control-Allow-Methods:POST,GET,OPTIONS,DELETE'); // 允许请求的类型
header('Access-Control-Allow-Credentials: true'); // 设置是否允许发送 cookies
header('Access-Control-Allow-Origin:*');
ini_set('max_execution_time', '0');
class Version extends Api
{
    protected $noNeedLogin = ['*'];
    //上传版本
    public function app_uploads()
    {

        $params = request()->param();
        $file = request()->file('app');
        $liujun = "./version";
        if(!is_dir($liujun)){
            mkdir(iconv("UTF-8", "GBK", $liujun),0777,true);
        }
        $app_file = $file->validate(['size'=>210780000,'ext'=>'apk,app,1'])->move(ROOT_PATH . 'public' . DS . 'version',$params['name']);
//        $this->error($file->getError());
//        halt($app_file);
        $app_url = DS . 'version' . DS . $app_file->getSaveName();
        $params['md5'] = md5_file($_SERVER["DOCUMENT_ROOT"] . "/" . $app_url);
        $params['size'] = filesize($_SERVER["DOCUMENT_ROOT"] . "/" . $app_url);
        $insert = [
            'create_time' => time(),
            'url'        => $app_url,
            'size'       => $params['size'],
            'name'       => $params['name'],
            'md5'        => $params['md5'],
        ];
        $upload_version_id = DB::name('app_version')->insertGetId($insert);
        DB::name('machine')->where("1=1")->update(['upload_version_id'=>$upload_version_id]);//将所有设备都安排升级任务
        $this->success();
    }

    //凌晨四点检测
    public function version_check()
    {
        $machine_ids = DB::name('machine')
            ->where('upload_version_id','neq',null)
            ->where('is_online',1)
            ->column('machine_id');
        $max_id = DB::name('app_version')->max('id');
        foreach($machine_ids as $k => $v){
            $version = DB::name('app_version')->find($max_id);
            $P = [
                'nv' => $version['name'],
                'ad' => $version['url'],
                'dd' => $version['md5']
            ];
            $add = [
                'machine_id' => $v,
                'msgtype' => 'us',
                'send_time' => time(),
                'content' => json_encode($P,JSON_UNESCAPED_UNICODE),
            ];
            $O = DB::name('command_machine')->insertGetId($add);
            $msg = [
                'C' => 'us',
                'P' => $P,
                'O' => $O,
            ];
            model('Machine')->post_to_server($msg,$v);
        }
    }


    //设备更新列表 status=0不许要更新  status=1 需要更新
    public function machine_upgrade_list()
    {
        $machine = DB::name('machine')->alias('t1')
            ->field('t1.machine_id,t1.machine_name,t1.soft_version,t1.upload_version_id,t2.name as version_name')
            ->join('__APP_VERSION__ t2','t1.upload_version_id=t2.id','LEFT')
            ->select();
        foreach($machine as $key => &$value){
            if(!is_null($value['upload_version_id'])){
                //需要更新
                $value['status'] = 1;
            }else{
                $value['status'] = 0;
            }
        }
        return json_encode($machine,JSON_UNESCAPED_UNICODE);
    }

    //设备更新
    public function machine_upgrade()
    {

        $params = request()->param('machine_id');
//        $params = 1;
        $max_id = DB::name('app_version')->max('id');
        $version = DB::name('app_version')->find($max_id);
        $P = [
            'nv' => $version['name'],
            'ad' => $version['url'],
            'dd' => $version['md5'],
            'ps' => $version['size'],
            'un' => 1,
        ];
        $add = [
            'machine_id' => $params,
            'msgtype' => 'us',
            'send_time' => time(),
            'content' => json_encode($P,JSON_UNESCAPED_UNICODE),
        ];
        $O = DB::name('command_machine')->insertGetId($add);
        $msg = [
            'C' => 'us',
            'P' => $P,
            'O' => $O,
        ];
        model('Machine')->post_to_server($msg,$params);
        $this->success();
    }




}