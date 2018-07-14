<?php
/**
 * Author: xx.com
 */
namespace Mobile\Controller;
use Home\Logic\UsersLogic;
use Think\Page;
use Think\Verify;

class DistributController extends MobileBaseController {
        /*
        * 初始化操作
        */
    public function _initialize() {
        parent::_initialize();
        if(session('?user'))
        {
        	$user = session('user');
        	$this->user = $user;
        	$this->user_id = $user['user_id'];
        	$this->assign('user',$user); //存储用户信息
        }        
        $nologin = array(
        	'login','pop_login','do_login','logout','verify','set_pwd','finished',
        	'verifyHandle','reg','send_sms_reg_code','find_pwd','check_validate_code',
        	'forget_pwd','check_captcha','check_username','send_validate_code',
        );
        if(!$this->user_id && !in_array(ACTION_NAME,$nologin)){
        	header("location:".U('Mobile/User/login'));
        	exit;
        }
        
        $order_count = M('order')->where("user_id = {$this->user_id}")->count(); // 我的订单数
        $goods_collect_count = M('goods_collect')->where("user_id = {$this->user_id}")->count(); // 我的商品收藏
        $comment_count = M('comment')->where("user_id = {$this->user_id}")->count();//  我的评论数
        $coupon_count = M('coupon_list')->where("uid = {$this->user_id}")->count(); // 我的优惠券数量        
        $first_nickname = M('users')->where("user_id = {$this->user['first_leader']}")->getField('nickname');        
        $level_name = M('user_level')->where("level_id = {$this->user['level']}")->getField('level_name'); // 等级名称
        $this->assign('level_name',$level_name);        
        $this->assign('first_nickname',$first_nickname);        
        $this->assign('order_count',$order_count);
        $this->assign('goods_collect_count',$goods_collect_count);
        $this->assign('comment_count',$comment_count);
        $this->assign('coupon_count',$coupon_count); 
    }
  
    /**
     * 分销用户中心首页
     */
    public function index(){
        // 销售额 和 我的奖励
        $result = M()->query("select sum(goods_price) as goods_price, sum(money) as money from __PREFIX__rebate_log where user_id = {$this->user_id}");        
        $result = $result[0];
        $result['goods_price'] = $result['goods_price'] ? $result['goods_price'] : 0;
        $result['money'] = $result['money'] ? $result['money'] : 0;        
                
         $lower_count[1] = M('users')->where("first_leader = {$this->user_id}")->count();
         $lower_count[2] = M('users')->where("second_leader = {$this->user_id}")->count();
         //$lower_count[3] = M('users')->where("third_leader = {$this->user_id}")->count();
         
        // 我的下线 订单数
        $result2 = M()->query("select status,count(1) as c , sum(goods_price) as goods_price from `__PREFIX__rebate_log` where user_id = {$this->user_id} group by status");        
        $level_order = convert_arr_key($result2, 'status');
        for($i = 0; $i <= 5; $i++)
        {
            $level_order[$i]['c'] = $level_order[$i]['c'] ? $level_order[$i]['c'] : 0;
            $level_order[$i]['goods_price'] = $level_order[$i]['goods_price'] ? $level_order[$i]['goods_price'] : 0;
        }
        
        $withdrawals_money = M('withdrawals')->where("user_id = {$this->user_id} and `status` = 1")->sum('money');
        
        //print_r($level_order);
        $this->assign('level_order',$level_order); // 下线订单        
        $this->assign('lower_count',$lower_count); // 下线人数        
        $this->assign('sales_volume',$result['goods_price']); // 销售额
        $this->assign('reward',$result['money']);// 奖励
        $this->assign('withdrawals_money',$withdrawals_money);// 已提现财富
                
        $this->display();
    }
    
    /**
     * 下线列表
     */
    public function lower_list(){
        $level = I('get.level',1);         
        $q = I('post.q','','trim');
        $condition = array(1=>'first_leader',2=>'second_leader');
        
        $where = "{$condition[$level]} = {$this->user_id}";
        $q && $where .= " and (nickname like '%$q%' or user_id = '$q' or mobile = '$q')";
        
        $count = M('users')->where($where)->count();               
        $page = new Page($count,10);
        $list = M('users')
            ->field('user_id, email, sex, mobile, head_pic, nickname, level, first_leader, second_leader, reg_time')
            ->where($where)
            ->limit("{$page->firstRow},{$page->listRows}")
            ->order('user_id desc')
            ->select();
        
        $this->assign('count', $count);// 总人数
        $this->assign('page', $page->show());// 赋值分页输出
        $this->assign('list',$list); // 下线
        if($_GET['is_ajax'])
        {
            $this->display('ajax_lower_list');
            exit;
        }                
        $this->display();
    }    
    
    /**
     * 下线订单列表
     */
    public function order_list(){
        $status = I('get.status',0);        
        $where = " user_id = {$this->user_id} and status in($status)";        
        $count = M('rebate_log')->where($where)->count();               
        $page = new Page($count,10);
        $list = M('rebate_log')->where($where)->order('id desc')->limit("{$page->firstRow},{$page->listRows}")->select();
        $user_id_list = get_arr_column($list, 'buy_user_id');
        if(!empty($user_id_list))
            $userList = M('users')->where("user_id in (".  implode(',', $user_id_list).")")->getField('user_id,nickname,mobile,head_pic');                        
        
        $this->assign('count', $count);// 总人数
        $this->assign('page', $page->show());// 赋值分页输出        
        $this->assign('userList',$userList); // 
        $this->assign('list',$list); // 下线
        if($_GET['is_ajax'])
        {
            $this->display('ajax_order_list');
            exit;
        }                
        $this->display();
    } 

    
    /**
     * 验证码验证
     * $id 验证码标示
     */
    private function verifyHandle($id)
    {
        $verify = new Verify();
        if (!$verify->check(I('post.verify_code'), $id ? $id : 'user_login')) {
            $this->error("验证码错误");
        }
    }

    /**
     * 验证码获取
     */
    public function verify()
    {
        //验证码类型
        $type = I('get.type') ? I('get.type') : 'user_login';
        $config = array(
            'fontSize' => 40,
            'length' => 4,
            'useCurve' => true,
            'useNoise' => false,
        );
        $Verify = new Verify($config);
        $Verify->entry($type);
    }    
    
    /*
     *个人推广二维码 
     */
    public function qr_code(){
        $ShareLink = urlencode("http://{$_SERVER[HTTP_HOST]}/index.php?m=Mobile&c=Index&a=index&first_leader={$this->user_id}"); //默认分享链接                  
        if($this->user['is_distribut'] == 1)
            $this->assign('ShareLink',$ShareLink);
        $this->display();
    }  
}