<?php
/**
 * This file is part of workerman.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author walkor<walkor@workerman.net>
 * @copyright walkor<walkor@workerman.net>
 * @link http://www.workerman.net/
 * @license http://www.opensource.org/licenses/mit-license.php MIT License
 */

/**
 * 用于检测业务代码死循环或者长时间阻塞等问题
 * 如果发现业务卡死，可以将下面declare打开（去掉//注释），并执行php start.php reload
 * 然后观察一段时间workerman.log看是否有process_timeout异常
 */
//declare(ticks=1);

use \GatewayWorker\Lib\Gateway;
require_once('/www/wwwroot/www.9pointstars.com/vendor/GatewayWorker/vendor/workerman/MySQL/src/Connection.php');
/**
 * 主逻辑
 * 主要是处理 onConnect onMessage onClose 三个方法
 * onConnect 和 onClose 如果不需要可以不用实现并删除
 */
class Events
{

    /**
     * 新建一个类的静态成员，用来保存数据库实例
     */
    public static $db = null;

    /**
     * 进程启动后初始化数据库连接
     */
    public static function onWorkerStart($worker)
    {
        self::$db = new \Workerman\MySQL\Connection('47.106.159.56', '3306', '9pointstars', 'GwdwXmS4NGknKnLf', '9pointstars');
        //$db = new \Workerman\MySQL\Connection('host', 'port', 'user', 'password', 'db_name');
    }
    /**
     * 当客户端连接时触发
     * 如果业务不需此回调可以删除onConnect
     *
     * @param int $client_id 连接id
     */
    public static function onConnect($client_id)
    {
        // 向当前client_id发送数据
//        Gateway::sendToClient($client_id, "Hello $client_id\r\n");
        // 向所有人发送
//        Gateway::sendToAll("$client_id login\r\n");

        Gateway::sendToClient($client_id,json_encode([
            'type'=>'init',
            'client_id'=>$client_id,
            'msg'=>'连接成功',
            'code' => 200
        ]));

    }

