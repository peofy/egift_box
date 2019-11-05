<?php
/**
 * 聊天API
 */
namespace app\api\controller;
use app\common\model\Users;
use think\AjaxPage;
use think\Page;
use think\Db;
use think\Request;
use think\view\driver\Think;

class Chat extends ApiBase
{

    /**
     * @throws \think\db\exception\BindParamException
     * @throws \think\exception\PDOException
     * 根据时间戳获取20条记录
     */
    public function getChatLog()
    {
        $from_id = input('from_id/d',0);
        $receive_id = input('receive_id/d',0);
        $add_time= input('add_time/d',0);
        if(empty($add_time) ||empty($receive_id)||empty($from_id))
        {
            return $this->ajaxReturn(['status' => 500 , 'msg'=>'参数错误']);
        }
        $ChatLog = Db::name('chat_record')->query("SELECT * FROM `chat_record` WHERE `add_time` < {$add_time} AND ( `receive_id` = {$from_id} OR `receive_id` = {$receive_id}) AND (`from_id` = {$from_id} OR`from_id` = {$receive_id}) AND `from_id_is_show` = 1 AND `receive_id_is_show` = 1 AND `is_show` = 1 ORDER BY `id` DESC LIMIT 20;");
        sort($ChatLog);
        return $this->ajaxReturn(['status' => 200 , 'msg'=>'ok','data' => $ChatLog]);
    }


