<?php
/**
 * Author: xx.com
 */ 
namespace Api\Controller;
use Think\Controller;
class UserController extends BaseController {
    public $userLogic;
    
    /**
     * 析构流函数
     */
    public function  __construct() {   
        parent::__construct();    
    
    } 
    
    public function _initialize(){
        parent::_initialize();
        $this->userLogic = new \Home\Logic\UsersLogic();
    }
    
   
    /**
     *  登录
     */
    public function login(){
        $username = I('username','');
        $password = I('password','');
        $unique_id = I("unique_id"); // 唯一id  类似于 pc 端的session id
        $data = $this->userLogic->app_login($username,$password);
        
        if($data['status'] != 1)
            exit(json_encode($data));        
        
        $cartLogic = new \Home\Logic\CartLogic();        
        $cartLogic->login_cart_handle($unique_id,$data['result']['user_id']); // 用户登录后 需要对购物车 一些操作               
        exit(json_encode($data));
    }
    /*
     * 第三方登录
     */
    public function thirdLogin(){
        $map['openid'] = I('openid','');
        $map['oauth'] = I('from','');
        $map['nickname'] = I('nickname','');
        $map['head_pic'] = I('head_pic','');        
        $data = $this->userLogic->thirdLogin($map);
        exit(json_encode($data));
    }

    /**
     * 用户注册
     */
    public function reg(){
        $username = I('post.username','');
        $password = I('post.password','');
        $first_leader = I('post.first_leader');
       // $username2 = I('post.username','');
        
        $unique_id = I('unique_id');
        //是否开启注册验证码机制
        if(check_mobile($username) && TpCache('sms.regis_sms_enable')){
            $code = I('post.code');
            if(empty($code))
                exit(json_encode(array('status'=>-1,'msg'=>'请输入验证码','result'=>'')));
            $check_code = $this->userLogic->sms_code_verify($username,$code,$unique_id);
            if($check_code['status'] != 1)
                exit(json_encode(array('status'=>-1,'msg'=>$check_code['msg'],'result'=>'')));
        }        
        $data = $this->userLogic->reg($username,$password , $password, $first_leader);
        exit(json_encode($data));
    }

    /*
     * 获取用户信息
     */
    public function userInfo(){
        //$user_id = I('user_id');
        $data = $this->userLogic->get_info($this->user_id);
        
        exit(json_encode($data));
    }

    /*
     *更新用户信息
     */
    public function updateUserInfo(){
        if(IS_POST){
            //$user_id = I('user_id');
            if(!$this->user_id)
                exit(json_encode(array('status'=>-1,'msg'=>'缺少参数','result'=>'')));
          
            if($_FILES[img_file][tmp_name])
            {
                    $upload = new \Think\Upload();// 实例化上传类
                    $upload->maxSize   =    $map['author'] = (1024*1024*3);// 设置附件上传大小 管理员10M  否则 3M
                    $upload->exts      =     array('jpg', 'gif', 'png', 'jpeg');// 设置附件上传类型
                    $upload->rootPath  =     './Public/upload/head_pic/'; // 设置附件上传根目录
                    $upload->replace  =     true; // 存在同名文件是否是覆盖，默认为false
                    //$upload->saveName  =   'file_'.$id; // 存在同名文件是否是覆盖，默认为false
                    // 上传文件 
                    $info   =   $upload->upload();             
                    if(!$info) {// 上传错误提示错误信息                                                                                                
                        exit(json_encode(array('status'=>-1,'msg'=>$upload->getError()))); //$this->error($upload->getError());
                    }else{
                        
                    $post['head_pic'] = '/Public/upload/head_pic/'.$info['img_file']['savepath'].$info['img_file']['savename']; //头像地址
                    }                     
            } 
            I('post.nickname') ? $post['nickname'] = I('post.nickname') : false; //昵称
            I('post.qq') ? $post['qq'] = I('post.qq') : false;  //QQ号码
            (I('post.sex') != '') ? $post['sex'] = I('post.sex') : false;  // 性别
            I('post.birthday') ? $post['birthday'] = I('post.birthday') : false;  // 生日
            I('post.province') ? $post['province'] = I('post.province') : false;  //省份
            I('post.city') ? $post['city'] = I('post.city') : false;  // 城市
            I('post.district') ? $post['district'] = I('post.district') : false;  //地区
            $mobile = I('post.mobile', '', 'trim');
            if (!empty($mobile)) { //手机
                $post['mobile'] = I('post.mobile');
            }
            if(!$this->userLogic->update_info($this->user_id,$post))
                exit(json_encode(array('status'=>-1,'msg'=>'更新失败','result'=>'')));

            exit(json_encode(array('status'=>1,'msg'=>'更新成功','result'=>$post)));

        }
    }