    /**
     * 当客户端发来消息时触发
     * @param int $client_id 连接id
     * @param mixed $message 具体消息
     */
    public static function onMessage($client_id, $message)
    {
        // 向所有人发送
        $message_data = self::isJson($message,true);
        if(!$message_data){
            $date['bak_type'] = 'err';
            $date['msg'] ='格式错误';
            $date['code'] = 500;
            Gateway::sendToClient($client_id, json_encode($date));
            return;
        }

        switch($message_data['type']){
            // 接收心跳
            //        {
            //        "type": "ping"
            //        }
            case 'ping':
                $date = [];
                $date['msg'] = '心动的感觉';
                $date['code'] = 200;
                Gateway::sendToUid($client_id, json_encode($date));
                return;
            /**
             * 绑定from_id
             */
            case "bind":
                $date = [];
                if(empty($message_data['from_id']))
                {
                    $date['bak_type'] = 'err';
                    $date['msg'] ='参数错误';
                    $date['code'] = 400;
                    Gateway::sendToClient($client_id, json_encode($date));
                    return;
                    break;
                }
                $from_id = $message_data['from_id'];
                Gateway::bindUid($client_id,$from_id);
                $date=[
                    'bak_type'=>'bind',
                    'client_id'=>$client_id,
                    'from_id'=>$from_id,
                    'msg'=>'绑定成功',
                    'code' => 200,
                    "add_time" => time()
                ];
                Gateway::sendToUid($from_id, json_encode($date));
                return;

            /**
             * 发送信息
             * （scene：chat 为一对一聊天见面界面;  scene：list 列表页）（msg_type：1 文本;  scene：2 图片）
             */
            case "say":
                $date = [];
                if(empty($message_data['from_id']) || empty($message_data['receive_id']) || empty($message_data['msg_type']))
                {
                    $date['bak_type'] = 'err';
                    $date['msg'] ='参数错误';
                    $date['code'] = 400;
                    Gateway::sendToClient($client_id, json_encode($date));
                    return;
                }
                $from_id = $message_data['from_id'];
                $receive_id = $message_data['receive_id'];
                $msg_type = $message_data['msg_type'];
                $add_time = time();
                if($receive_id == $from_id)
                {
                    $date['type'] = 'err';
                    $date['msg'] ='聊天对象错误';
                    $date['code'] = 500;
                    Gateway::sendToUid($from_id, json_encode($date));
                    return;
                }
                $date=[
                    'msg_type'=>$msg_type,
                    'from_id'=>$from_id,
                    'receive_id'=>$receive_id,
                    'add_time'=> $add_time
                ];

                if(!Gateway::isUidOnline($from_id))
                {
                    $date = [];
                    $date['type'] = 'err';
                    $date['msg'] ='未绑定，请先发送绑定指令';
                    $date['code'] = 500;
                    Gateway::sendToUid($client_id, json_encode($date));
                    return;
                }
                //是否在线 一对一聊天见面界面为已读
                $is_online = Gateway::isUidOnline($receive_id);
                $date['receive_id_is_show'] = 1;
                $date['from_id_is_show'] = 1;
                if($is_online && $message_data['scene'] == 'chat'){
                    $date['receive_id_is_read'] = 1;
                }else{
                    $date['receive_id_is_read'] = 0;
                }

                if($msg_type == 1)//文本
                {
                    $date['content'] = nl2br(htmlspecialchars($message_data['content']));
                    $satus = self::$db->query("insert into chat_record   
                                      (receive_id_is_read,from_id_is_show,receive_id_is_show,add_time,content,from_id,receive_id,msg_type)
                                    VALUES 
                                    (
                                        {$date['receive_id_is_read']},
                                        {$date['from_id_is_show']},
                                        {$date['receive_id_is_show']},
                                        {$add_time},
                                        '".$date['content']."', 
                                        {$date['from_id']}, 
                                        {$date['receive_id']},
                                        {$date['msg_type']})
                                    ");

                    if($satus)
                    {
                        if($is_online)
                        {
                            $date['type'] = 'accept';
                            Gateway::sendToUid($receive_id, json_encode($date));
                        }

                        $date = [];
                        $date['bak_type'] ='say';
                        $date['msg'] ='已发送';
                        $date['code'] = 200;
                        $date['is_online '] = $is_online;
                        $date['info_id'] = $satus;
                        Gateway::sendToUid($from_id, json_encode($date));
                        return;
                    }else{
                        $date = [];
                        $date['type'] = 'err';
                        $date['msg'] ='信息保存失败';
                        $date['code'] = 500;
                        Gateway::sendToUid($from_id, json_encode($date));
                        return;
                    }

                }elseif ($msg_type == 2)//图片 {"type":"say","from_id":224,"receive_id":199"msg_type":2,"img_src";"https://xx.com/xx.png"}
                {
                    $date['img_src'] = $message_data['img_src'];

                    $satus = self::$db->query("insert into chat_record   
                                      (receive_id_is_read,from_id_is_show,receive_id_is_show,add_time,img_src,from_id,receive_id,msg_type)
                                    VALUES 
                                    (
                                        {$date['receive_id_is_read']},
                                        {$date['from_id_is_show']},
                                        {$date['receive_id_is_show']},
                                        {$add_time},
                                         '".$date['img_src']."', 
                                        {$date['from_id']}, 
                                        {$date['receive_id']},
                                        {$date['msg_type']})
                                    ");
                    if($satus)
                    {
                        if($is_online)
                        {
                            $date['type'] = 'accept';
                            Gateway::sendToUid($receive_id, json_encode($date));
                        }

                        $date = [];
                        $date['bak_type'] ='say';
                        $date['msg'] ='已发送';
                        $date['code'] = 200;
                        $date['is_online '] = $is_online;
                        $date['info_id'] = $satus;
                        Gateway::sendToUid($from_id, json_encode($date));
                        return;
                    }else{
                        $date = [];
                        $date['type'] = 'err';
                        $date['msg'] ='图片信息信息保存失败';
                        $date['code'] = 500;
                        Gateway::sendToUid($from_id, json_encode($date));
                        return;
                    }
                }
                return;

            case "online":
                $receive_id = $message_data['receive_id'];
                $from_id = $message_data['from_id'];
                $status = Gateway::isUidOnline($receive_id);
                $date = [];
                if($status)
                {
                    $date['msg'] ='接收者ID：'.$receive_id.'在线';
                    $date['is_online'] = $status;
                }else{
                    $date['msg'] ='接收者ID：'.$receive_id.'已下线';
                    $date['is_online'] = $status;
                }
                $date['code'] = 200;
                $date['time'] = time();
                Gateway::sendToUid($fromid,json_encode($date));
                return;
            case "list":

                return;
            default:
                $date = [];
                $date['type'] = 'err';
                $date['msg'] ='未知识别指令';
                $date['code'] = 400;
                Gateway::sendToUid($fromid,json_encode($date));
                return;
        }
        return;
    }

    /**
     * 当用户断开连接时触发
     * @param int $client_id 连接id
     */
    public static function onClose($client_id)
    {
        Gateway::sendToClient($client_id,json_encode([
            'type'=>'close',
            'client_id'=>$client_id,
            'msg'=>'下线',
            'code' => 200
        ]));
        // 向所有人发送
//        GateWay::sendToAll("$client_id logout\r\n");
        return;
    }

    /**
     * 判断字符串是否为 Json 格式
     *
     * @param  string  $data  Json 字符串
     * @param  bool    $assoc 是否返回关联数组。默认返回对象
     *
     * @return array|bool|object 成功返回转换后的对象或数组，失败返回 false
     */
    public static function isJson($data = '', $assoc = false) {
        $data = json_decode($data, $assoc);
        if (($data && is_object($data)) || (is_array($data) && !empty($data))) {
            return $data;
        }
        return false;
    }
}