    /**
     * 页面加载返回聊天记录
     */
    public function load(){
        $from_id = input('from_id/d',0);
        $receive_id = input('receive_id/d',0);
        if(empty($from_id) || empty($receive_id))
        {
            return $this->ajaxReturn(['status' => 500 , 'msg'=>'参数错误']);
        }
        //$NoReadCount =  Db::name('chat_record')->query("SELECT count(*) as counts FROM `chat_record` WHERE( `receive_id` = {$from_id} OR `receive_id` = {$receive_id}) AND (`from_id` = {$from_id} OR`from_id` = {$receive_id} )AND is_read = 0  AND `is_show` = 1 ORDER BY `id` DESC");
        $NoReadCount =  Db::name('chat_record')->where([
            'from_id' => $receive_id,
            'receive_id' => $from_id,
            'receive_id_is_read' => 0,
            'is_show' => 1,
        ])->count();

        $ListMessage = Db::name('chat_record')->fetchSql()->query("
            SELECT * FROM `chat_record` WHERE 
            ( `receive_id` = {$from_id} OR `receive_id` = {$receive_id}) AND (`from_id` = {$from_id} OR `from_id` = {$receive_id} ) 
            AND `from_id_is_show` = 1 AND `receive_id_is_show` = 1  AND `is_show` = 1 ORDER BY `id` DESC LIMIT 20
            ");
        sort($ListMessage);
        if($NoReadCount < 10)
        {
            $NoReadCount = 0;
            Db::name('chat_record')
                ->where([
                    'from_id' => $receive_id,
                    'receive_id' => $from_id,
                    'receive_id_is_read' => 0,
                    'is_show' => 1,
                ])
                ->update(['receive_id_is_read'=>1]);

            //Db::name('chat_record')->where("( `receive_id` = {$from_id} OR `receive_id` = {$receive_id}) AND (`from_id` = {$from_id} OR`from_id` = {$receive_id} ) AND is_read = 0")->update(['is_read'=>1]);
        }
        return $this->ajaxReturn(['status' => 200 , 'msg'=>'ok','data' => ['NoReadCount'=> $NoReadCount,'ListMessage' => $ListMessage]]);
//        $count =  Db::name('chat_record')->where('(from_id=:from_id and receive_id=:receive_id) || (from_id=:toid1 and receive_id=:fromid1)',['fromid'=>$from_id,'toid'=>$receive_id,'toid1'=>$receive_id,'fromid1'=>$from_id])->count('id');
//        if($count>=10){
//            $message = Db::name('chat_record')->where('(from_id=:from_id and receive_id=:receive_id) || (from_id=:toid1 and receive_id=:fromid1)',['fromid'=>$from_id,'toid'=>$receive_id,'toid1'=>$receive_id,'fromid1'=>$from_id])->limit($count-10,10)->order('id')->select();
//        }else{
//            $message = Db::name('chat_record')->where('(from_id=:from_id and receive_id=:receive_id) || (from_id=:toid1 and receive_id=:fromid1)',['fromid'=>$from_id,'toid'=>$receive_id,'toid1'=>$receive_id,'fromid1'=>$from_id])->order('id')->select();
//        }

    }

    /**
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     * 删除聊天记录
     */
    public function delChat()
    {
        $from_id = input('from_id/d',0);
        $receive_id = input('receive_id/d',0);

        $status_1 = Db::name('chat_record')
            ->where( "")
            ->where([
                'from_id' => $from_id,
                'receive_id' => $receive_id,
                'is_show' => 1
            ])
            ->update(['from_id_is_show' => 0]);
            
            
            
        $status_2 = Db::name('chat_record')
            ->where( "")
            ->where([
                'from_id' => $from_id,
                'receive_id' => $receive_id,
                'is_show' => 1
            ])
            ->update(['receive_id_is_show' => 0]);
            
           

        if($status_1 && $status_2)
        {
            return $this->ajaxReturn(['status' => 200 , 'msg'=>'ok']);
        }else{
            return $this->ajaxReturn(['status' => 500 , 'msg'=>'参数错误']);
        }

    }

    /**
     * @throws \think\Exception
     * @throws \think\exception\PDOException
     * 修改未读状态
     */
    public function changeNoRead(){
        $from_id = input('from_id/d',0);
        $add_time = input('add_time/d',0);
        $receive_id = input('receive_id/d',0);
        if(empty($from_id)|| empty($receive_id)|| empty($add_time))
        {
            return $this->ajaxReturn(['status' => 500 , 'msg'=>'参数错误']);
        }
        $where = [
            'from_id' => $from_id,
            'receive_id' => $receive_id,
            'add_time' => $add_time,
            'receive_id_is_read' => 0,
        ];
        $status = Db::name('chat_record')
            ->where($where)
            ->where("`add_time` <= {$add_time}")
            ->update(['receive_id_is_read'=>1]);
        if($status)
        {
            return $this->ajaxReturn(['status' => 200 , 'msg'=>'ok']);
        }else{
            return $this->ajaxReturn(['status' => 500 , 'msg'=>'修改状态失败']);
        }

    }

    /**
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * 获取用户信息
     */
    public function getUserInfo()
    {
        $from_id= input('from_id',0);
        $receive_id = input('receive_id',0);
        if(!empty($from_id) && empty($receive_id))
        {
            $user = Db::name('member')->field('id,nickname,avatar,sex')->where('id',$from_id)->find();
            return $this->ajaxReturn(['status' => 200 , 'msg'=>'ok','data'=>$user]);
        }else if(!empty($from_id) && !empty($receive_id)){
            $id = $from_id.','.$receive_id;
            $user = Db::name('member')->query("SELECT `id`,`nickname`,`avatar`,`sex` FROM `member` WHERE  `id` IN ({$id}) order by field (id,{$id}) ");
            return $this->ajaxReturn(['status' => 200 , 'msg'=>'ok','data'=>$user]);
        }else{
            return $this->ajaxReturn(['status' => 400 , 'msg'=>'参数错误']);
        }
    }


    /**
     * @throws \think\Exception
     * @throws \think\db\exception\BindParamException
     * @throws \think\db\exception\DataNotFoundException
     * @throws \think\db\exception\ModelNotFoundException
     * @throws \think\exception\DbException
     * @throws \think\exception\PDOException
     * 根据from_id来获取当前用户聊天列表
     */
    public function get_list()
    {
        $from_id = input('from_id/d','');
        if(empty($from_id))
        {
            $this->ajaxReturn(['status' => 400 , 'msg'=>'参数错误!']);
        }

    //查询当前用户为接收者或者发送者的最后一条信息
        //1、找出当前用户为发送者的最后一条消息记录data1
        $from_id_list = Db::name('chat_record')->query("SELECT
                                                                        *
                                                                    FROM
                                                                        ( SELECT * FROM `chat_record` WHERE  `from_id` = {$from_id} AND `from_id_is_show` = 1 AND `is_show` = 1 ORDER BY `add_time` DESC) a 
                                                                    GROUP BY
                                                                        `receive_id`");

        //2、找出当前用户为接受者的最后一条消息记录data2
        $receive_id_list = Db::name('chat_record')->query("SELECT
                                                                        *
                                                                    FROM
                                                                        (SELECT * FROM `chat_record` WHERE  `receive_id` = {$from_id} AND `receive_id_is_show` = 1   AND `is_show` = 1 ORDER BY `add_time` DESC) a 
                                                                    GROUP BY
                                                                        `from_id`");

//        dump($from_id_list);
//        echo "-----------";
//        dump($receive_id_list);
//        echo "---------------------------------";die;
        //如果没有信息则为空


        if(empty($receive_id_list) && empty($from_id_list))
        {
            $receive_id_list = [];
        }elseif (empty($receive_id_list)){//如果没有人跟我发信息则返回我发的信息
            $receive_id_list = $from_id_list;
        }else {
            $receive_id_array = array_column($from_id_list, 'receive_id');
            $from_id_array = array_column($receive_id_list, 'from_id');
            $tmp = [];

            foreach ($from_id_array as $k_1 => $v_1) {
                foreach ($receive_id_array as $k_2 => $v_2) {
                    $ks_1 = array_search($v_1, $from_id_array);
                    $ks_2 = array_search($v_2, $receive_id_array);
                    //互为接收者和发送者 ,即 receive_id = from_id && receive_id = from_id
                    if ($v_1 == $v_2) {
                        //两条都是已读的时候
                        if ($receive_id_list[$ks_1]['receive_id_is_read'] == 1 && $from_id_list[$ks_2]['receive_id_is_read'] == 1) {
                            //则根据时间取最大的一条记录
                            if ($receive_id_list[$ks_1]['add_time'] > $from_id_list[$ks_2]['add_time']) {
                                $tmp[] = $receive_id_list[$ks_1];
                            } else {
                                $tmp[] = $from_id_list[$ks_2];
                            }
                        } else {

                            //取未读的数据
                            if ($receive_id_list[$ks_1]['receive_id_is_read'] == 0 && $from_id_list[$ks_2]['receive_id_is_read'] == 1) {
                                $tmp[] = $receive_id_list[$ks_1];
                            } else {
                                $tmp[] = $from_id_list[$ks_2];
                            }
                        }
                    } else {
                        if (!in_array($v_1, $receive_id_array)) {
                            $tmp[] = $receive_id_list[$ks_1];
                        }
                        if (!in_array($v_2, $from_id_array)) {
                            $tmp[] = $from_id_list[$ks_2];
                        }
                    }
                    $ks_1 = null;
                    $ks_2 = null;
                }
            }
            //去重
            $tmp = array_merge(array_unique($tmp, SORT_REGULAR));

            $receive_id_list = $tmp;
        }

//        if(empty($receive_id_list) && empty($from_id_list))
//        {
//            $receive_id_list = [];
//        }elseif (empty($receive_id_list)){//如果没有人跟我发信息则返回我发的信息
//            $receive_id_list = $from_id_list;
//        }else{
//            $receive_id_list_count = count($receive_id_list);
//            $from_id_list_count = count($from_id_list);
//            for ($i = 0; $i < $receive_id_list_count; $i++)
//            {
//                for ($j = 0; $j < $from_id_list_count; $j++)
//                {
//                    //互为接收者和发送者 ,即 receive_id = from_id && receive_id = from_id
//                    if($receive_id_list[$i]['from_id'] == $from_id_list[$j]['receive_id'] && $receive_id_list[$i]['receive_id'] == $from_id_list[$j]['from_id'])
//                    {
//                        //两条都是已读的时候
//                        if($receive_id_list[$i]['receive_id_is_read'] == 1 && $from_id_list[$j]['receive_id_is_read'] == 1)
//                        {
//                            //则根据时间取最新的一条记录
//                            if($receive_id_list[$i]['add_time'] < $from_id_list[$j]['add_time'])
//                            {
//                                $receive_id_list[$i] = $from_id_list[$j];
//                            }
//                        }else if(//一条为已读的情况下
//                            ($receive_id_list[$i]['receive_id_is_read'] == 0 || $from_id_list[$j]['receive_id_is_read'] == 0)
//                            &&
//                            ($receive_id_list[$i]['receive_id_is_read'] == 1 || $from_id_list[$j]['receive_id_is_read'] == 1)
//                        )
//                        {
//                            //接收者为未读、接收者是我并且发送者已读的情况下
//                            if($receive_id_list[$j]['receive_id_is_read'] == 0 && $receive_id_list[$j]['receive_id'] == $from_id){
//                                $receive_id_list[$i] = $receive_id_list[$j];
//                            }
//
//                        }
//                    }else{
//                        $receive_id_list[count($receive_id_list)] = $receive_id_list[$j];
//                    }
//                }
//            }
//        }

        //统计未读数据
        $str_id = '';
        foreach ($receive_id_list as $k => $v)
        {

            $receive_id_list[$k]['unread_data'] = 0;
            //未读信息统计总数
            if($v['receive_id'] == $from_id)
            {
                $receive_id_list[$k]['unread_data'] = Db::name('chat_record')->where(['receive_id' => $from_id,'from_id'=> $v['from_id'],'receive_id_is_read' =>0,'is_show' => 1])->count();
            }
            //获取跟我聊过天的用户ID
            if($v['from_id'] == $from_id)
            {
                $str_id .= $v['receive_id'].',';
            }else{
                $str_id .= $v['from_id'].',';
            }
        }
        if(!empty($str_id))
        {
            $str_id = rtrim($str_id, ',');
            $order = 'id ,'.$str_id;
            $userInfo = Db::name('member')->query("SELECT `id`,`nickname`,`avatar`,`sex` FROM `member` WHERE  `id` IN ({$str_id}) order by field ({$order}) ");
            $domain = SITE_URL;//SITE_URL.DS
            foreach ($receive_id_list as $ks => $vs)
            {
                $receive_id_list[$ks]['user_id'] = $userInfo[$ks]['id'];
                $receive_id_list[$ks]['nickname'] = $userInfo[$ks]['nickname'];
		
                if(substr($userInfo[$ks]['avatar'],0,5) == 'https')
                {
                	 $receive_id_list[$ks]['avatar'] = $userInfo[$ks]['avatar'];
                	
      
                }else{
                   
    				$receive_id_list[$ks]['avatar'] = $domain.$userInfo[$ks]['avatar'];
                }
                $receive_id_list[$ks]['sex'] = $userInfo[$ks]['sex'];
            }
        }
//        $receive_id_list = self::array_sort($receive_id_list,'unread_data');
        sort($receive_id_list);
        return $this->ajaxReturn(['status' => 200 , 'msg'=>'ok','data' => ['list'=> $receive_id_list]]);
//        return $this->ajaxReturn($res);
        unset($receive_id_list);
        exit();

    }


    public function array_sort($arr, $keys, $type = 'desc')
    {
        $key_value = $new_array = array();
        foreach ($arr as $k => $v) {
            $key_value[$k] = $v[$keys];
        }
        if ($type == 'asc') {
            asort($key_value);
        } else {
            arsort($key_value);
        }
        reset($key_value);
        foreach ($key_value as $k => $v) {
            $new_array[$k] = $arr[$k];
        }
        return $new_array;
    }

    /**
     * 根据from_id来获取当前用户聊天列表
     */
    public function get_list_bak20191031(){
        $from_id = input('from_id','');
        $limit = input('limit','0');
        if(empty($from_id))
        {
            $this->ajaxReturn(['status' => 400 , 'msg'=>'参数错误!']);
        }
        //未读信息
        $UnreadInfo = Db::query("SELECT *,count( * ) AS nums FROM ( SELECT * FROM `chat_record` WHERE `receive_id` = {$from_id} AND is_read = 0 AND `is_show` = 1 ORDER BY `id` DESC ) a  GROUP BY `from_id`");
        //已读信息
        $ReadInfo = Db::query("SELECT * FROM `chat_record` WHERE ( `receive_id` = {$from_id} OR `from_id` = {$from_id} ) AND is_read = 1  AND `is_show` = 1 ORDER BY `id` DESC");

        for ($i = 0; $i < count($ReadInfo) ; $i++)
        {
            for ($j = $i+1; $j < count($ReadInfo); $j++)
            {
                //当接收者与发送者为同一组数据的时候
                if((($ReadInfo[$i]['receive_id'] == $ReadInfo[$j]['receive_id'] && $ReadInfo[$i]['from_id'] == $ReadInfo[$j]['from_id']) ||
                        //当接收者与发送者互为一组数据的时候
                        ($ReadInfo[$i]['receive_id']==$ReadInfo[$j]['from_id'] && $ReadInfo[$i]['from_id']==$ReadInfo[$j]['receive_id'])
                    ) &&
                    //判断当前数据没有对比的时候 （is_read做标识用）
                    $ReadInfo[$i]['is_read'] == 1 && $ReadInfo[$j]['is_read'] == 1
                )
                {
                    if($ReadInfo[$i]['add_time'] > $ReadInfo[$j]['add_time'])
                    {
                        $ReadInfo[$i]['is_read'] = 1;
                        $ReadInfo[$j]['is_read'] = 0;
                    }else{
                        $ReadInfo[$i]['is_read'] = 0;
                        $ReadInfo[$j]['is_read'] = 1;
                    }
                }
            }
        }


        foreach($ReadInfo as $k=>$v)
        {
            if($v['is_read'] == 0)
            {
                unset($ReadInfo[$k]);
            }else{
                $ReadInfo[$k]['nums'] = 0;

                if(!self::chat_deep_in_array($ReadInfo[$k]['receive_id'], $UnreadInfo) || !self::chat_deep_in_array($ReadInfo[$k]['from_id'], $UnreadInfo))
                {
                    array_push($UnreadInfo,$ReadInfo[$k]);
                }
            }
        }

        $str_id = '';
        foreach ($UnreadInfo as $key => $value)
        {
            if($value['from_id'] == $from_id)
            {
                $str_id .= $value['receive_id'].',';
            }else{
                $str_id .= $value['from_id'].',';
            }
        }
        $str_id = rtrim($str_id, ',');
        $where = explode(",",$str_id);
        $userInfo = Db::name('member')->field('id,nickname,avatar,sex')->where('id','in',$where)->select();

//        foreach ($UnreadInfo as $ks =>$vs)
//        {
//            if($vs['from_id'] == )
//        }

        return $this->ajaxReturn(['status' => 200 , 'msg'=>'ok','data'=>['list' => $UnreadInfo,'headInfo' => $userInfo,'domain' => 'https://www.9pointstars.com']]);

//        header('Access-Control-Allow-Origin:*');
//        header('Access-Control-Allow-Headers:*');
//        header("Access-Control-Allow-Methods:GET, POST, OPTIONS, DELETE");
//        header('Content-Type:application/json; charset=utf-8');
//        exit(str_replace("\\/", "/", json_encode(['status' => 200 , 'msg'=>'ok','data'=>$UnreadInfo], JSON_UNESCAPED_UNICODE)));

    }

    /**
     * @param $value
     * @param $array
     * @return bool
     * 判断二维数组是否存在某个值
     */
    function deep_in_array($value, $array) {
        foreach($array as $item) {
            if(!is_array($item)) {
                if ($item == $value) {
                    return true;
                } else {
                    continue;
                }
            }
            if(in_array($value, $item)) {
                return true;
            } else if(deep_in_array($value, $item)) {
                return true;
            }
        }
        return false;
    }



    //用户的对话列表
//    public function information_list(){
//        $user_id = $this->get_user_id();
//
//        $page = I('page')?I('page'):1;
//        $psize = 10;
//        $offset = (($page - 1) * $psize);
//        $map['user_id'] = ['eq',$user_id];
//        $map['to_user_id'] = ['eq',$user_id];
//        $map['_logic'] = 'or';
//        $amap['_complex'] = $map;
//        $res = M('information')->where($amap)->order('add_time DESC')->field('id,sender,user_id,to_user_id,content,is_see,add_time')->select();
//        // dump($res);die;
//        $new_arr = [];
//        foreach($res as $key=>$value){
//            if(($value['user_id'] == $user_id) || ($value['to_user_id'] == $user_id)){
//                if($value['user_id'] == $user_id){
//                    if( !isset($new_arr[$value['to_user_id']]) ){
//                        $new_arr[$value['to_user_id']] = $value;
//                    }
//                }else{
//                    if( !isset($new_arr[$value['user_id']]) ){
//                        $temp_me_id = $value['to_user_id'];
//                        $value['to_user_id'] = $value['user_id'];
//                        $value['user_id'] = $temp_me_id;
//                        $new_arr[$value['user_id']] = $value;
//                    }
//                }
//            }
//        }
//        // dump($new_arr);die;
//        $res = array_values($new_arr);
//
//        $lastpage = ceil(count($res) / $psize);
//
//        $list = array_slice($res,$offset,$psize);
//        if($list){
//            foreach($list as $key=>&$value){
//                $user = M('users')->where('user_id',$value['to_user_id'])->field('user_id,head_pic,nick_name')->find();
//
//                $value['kefu_id'] = $value['to_user_id'];
//                $value['nick_name'] = $user['nick_name'];
//                if($user['head_pic']){
//                    $value['head_pic'] = 'https://' . $_SERVER['SERVER_NAME'] .  $user['head_pic'];
//                }
//            }
//        }
//        $list = $this->uniquArr($list);
//        $this->ajaxReturn(['status' => 200 , 'msg'=>'获取成功!','data'=>['list'=>$list,'countPage'=>$lastpage]]);
//    }


    //生成聊天图片
    public function create_img()
    {
         $dir = ROOT_PATH . 'public' . DS .'chat' . DS . 'uploads';
         if (!file_exists($dir)){
             mkdir($dir,0777,true);
         }
        $files = request()->file('image');
       // foreach($files as $file){
            $info = $files->validate(['size'=>6145728,'ext'=>'jpg,png,gif,jpeg'])->move($dir);
            if($info){
                // 输出 jpg
                //echo $info->getExtension();
                // 输出 42a79759f284b767dfcb2a0197904287.jpg
//                echo $info->getFilename();
                $path = SITE_URL. DS .'public'. DS .'chat'. DS .'uploads'. DS .$info->getSaveName();
                $path = str_replace("\\","/",$path);

                //$image = \think\Image::open('./image.png');
                // 按照原图的比例生成一个最大为150*150的缩略图并保存为thumb.png
                //$image->thumb(150, 150)->save('./thumb.png');

                return $this->ajaxReturn(['status' => 200 , 'msg'=>'ok','path' => $path]);
            }else{
                // 上传失败获取错误信息
//                echo $file->getError();
                return $this->ajaxReturn(['status' => 500 , 'msg'=>$files->getError()]);
            }
        //}

        exit();

        $base64_image_content = I('image');
        if (preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64_image_content, $result)) {
            //图片后缀
            $type = $result[2];
            if ($type == 'jpeg') {
                $type = 'jpg';
            }
            //保存位置--图片名
            $image_name = date('His') . str_pad(mt_rand(1, 99999), 5, '0', STR_PAD_LEFT) . "." . $type;
            $image_url = '/Public/images/information/' . date('Y-m-d') . '/' . $image_name;
            if (!is_dir(dirname('./' . $image_url))) {
                mkdir(dirname('./' . $image_url));
                chmod(dirname('./' . $image_url), 0777);
                umask($image_url);
            }
            //解码
            $decode = base64_decode(str_replace($result[1], '', $base64_image_content));
            if (file_put_contents('./' . $image_url, $decode)) {
                $data['code'] = '0';
                $data['imageName'] = $image_name;
                $data['image_url'] = $image_url;
                $data['type'] = $type;
                $data['msg'] = '保存成功！';
            } else {
                $data['code'] = '1';
                $data['imgageName'] = '';
                $data['image_url'] = '';
                $data['type'] = '';
                $data['msg'] = '图片保存失败！';
            }
        } else {
            $data['code'] = '1';
            $data['imgageName'] = '';
            $data['image_url'] = '';
            $data['type'] = '';
            $data['msg'] = 'base64图片格式有误！';
        }
        if ($data['code'] == 0) {
            $img_data = $data['image_url'];
        } elseif ($data['code'] == 1) {
            $this->ajaxReturn(array('status' => -2, 'msg' => $data['msg'], 'result' => ''));
        } else {
            $this->ajaxReturn(array('status' => -2, 'msg' => '文件上传失败', 'result' => ''));
        }
        if (!$img_data) {
            $this->ajaxReturn(array('status' => -2, 'msg' => '文件上传失败', 'result' => ''));
        }
        $img_data = SITE_URL . $img_data;
        $this->ajaxReturn(array('status' => 1, 'msg' => '获取成功', 'result' => $img_data));
    }

    /**
     * 上传视频文件
     */
    public function upload_videos(){
        $files = $_FILES['video'];
        $dir = "./Public/upload/video/";
        if (!file_exists($dir)){
            mkdir($dir);
        }
        $upload = new \Think\Upload();// 实例化上传类
        $upload->maxSize = 0 ;// 设置附件上传大小
        $upload->exts = array('mp4');
        $upload->rootPath = $dir; // 设置上传跟目录
        $info   =   $upload->uploadOne($files);
        if(!$info) {
            // 上传错误提示错误信息
            $this->error($upload->getError());
        }else{
            // 上传成功 获取上传文件信息
            $infopath = $info['savepath'].$info['savename'];
            $data['path'] = SITE_URL . "/Public/upload/video/" . $infopath;
            // $data['slimg'] = $this->getVideoCover($files,1,$info['savename']);
            $this->ajaxReturn(array('status' => 1, 'msg' => '获取成功', 'data' => $data));
        }
    }

    //获得视频文件的缩略图
    function getVideoCover($file,$time,$name) {
        if(empty($time))$time = '1';//默认截取第一秒第一帧
        $strlen = strlen($file);
        // $videoCover = substr($file,0,$strlen-4);
        // $videoCoverName = $videoCover.'.jpg';//缩略图命名
        //exec("ffmpeg -i ".$file." -y -f mjpeg -ss ".$time." -t 0.001 -s 320x240 ".$name."",$out,$status);
        $str = "ffmpeg -i ".$file." -y -f mjpeg -ss 3 -t ".$time." -s 320x240 ".$name;
        //echo $str."</br>";
        $result = system($str);
        dump($result);
    }

    //获得视频文件的总长度时间和创建时间
    function getTime($file){
        $vtime = exec("ffmpeg -i ".$file." 2>&1 | grep 'Duration' | cut -d ' ' -f 4 | sed s/,//");//总长度
        $ctime = date("Y-m-d H:i:s",filectime($file));//创建时间
        //$duration = explode(":",$time);
        // $duration_in_seconds = $duration[0]*3600 + $duration[1]*60+ round($duration[2]);//转化为秒
        return array('vtime'=>$vtime,
            'ctime'=>$ctime
        );
    }

    /*
     *上传语音文件
     *media_id为微信jssdk接口上传后返回的媒体id
    */
    function upload(){
        // dump(SITE_URL);die;
        $media_id = $_POST["media_id"];//'bGa78LXQS-UhfYOKCfvP521074JdOXCR239wPsrtZ17OkBP5Y8tSQsheBgJjOLkB';
        $access_token = $this->getAccessToken();

        $path = "./Public/weixinrecord/";   //保存路径，相对当前文件的路径
        $outPath = "/Public/weixinrecord/";  //输出路径，给show.php 文件用，上一级

        if(!is_dir($path)){
            mkdir($path);
        }

        //微 信上传下载媒体文件
        $url = "http://file.api.weixin.qq.com/cgi-bin/media/get?access_token={$access_token}&media_id={$media_id}";

        $filename = "wxupload_".time().rand(1111,9999).".amr";
        $this->downAndSaveFile($url,$path."/".$filename);
        $url = str_replace("com","cn",SITE_URL);
        $data["path"] = $url . $outPath . $filename;
        $data["msg"] = "download record audio success!";
        // $data["url"] = $url;

        echo json_encode($data);
    }

    public function amrTomp3()
    {
        $file = 'https://chuen.zhifengwangluo.cn/Public/weixinrecord/wxupload_15711121865676.amr';
        $key = C('APPSECRET');
        $curl = curl_init();
        curl_setopt_array($curl, array(
            CURLOPT_URL => "https://api.xiaoliaoba.cn/amr?key=
                        ".$key."&file=".$file,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => "",
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => "GET",
        ));
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        if ($err) {
            echo "cURL Error #:" . $err;
        } else {
            echo "<pre>";
            var_dump( json_decode($response,true));
        }
    }



}