    /*
     * 修改用户密码
     */
    public function password(){
        if(IS_POST){
            if(!$this->user_id){
                exit(json_encode(array('status'=>-1,'msg'=>'缺少参数','result'=>'')));
            }
            $data = $this->userLogic->passwordForApp($this->user_id,I('post.old_password'),I('post.new_password')); // 修改密码
            exit(json_encode($data));
        }
    }
    
    /**
     * @add by wangqh APP端忘记密码
     * 忘记密码
     */
    public function forgetPassword(){
          
            $password = I('password');
            $mobile = I('mobile');
            $unique_id = I('unique_id');        
            $checkCode = I('check_code');   //验证码

            if(!check_mobile($mobile)){
                exit(json_encode(array('status'=>-1,'msg'=>'手机号码格式不正确','result'=>'')));
            }
            
            //获取时间配置
            $sms_time_out = tpCache('sms.sms_time_out');
            $sms_time_out = $sms_time_out ? $sms_time_out : 120;
            //120秒以内不可重复发送
         
            $data = M('sms_log')->where(array('mobile'=>$mobile,'session_id'=>$unique_id))->order('id DESC')->find();
            if(!$data){
                exit(json_encode(array('status'=>-1,'msg'=>'短信验证码错误','result'=>'')));
            }else if((time() - $data['add_time']) > $sms_time_out ){
                //验证码过期
                exit(json_encode(array('status'=>-1,'msg'=>'短信验证码过期','result'=>'')));
            }
            
            $user = M('users')->where("mobile = '$mobile'")->find();
            if(!$user){
                exit(json_encode(array('status'=>-1,'msg'=>'该手机号码没有关联账户','result'=>'')));
            }else{
                //修改密码
                $pdata['password'] = encrypt($password);
                M('users')->where("user_id=".$user['user_id'])->save($pdata);
                exit(json_encode(array('status'=>1,'msg'=>'密码已重置','result'=>'')));
            }
    }
    

    /**
     * 获取收货地址
     */
    public function getAddressList(){
       //$user_id = I('user_id');
        if(!$this->user_id)
            exit(json_encode(array('status'=>-1,'msg'=>'缺少参数','result'=>'')));
            $address = M('user_address')->where(array('user_id'=>$this->user_id))->select();
        if(!$address)
            exit(json_encode(array('status'=>1,'msg'=>'没有数据','result'=>'')));
        exit(json_encode(array('status'=>1,'msg'=>'获取成功','result'=>$address)));
    }

    /*
     * 添加地址
     */
    public function addAddress(){
        //$user_id = I('user_id',0);
        if(!$this->user_id) exit(json_encode(array('status'=>-1,'msg'=>'缺少参数','result'=>'')));
        $address_id = I('post.address_id',0);
        $data = $this->userLogic->add_address($this->user_id,$address_id,I('post.')); // 获取用户信息
        exit(json_encode($data));
    }
    /*
     * 地址删除
     */
    public function del_address(){
        $id = I('address_id');
        if(!$this->user_id) exit(json_encode(array('status'=>-1,'msg'=>'缺少参数','result'=>'')));
        $address = M('user_address')->where("address_id = $id")->find();
        $row = M('user_address')->where(array('user_id'=>$this->user_id,'address_id'=>$id))->delete();                
        // 如果删除的是默认收货地址 则要把第一个地址设置为默认收货地址
        if($address['is_default'] == 1)
        {
            $address = M('user_address')->where("user_id = {$this->user_id}")->find();
            if (!empty($address)) {
                M('user_address')->where("address_id = {$address['address_id']}")->save(array('is_default'=>1));
            }          
            
        }        
        //@mobify by wangqh 
        if($row)
           exit(json_encode(array('status'=>1,'msg'=>'删除成功','result'=>''))); 
        else
           exit(json_encode(array('status'=>-1,'msg'=>'删除失败','result'=>''))); 
    } 
    /*
     * 设置默认收货地址
     */
    public function setDefaultAddress(){
//        $user_id = I('user_id',0);
        if(!$this->user_id) exit(json_encode(array('status'=>-1,'msg'=>'缺少参数','result'=>'')));
        $address_id = I('address_id',0);
        $data = $this->userLogic->set_default($this->user_id,$address_id); // 获取用户信息
        if(!$data)
            exit(json_encode(array('status'=>-1,'msg'=>'操作失败','result'=>'')));
        exit(json_encode(array('status'=>1,'msg'=>'操作成功','result'=>'')));
    }

