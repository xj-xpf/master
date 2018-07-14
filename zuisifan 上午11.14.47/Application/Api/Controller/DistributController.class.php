<?php
/**
 * Author: xx.com
 */
namespace Api\Controller;
use Think\Page;
use Think\Verify;

class DistributController extends BaseController {

    /*
    * 初始化操作
    */
    public function  __construct() {
        parent::__construct();
    }

    public function _initialize(){
        parent::_initialize();
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

        $lower_count[] = $lower_count_one = M('users')->where("first_leader = {$this->user_id}")->count();
        $lower_count[] = $lower_count_two = M('users')->where("second_leader = {$this->user_id}")->count();
        //三级分销改二级
        //$lower_count[] = $lower_count_three = M('users')->where("third_leader = {$this->user_id}")->count();


        // 我的下线 订单数
        $result2 = M()->query("select status,count(1) as c , sum(goods_price) as goods_price, sum(money) as money from `__PREFIX__rebate_log` where user_id = {$this->user_id} group by status");

        $level_order = convert_arr_key($result2, 'status');
        for($i = 0; $i < 5; $i++)
        {
            $level_order2[$i]['c'] = $level_order[$i]['c'] ? $level_order[$i]['c'] : 0;
            $level_order2[$i]['goods_price'] = $level_order[$i]['goods_price'] ? $level_order[$i]['goods_price'] : 0;
            $level_order2[$i]['money'] = $level_order[$i]['money'] ? $level_order[$i]['money'] : 0;
            $level_order2[$i]['status'] = $level_order[$i]['status'] ? $level_order[$i]['status'] : $i;

            $level_order[$i]['c'] = $level_order[$i]['c'] ? $level_order[$i]['c'] : 0;
            $level_order[$i]['goods_price'] = $level_order[$i]['goods_price'] ? $level_order[$i]['goods_price'] : 0;
            $level_order[$i]['money'] = $level_order[$i]['money'] ? $level_order[$i]['money'] : 0;
            $level_order[$i]['status'] = $level_order[$i]['status'] ? $level_order[$i]['status'] : $i;
        }
        $withdrawals_money = M('withdrawals')->where("user_id = {$this->user_id} and `status` = 1")->sum('money');

        $distributInfo['lower_count'] = $lower_count; // 下线人数
        $distributInfo['lower_count_one'] = $lower_count_one;
        $distributInfo['lower_count_two'] = $lower_count_two;
        //$distributInfo['lower_count_three'] = $lower_count_three;

        $distributInfo['sales_volume'] = $level_order[0]['goods_price'] + $level_order[1]['goods_price'] + $level_order[2]['goods_price'] + $level_order[3]['goods_price']; // 销售额
        $distributInfo['reward'] = $result['money'];// 奖励
        $withdrawals_money = $withdrawals_money == null ? 0 : $withdrawals_money;// 已提现财富
        $distributInfo['withdrawals_money'] = $withdrawals_money ;

        $user = M('users')->where("user_id = {$this->user_id}")->find();
        $distributInfo['user_money'] = $user['user_money'];             //余额

        $count_order = 0;
        $count_price = 0;
        foreach ($level_order as $value){
            $count_order += $value['c'];
            $count_price += $value['goods_price'];
        }
        $distributInfo['count_order'] = $count_order;           //订单总数
        $distributInfo['count_price'] = $count_price;           //订单金额总数

        $distributInfo['count_no_payment'] = $level_order[0]['c'] + $level_order[4]['c'];           //下单未购买
        $distributInfo['price_no_payment'] = $level_order[0]['goods_price'] + $level_order[4]['goods_price'];           //下单未购买

        $distributInfo['count_payment'] = $level_order[1]['c'] + $level_order[2]['c'] + $level_order[3]['c'];           //下单已购买订单数量
        $distributInfo['price_payment'] = $level_order[1]['goods_price'] + $level_order[2]['goods_price'] + $level_order[3]['goods_price'];//下单已购买

        $distributInfo['count_received'] = $level_order[2]['c']+$level_order[3]['c'];       //已收货订单
        $distributInfo['price_received'] = $level_order[2]['goods_price'];           //已收货订单金额
        $distributInfo['alloted_price'] = $level_order[3]['goods_price'];               //已分成订单财富
        //$distributInfo['level_order'] = $level_order2; // 下线订单


        $distributInfo['price_no_payed'] = $level_order[0]['goods_price'];    //未付款订单财富
        $distributInfo['price_payed'] = $level_order[1]['goods_price'];     //已付款订单财富
        $json_arr = array('status'=>1,'msg'=>'获取成功','result'=>$distributInfo );
        $json_str = json_encode($json_arr);
        exit($json_str);
    }

    /**
     * 下线列表
     */
    public function lower_list(){
        $level = I('level', 1, 'intval');
        $q = I('q', '', 'trim');
        $p = I('p', 1, 'intval');
        //三级分销改二级
        //$condition = array(1=>'first_leader',2=>'second_leader',3=>'third_leader');
        $condition = array(1=>'first_leader',2=>'second_leader');

        $where = "{$condition[$level]} = {$this->user_id}";
        $q && $where .= " and (nickname like '%$q%' or user_id = '$q' or mobile = '$q')";

        $count = M('users')->where($where)->count();

        $list = M('users')
            //->field('user_id, email, sex, mobile, head_pic, nickname, level, first_leader, second_leader, third_leader, reg_time')
            ->field('user_id, email, sex, mobile, head_pic, nickname, level, first_leader, second_leader, reg_time')
            ->where($where)->page($p,10)
            ->order('user_id desc')
            ->select();

        exit(json_encode(array('status'=>1,'msg'=>'获取成功','result'=>array('user_list' => $list, 'count' => $count))));
    }

    /**
     * 下线订单列表
     */
    public function order_list(){
        $status = I('status', 0);
        $p = I('p', 1, 'intval');
        $where = " user_id = {$this->user_id} and status in($status)";
        $count = M('rebate_log')->where($where)->count();
        $list = M('rebate_log')->where($where)->order('id desc')->page($p, 10)->select();
        $user_id_list = get_arr_column($list, 'buy_user_id');
        if(!empty($user_id_list))
            $userList = M('users')->where("user_id in (".  implode(',', $user_id_list).")")->getField('user_id,nickname,mobile,head_pic');

        foreach($list as $k => $value){
            foreach($userList as $user){
                if($value['buy_user_id'] == $user['user_id']){
                    $list[$k]['nickname'] = $user['nickname'];
                    $list[$k]['mobile'] = $user['mobile'];
                    $list[$k]['head_pic'] = $user['head_pic'];
                }
            }
        }

        //$distributInfo['userList'] = $userList;
        //$distributInfo['list'] = $list; // 下线订单

        $json_arr = array('status'=>1,'msg'=>'获取成功','result'=>array('order_list' => $list, 'count' => $count ));
        $json_str = json_encode($json_arr);
        exit($json_str);
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
            $distributInfo['ShareLink'] = $ShareLink;
        $json_arr = array('status'=>1,'msg'=>'获取成功','result'=>$distributInfo );
        $json_str = json_encode($json_arr);
        exit($json_str);
    }
}