    /*
     * 获取优惠券列表
     */
    public function getCouponList(){
        //$user_id = I('user_id',0);
        if(!$this->user_id)
            exit(json_encode(array('status'=>-1,'msg'=>'参数有误','result'=>'')));
        $data = $this->userLogic->get_coupon($this->user_id,$_REQUEST['type']);
        unset($data['show']);
        exit(json_encode($data));
    }
    /*
     * 获取商品收藏列表
     */
    public function getGoodsCollect(){
//        $user_id = I('user_id',0);
        //if(!$this->user_id) exit(json_encode(array('status'=>-1,'msg'=>'缺少参数','result'=>'')));
        $data = $this->userLogic->get_goods_collect($this->user_id);
        foreach($data['result'] as &$r){

        }
        unset($data['show']);
        exit(json_encode($data));
    }

    /*
     * 用户订单列表
     */
    public function getOrderList(){
       // $user_id = I('user_id',0);
        $type = I('post.type','');
        if(!$this->user_id) exit(json_encode(array('status'=>-1,'msg'=>'缺少参数','result'=>'')));
    // 'WAITPAY'=>' AND pay_status = 0 AND order_status = 0 AND pay_code !="cod" ', //订单查询状态 待支付
    // 'WAITSEND'=>' AND (pay_status=1 OR pay_code="cod") AND shipping_status !=1 AND order_status in(0,1) ', //订单查询状态 待发货
    // 'WAITRECEIVE'=>' AND shipping_status=1 AND order_status = 1 ', //订单查询状态 待收货    
    // 'WAITCCOMMENT'=> ' AND order_status=2 ', // 待评价 确认收货     //'FINISHED'=>'  AND order_status=1 ', //订单查询状态 已完成 
    // 'FINISH'=> ' AND order_status = 4 ', // 已完成
    // 'CANCEL'=> ' AND order_status = 3 ', // 已取消
    // 'CANCELLED'=> 'AND order_status = 5 ',//已作废
        $map = " user_id = {$this->user_id} ";        
        $map = $type ? $map.C($type) : $map;   
        
        
        if(I('type') )
        $count = M('order')->where($map)->count();
        $Page       = new \Think\Page($count,10);

        $show = $Page->show();
        $order_str = "order_id DESC";
        $order_list = M('order')->order($order_str)->where($map)->limit($Page->firstRow.','.$Page->listRows)->select();

        //获取订单商品
        foreach($order_list as $k=>$v){
            $order_list[$k] = set_btn_order_status($v);  // 添加属性  包括按钮显示属性 和 订单状态显示属性
            $rebate_status = M('rebate_log')->field('status')->where(array('order_id'=>$v['order_id']))->find();
            $order_list[$k]['rebate_status'] = $rebate_status['status'] ? $rebate_status['status'] : 0;
            //订单总额
            //$order_list[$k]['total_fee'] = $v['goods_amount'] + $v['shipping_fee'] - $v['integral_money'] -$v['bonus'] - $v['discount'];
            $data = $this->userLogic->get_order_goods($v['order_id']);
            $order_list[$k]['goods_list'] = $data['result'];            
        }
        exit(json_encode(array('status'=>1,'msg'=>'获取成功','result'=>$order_list)));
    }
    /*
     * 获取订单详情
     */
    public function getOrderDetail(){
        //$user_id = I('user_id',0);
        if(!$this->user_id) exit(json_encode(array('status'=>-1,'msg'=>'缺少参数','result'=>'')));
        $id = I('id');
        if(I('id')){
            $map['order_id'] = $id;
        }else{
            $map['order_sn'] = I('sn');
        }
        $map['user_id'] = $this->user_id;
        $order_info = M('order')->where($map)->find();
        if($order_info['pay_status'] && !$order_info['pay_name']){
            $order_info['pay_name'] = '其他支付方式';
        }
        $order_info = set_btn_order_status($order_info);  // 添加属性  包括按钮显示属性 和 订单状态显示属性
        
        if(!$this->user_id > 0)
            exit(json_encode(array('status'=>-1,'msg'=>'参数有误','result'=>'')));
        if(!$order_info){
            exit(json_encode(array('status'=>-1,'msg'=>'订单不存在','result'=>'')));
        }
        
        $invoice_no = M('DeliveryDoc')->where("order_id = $id")->getField('invoice_no',true);
        $order_info['invoice_no'] = implode(' , ', $invoice_no);
        // 获取 最新的 一次发货时间
        $order_info['shipping_time'] = M('DeliveryDoc')->where("order_id = $id")->order('id desc')->getField('create_time');        
        
        //获取订单商品
        $data = $this->userLogic->get_order_goods($order_info['order_id']);
        $order_info['goods_list'] = $data['result'];
        //$order_info['total_fee'] = $order_info['goods_price'] + $order_info['shipping_price'] - $order_info['integral_money'] -$order_info['coupon_price'] - $order_info['discount'];
        exit(json_encode(array('status'=>1,'msg'=>'获取成功','result'=>$order_info)));
    }

    /**
     * 取消订单
     */
    public function cancelOrder(){
        $id = I('order_id');
//        $user_id = I('user_id',0);
        if(!$this->user_id > 0 || !$id > 0)
            exit(json_encode(array('status'=>-1,'msg'=>'参数有误','result'=>'')));
        $data = $this->userLogic->cancel_order($this->user_id,$id);
        exit(json_encode($data));
    }
    
    /**
     * 发送手机注册验证码
     * http://www.xx.com/index.php?m=Api&c=User&a=send_sms_reg_code&mobile=13800138006&unique_id=123456
     */
    public function send_sms_reg_code(){
        $mobile = I('mobile');     
        $unique_id = I('unique_id');
        if(!check_mobile($mobile))
            exit(json_encode(array('status'=>-1,'msg'=>'手机号码格式有误')));
        $code =  rand(1000,9999);
        //验证是否已经注册

        if(get_user_info($mobile,1)||get_user_info($mobile,2)){
            exit(json_encode(array('status'=>-1,'msg'=>'手机号码已注册')));
        }

        //$send = $this->userLogic->sms_log($mobile,$code,$unique_id);
        $send = $this->userLogic->sms_liuniu_log($mobile,$code,$unique_id);
        if($send['status'] != 1)
            exit(json_encode(array('status'=>-1,'msg'=>$send['msg'])));
        exit(json_encode(array('status'=>1,'msg'=>'验证码已发送，请注意查收')));
    }    

    //发送验证码
    public function send_validate_code(){
        $send = I('send');
        $unique_id = I('unique_id');
        if(!check_mobile($send))
            exit(json_encode(array('status'=>-1,'msg'=>'手机号码格式有误')));
        $code =  rand(1000,9999);
        $res = $this->userLogic->sms_liuniu_log($send, $code, $unique_id);
        if($res['status'] != 1)
            exit(json_encode(array('status'=>-1,'msg'=>$res['msg'])));
        exit(json_encode(array('status'=>1,'msg'=>'验证码已发送，请注意查收')));
    }

    /*
    * 手机验证
    */
    public function mobile_validate(){
        $user_info = $this->userLogic->get_info($this->user_id); //获取用户信息
        $user_info = $user_info['result'];
        if(!$user_info['user_id'] > 0)
            exit(json_encode(array('status'=>-1,'msg'=>'参数有误','result'=>'')));
        $config = F('sms','',TEMP_PATH);
        $sms_time_out = $config['sms_time_out'];
        $mobile = I('post.mobile');
        $old_mobile = I('post.old_mobile','');
        $code = I('post.code');
        $unique_id = I('post.unique_id');
        
        $data = M('sms_log')->where(array('mobile'=>$mobile,'session_id'=>$unique_id))->order('id DESC')->find();
        if(!$data){
            exit(json_encode(array('status'=>-1,'msg'=>'短信验证码错误','result'=>'')));
        }else if((time() - $data['add_time']) > $sms_time_out ){
            //验证码过期
            exit(json_encode(array('status'=>-1,'msg'=>'短信验证码过期','result'=>'')));
        }

        //检查原手机是否正确
        if($user_info['mobile_validated'] == 1 && $old_mobile != $user_info['mobile'])
            exit(json_encode(array('status'=>-1,'msg'=>'原手机号码错误')));
        //验证手机和验证码
        if($data['mobile'] == $mobile && $data['code'] == $code){
            if(!$this->userLogic->update_email_mobile($mobile,$this->user_id,2))
                exit(json_encode(array('status'=>-1,'msg'=>'手机已存在')));
            exit(json_encode(array('status'=>1,'msg'=>'绑定成功')));
        }
        exit(json_encode(array('status'=>-1,'msg'=>'手机验证码不匹配')));
    }

    /**
     *  收货确认
     */
    public function orderConfirm(){
        $id = I('order_id',0);
        //$user_id = I('user_id',0);
        if(!$this->user_id || !$id)
            exit(json_encode(array('status'=>-1,'msg'=>'参数有误','result'=>'')));
        $data = confirm_order($id,$this->user_id);
        if($data['status']){
            // 根据order表查看消费记录 给他会员等级升级 修改他的折扣 和 总金额zn
            update_user_level($this->user_id);
        }
        //获取用户等级信息
        $userInfo = $this->userLogic->get_info($this->user_id);
        $data['result'] = array('level_name'=>$userInfo['result']['level_name'], 'level'=>$userInfo['result']['level']);
        exit(json_encode($data));
    }
    
    
    /*
     *添加评论
     */
    public function add_comment(){                
      
            // 晒图片        
            if($_FILES[img_file][tmp_name][0])
            {
                    $upload = new \Think\Upload();// 实例化上传类
                    $upload->maxSize   =    $map['author'] = (1024*1024*3);// 设置附件上传大小 管理员10M  否则 3M
                    $upload->exts      =     array('jpg', 'gif', 'png', 'jpeg');// 设置附件上传类型
                    $upload->rootPath  =     './Public/upload/comment/'; // 设置附件上传根目录
                    $upload->replace  =     true; // 存在同名文件是否是覆盖，默认为false
                    //$upload->saveName  =   'file_'.$id; // 存在同名文件是否是覆盖，默认为false
                    // 上传文件 
                    $info   =   $upload->upload();                 
                    if(!$info) {// 上传错误提示错误信息                                                                                                
                        exit(json_encode(array('status'=>-1,'msg'=>$upload->getError()))); //$this->error($upload->getError());
                    }else{
                        foreach($info as $key => $val)
                        {
                            $comment_img[] = '/Public/upload/comment/'.$val['savepath'].$val['savename'];                            
                        }   
                        $comment_img = serialize($comment_img); // 上传的图片文件
                    }                     
            }         
         
         
            
            $unique_id = I("unique_id"); // 唯一id  类似于 pc 端的session id
            //$user_id = I('user_id'); // 用户id
            $user_info = M('users')->where("user_id = {$this->user_id}")->find();            

            $add['goods_id'] = I('goods_id');
            $add['email'] = $user_info['email'];
            //$add['nick'] = $user_info['nickname'];
            $add['username'] = empty($user_info['nickname']) ? '用户'.$this->user_id : $user_info['nickname'] ; //用户nickname为空，取用户id
            $add['order_id'] = I('order_id');
            $add['service_rank'] = I('service_rank');
            $add['deliver_rank'] = I('deliver_rank');
            $add['goods_rank'] = I('goods_rank');
           // $add['content'] = htmlspecialchars(I('post.content'));
            $add['content'] = I('content');
            $add['img'] = $comment_img;
            $add['add_time'] = time();
            $add['ip_address'] = $_SERVER['REMOTE_ADDR'];
            $add['user_id'] = $this->user_id;                    
            
            //添加评论
            $row = $this->userLogic->add_comment($add);
            exit(json_encode($row));
    }  
    
    /*
     * 账户资金
     */
    public function account(){
        
        $unique_id = I("unique_id"); // 唯一id  类似于 pc 端的session id
       // $user_id = I('user_id'); // 用户id
        //获取账户资金记录
        
        $data = $this->userLogic->get_account_log($this->user_id,I('get.type'));
        $account_log = $data['result'];
        exit(json_encode(array('status'=>1,'msg'=>'获取成功','result'=>$account_log)));
    }    
    
    /**
     * 退换货列表
     */
    public function return_goods_list()
    {        
        
        $unique_id = I("unique_id"); // 唯一id  类似于 pc 端的session id
       // $user_id = I('user_id'); // 用户id       
        $count = M('return_goods')->where("user_id = {$this->user_id}")->count();        
        $page = new \Think\Page($count,4);
        $list = M('return_goods')->where("user_id = {$this->user_id}")->order("id desc")->limit("{$page->firstRow},{$page->listRows}")->select();
        $goods_id_arr = get_arr_column($list, 'goods_id');
        if(!empty($goods_id_arr))
            $goodsList = M('goods')->where("goods_id in (".  implode(',',$goods_id_arr).")")->getField('goods_id,goods_name');        
        foreach ($list as $key => $val)
        {
            $val['goods_name'] = $goodsList[$val[goods_id]];
            $list[$key] = $val;
        }
        //$this->assign('page', $page->show());// 赋值分页输出                    	    	
        exit(json_encode(array('status'=>1,'msg'=>'获取成功','result'=>$list)));
    }    
    
    
    /**
     *  售后 详情
     */
    public function return_goods_info()
    {
        $id = I('id',0);
        $return_goods = M('return_goods')->where("id = $id")->find();
        if($return_goods['imgs'])
            $return_goods['imgs'] = explode(',', $return_goods['imgs']);        
        $goods = M('goods')->where("goods_id = {$return_goods['goods_id']} ")->find();                
        $return_goods['goods_name'] = $goods['goods_name'];
        exit(json_encode(array('status'=>1,'msg'=>'获取成功','result'=>$return_goods)));
    }    
    
    
    /**
     * 申请退货状态
     */
    public function return_goods_status()
    {
        $order_id = I('order_id',0);        
        $goods_id = I('goods_id',0);

        if(!$order_id || !$goods_id){
            exit(json_encode(array('status'=>-1,'msg'=>'参数有误','result'=>'')));
        }
        $where['order_id'] = $order_id;
        $where['goods_id'] = $goods_id;

        $spec_key = I('spec_key','');
        if($spec_key){
            $where['spec_key'] = $spec_key;
        }
        $where['status'] = array('in','0,1');

        $return_goods = M('return_goods')->where($where)->find();

        if($return_goods){
            exit(json_encode(array('status'=>2,'msg'=>'已经提交过申请','result'=>$return_goods['id'])));
        } else{
            exit(json_encode(array('status'=>1,'msg'=>'可以去申请退货','result'=>-1)));
        }

    }
    /**
     * 申请退货
     */
    public function return_goods()
    {
        $unique_id = I("unique_id"); // 唯一id  类似于 pc 端的session id
        //$user_id = I('user_id'); // 用户id              
        $order_id = I('order_id',0);
        $order_sn = I('order_sn',0);
        $goods_id = I('goods_id',0);
        $type = I('type',0); // 0 退货  1为换货
        $reason = I('reason',''); // 问题描述
        $spec_key = I('spec_key');

        //if(empty($order_id) || empty($order_sn) || empty($goods_id)|| empty($this->user_id)|| empty($type)|| empty($reason))    //empty($type) == 1
        if(empty($order_id) || empty($order_sn) || empty($goods_id)|| empty($this->user_id)|| empty($reason))
            exit(json_encode(array('status'=>-1,'msg'=>'参数不齐!')));
        
        $c = M('order')->where("order_id = $order_id and user_id = {$this->user_id}")->count();
        if($c == 0)
        {
             exit(json_encode(array('status'=>-3,'msg'=>'非法操作!')));           
        }         
        
        $return_goods = M('return_goods')->where("order_id = $order_id and goods_id = $goods_id and spec_key = '$spec_key' and status in(0,1)")->find();            
        if(!empty($return_goods))
        {
            exit(json_encode(array('status'=>-2,'msg'=>'已经提交过退货申请!')));
        }       
        if(IS_POST)
        {
            
    		// 晒图片
    		if($_FILES[img_file][tmp_name][0])
    		{
    			$upload = new \Think\Upload();// 实例化上传类
    			$upload->maxSize   =    $map['author'] = (1024*1024*3);// 设置附件上传大小 管理员10M  否则 3M
    			$upload->exts      =    array('jpg', 'gif', 'png', 'jpeg');// 设置附件上传类型
    			$upload->rootPath  =    './Public/upload/return_goods/'; // 设置附件上传根目录
    			$upload->replace   =    true; // 存在同名文件是否是覆盖，默认为false
    			//$upload->saveName  =  'file_'.$id; // 存在同名文件是否是覆盖，默认为false
    			// 上传文件
    			$upinfo  =  $upload->upload();
    			if(!$upinfo) {// 上传错误提示错误信息
    				$this->error($upload->getError());
    			}else{
    				foreach($upinfo as $key => $val)
    				{
    					$return_imgs[] = '/Public/upload/return_goods/'.$val['savepath'].$val['savename'];
    				}
    				$data['imgs'] = implode(',', $return_imgs);// 上传的图片文件
    			}
    		}            
            $data['order_id'] = $order_id; 
            $data['order_sn'] = $order_sn; 
            $data['goods_id'] = $goods_id; 
            $data['addtime'] = time(); 
            $data['user_id'] = $this->user_id;            
            $data['type'] = $type; // 服务类型  退货 或者 换货
            $data['reason'] = $reason; // 问题描述            
            $data['spec_key'] = $spec_key; // 商品规格						
            $return_id = M('return_goods')->add($data);
            if($return_id) {
                //申请退货，取消返现
                $result_info = M('rebate_log')->where(array('order_id' => $order_id))->setField('status', 4);
                //修改商品申请退货状态
                $order_goods = array(
                    'apply_return' => 1,
                    //'is_comment'   => 2,    //申请退换货，不允许评论
                );
                $order_goods_info = M('order_goods')->where(array('order_id' => $order_id, 'goods_id' => $goods_id))->save($order_goods);
                exit(json_encode(array('status' => 1, 'msg' => '申请成功,客服第一时间会帮你处理!')));
            } else {
                exit(json_encode(array('status' => -1, 'msg' => '申请失败!')));
            }
        }     
    }  

    // base64上传头像
    public function editavator(){
        if(!$this->user_id)
                exit(json_encode(array('status'=>-1,'msg'=>'缺少参数','result'=>'')));
        $img = I('avatar');
        
        $return = $this->img_upload($img);
        if($return['status']==1){
            $data['user_id'] = $this->user_id;  
            $data['head_pic'] = '/Public/upload/'.$return['url'];
            M('users')->where('user_id=' .$data['user_id'])->save($data);
            exit(json_encode(array('status'=>$return['status'], 'msg'=>$return['msg'], 'result'=>'/Public/upload/'.$return['url'])));
        }else{
            exit(json_encode(array('status'=>$return['status'],'msg'=>$return['msg'])));
        }    
            
    }
    
    
    /**
     * base64图片上传
     * @param $base64_img
     * @return array
     */
    private static function img_upload($base64_img){
        $base64_img = trim($base64_img);
        $time = time();
        $date = date('Y');
        $date1 = date('m-d');
        $up_dir = './Public/upload/head_pic/'.$date.'/'.$date1.'/';
     
        if(!file_exists($up_dir)){
            mkdir($up_dir,0777,true);
        }
        if(preg_match('/^(data:\s*image\/(\w+);base64,)/', $base64_img, $result)){
            $type = $result[2];
            if(in_array($type,array('pjpeg','jpeg','jpg','gif','bmp','png'))){
                $new_file = $up_dir.$time.'.'.$type;
                //var_dump($new_file);exit;
                if(file_put_contents($new_file, base64_decode(str_replace($result[1], '', $base64_img)))){
                    $img_path = str_replace('./Public/upload/', '', $new_file);
                    return array('status' => 1, 'msg' => "图片上传成功", 'url' => $img_path);
                }
                return array('status' => 2, 'msg' => "图片上传失败");
            }
            //文件类型错误
            return array('status' => 4, 'msg' => "文件类型错误");
        }
        //文件错误
        return array('status' => 3, 'msg' => "文件错误");
    }
    /**
     * 用户提现申请
     */
    public function withdrawals(){
        $data['user_id'] = $this->user_id;      //获取用户的Id
        $data['money'] = I('post.money', 0, 'floatval');                     //获取用户的提现金额
        $data['bank_name'] = I('post.bank_name', '', 'trim');             //银行名称  如:支付宝,农业银行,工商银行等
        $data['account_bank'] = I('post.account_bank', '', 'trim');       //收款账号
        $data['account_name'] = I('post.account_name', '', 'trim');       //开户名
        foreach($data as $key=>$val){
            if(empty($val)){
                switch ($key){
                    case 'user_id' : $message = '用户信息有误';break;
                    case 'money' : $message = '提现金额有误';break;
                    case 'bank_name' : $message = '银行名称不能为空';break;
                    case 'account_bank' : $message = '收款账号不能为空';break;
                    case 'account_name' : $message = '开户姓名不能为空';break;
                }
                exit(json_encode(array('status'=>-1,'msg'=>$message)));
            }
        }
        $data['create_time'] = time();  
        $distribut_min = tpCache('distribut.min');          //最少提现额度
        if($data['money'] < $distribut_min)
            exit(json_encode(array('status'=>-1,'msg'=>'每次最少提现额度'.$distribut_min)));
        $user = M('users')->where("user_id = {$data['user_id']}")->find();
        if($data['money'] > $user['user_money'])
            exit(json_encode(array('status'=>-1,'msg'=>'你最多可提现'.$user['user_money'].'账户余额')));
        if(M('withdrawals')->add($data))
            exit(json_encode(array('status'=>1,'msg'=>'提交申请成功')));
        else
            exit(json_encode(array('status'=>-1,'msg'=>'提交失败,请联系客服人员!')));     
    } 
    /**
     * 用户提现充值数据列表
     */
    public function userMoneyRecord(){
        //获取用户ID
        $user_id = $this->user_id;
        $page = I('post.p',0,'intval');
        if(!($page>0 && $user_id>0))        
            exit(json_encode(array('status'=>-1,'msg'=>'缺少参数')));
        $limit = 6;
        $start = ($page-1)*$limit; 
        $subQuery = M()->table(C('DB_PREFIX').'withdrawals a')->field('1 as type,a.user_id as user_id,a.create_time as time,a.money as money,a.status as status,a.bank_name as pay_type')
        ->union('select 2 as type,b.user_id as user_id,b.ctime as time,b.account as money,b.pay_status as status,b.pay_name as pay_type from '.C('DB_PREFIX').'recharge b')->buildSql();
        $result = M()->table($subQuery.'a')->where("a.user_id = {$user_id}")->order('time desc')->limit("{$start},{$limit}")->select();
        $weekarray = array("日","一","二","三","四","五","六");
        for($i = 0;$i<count($result);$i++){
            $result[$i]['week'] = "周".$weekarray[date('w',$result[$i]['time'])];
            $result[$i]['date'] = date('m-d',$result[$i]['time']);
        }
        if(empty($result))
            exit(json_encode(array('status'=>1,'msg'=>'空数据','result'=>$result)));
        else
            exit(json_encode(array('status'=>1,'msg'=>'获取成功','result'=>$result)));
    }

    /**
     * 选择充值方式
     */
    public function rechargeType(){       
        $paymentList = M('Plugin')->field('name,type,code,icon')->where("`type`='payment' and code != 'cod' and 
           status = 1 and  scene in(0,2)")->select();
        if(empty($paymentList)){
            exit(json_encode(array('status' =>'-1','msg'=>'数据为空')));
        }else{
            for($i = 0;$i < count($paymentList);$i++){
                $paymentList[$i]['image_url'] = "/plugins/".$paymentList[$i]['type']."/".$paymentList[$i]['code']."/".$paymentList[$i]['icon'];          
                unset($paymentList[$i]['type']);
                unset($paymentList[$i]['icon']);
            }
            exit(json_encode(array('status' => '1','msg' => '获取成功','result' => $paymentList)));
        }
    }
    //查询物流信息
    public function getExpressInfo(){
        $order_id = I('order_id');
        $express_info = selectExpress($order_id);
        if($express_info['is_success']){
            exit(json_encode(array('status'=>1,'msg'=>'','result'=>$express_info['data'])));
        }else{
            exit(json_encode(array('status'=>-1, 'msg'=>$express_info['msg'], 'result'=>'')));
        }
    }
    /**
     * 用户充值
     */
    public function userRecharge(){
        if(!$this->user_id)
            exit(json_encode(array('status' => -1,'msg' => '缺少参数')));
        else{
            $user_info = M('users')->where(array('user_id'=>$this->user_id))->find(); //获取用户信息
            $data['user_id'] = $this->user_id;
            $data['nickname'] = $user_info['nickname'];
            $data['account'] = I('account');
            $data['order_sn'] = 'recharge'.get_rand_str(10,0,1);
            $data['ctime'] = time();
            $data['pay_code'] = I('pay_code');
            if (I('pay_code') == 'alipay') {
                $data['pay_name'] = 'app支付宝';
            } elseif (I('pay_code') == 'weixin') {
                $data['pay_name'] = '微信支付';
            } else {
                exit(json_encode(array('status' => -1,'msg' => '支付方式不正确!')));
            }
            $order_id = M('recharge')->add($data);
            if($order_id){
                //直接跳转到支付宝支付
                if (I('pay_code') == 'alipay') {
                    $payment = R('Api/Payment/doRechangePay', array('order_id'=>$order_id));
                } elseif (I('pay_code') == 'weixin') {
                    $payment = R('Api/Wxpay/rechangePay', array('order_id'=>$order_id));
                }
            }else{
                exit(json_encode(array('status'=>-1, 'msg'=>'提交失败,参数有误!', 'result'=>'')));
            }
        }
    }
    
}