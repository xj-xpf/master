<?php
/**
 * Author: xx.com
 */ 
namespace Api\Controller;
use Think\Controller;
use Api\Logic\GoodsLogic;
use Think\Page;
class PaymentController extends Controller {
    public $payment; //  具体的支付类
    public $alipay_config = array();// 支付宝支付配置参数
    /**
     * 析构流函数
     */
    public function  __construct() {   
        parent::__construct();
        require_once("plugins/payment/alipayMobile/lib/alipay_submit.class.php");
        require_once("plugins/payment/alipayMobile/lib/alipay_function.php");
        $paymentPlugin = M('Plugin')->where("code='alipayMobile' and  type = 'payment' ")->find(); // 找到支付插件的配置
        $config_value = unserialize($paymentPlugin['config_value']); // 配置反序列化
        $this->alipay_config['alipay_pay_method']= $config_value['alipay_pay_method']; // 1 使用担保交易接口  2 使用即时到帐交易接口s
        $this->alipay_config['partner']       = $config_value['alipay_partner'];//合作身份者id，以2088开头的16位纯数字
        $this->alipay_config['seller_email']  = $config_value['alipay_account'];//收款支付宝账号，一般情况下收款账号就是签约账号
        $this->alipay_config['key']	      = $config_value['alipay_key'];//安全检验码，以数字和字母组成的32位字符
        $this->alipay_config['sign_type']     = strtoupper('RSA');//签名方式 不需修改
        $this->alipay_config['input_charset'] = strtolower('utf-8');//字符编码格式 目前支持 gbk 或 utf-8
        $this->alipay_config['cacert']        = getcwd().'\\cacert.pem'; //ca证书路径地址，用于curl中ssl校验 //请保证cacert.pem文件在当前文件夹目录中
        $this->alipay_config['transport']     = 'http';//访问模式,根据自己的服务器是否支持ssl访问，若支持请选择https；若不支持请选择http
        // 导入具体的支付类文件
    }

    public function doPay(){

        $order_sn = I('order_sn',0);
        $order = M('order')->where(array('order_sn'=>$order_sn))->find();
        if(!$order){
            $res = array('msg'=>'该订单不存在','status'=>-1);
            $this->ajaxReturn($res);
        }
        if($order['pay_status'] == 1){
            $this->error('此订单，已完成支付!');
        }

        $parameter = array(
            "partner" => trim($this->alipay_config['partner']), //合作身份者ID，签约账号，以2088开头由16位纯数字组成的字符串，查看地址：https://b.alipay.com/order/pidAndKey.htm
            'seller_id'=> '18957927725', //收款支付宝账号，以2088开头由16位纯数字组成的字符串，一般情况下收款账号就是签约账号
            "out_trade_no"	=> $order['order_sn'], //商户订单号
            "subject"       =>"醉思凡商城订单", //订单名称，必填
            'body' => '醉思凡商城',//商品详情
            "total_fee"	=> $order['order_amount'], //付款金额
           // "notify_url"	=> SITE_URL.U('Payment/alipayNotify') , //服务器异步通知页面路径 //必填，不能修改
            "notify_url"	=> 'http://www.zuisifan.com/index.php/Api/Payment/alipayNotify' , //服务器异步通知页面路径 //必填，不能修改
            "service" => 'mobile.securitypay.pay',   // // 产品类型，无需修改
            "payment_type"  => "1", // 支付类型 ，无需修改
            '_input_charset' => 'utf-8',
            'it_b_pay' => "30m",
            "show_url"	=> "http://www.zuisifan.com", //收银台页面上，商品展示的超链接，必填
            'app_id' => '2017021605704778',
            'sign_type' => 'RSA',
        );

        $str = '';

        foreach ($parameter as $key => $val) {
            if ($key == 'sign_type' || $key == 'sign') {
                continue;
            } else {
                if ($str == '') {
                    $str = $key.'='.'"'.$val.'"';
                } else {
                    $str = $str . '&' . $key . '=' . '"'. $val .'"';
                }
            }
        }
        $sign = sign($str);
        $EncodeStr=urlencode($sign);//将加密后的字符串进行处理
        $str = $str.'&sign='.'"'.$EncodeStr.'"'.'&sign_type='.'"'.$parameter['sign_type'].'"';
        $res = array('msg'=>'获取成功','status'=>1,'result'=>$str);
        $this->ajaxReturn($res);
    }

    //充值
    public function doRechangePay($order_id){
        $order = M('recharge')->where(array('order_id'=>$order_id))->find();
        if(!$order){
            $res = array('msg'=>'该充值订单不存在','status'=>-1);
            $this->ajaxReturn($res);
        }
        if($order['pay_status'] == 1){
            $this->error('此充值订单，已完成支付!');
        }
        if($order['pay_status'] == 2){
            $this->error('此充值订单，已关闭!');
        }

        $parameter = array(
            "partner" => trim($this->alipay_config['partner']), //合作身份者ID，签约账号，以2088开头由16位纯数字组成的字符串，查看地址：https://b.alipay.com/order/pidAndKey.htm
            'seller_id'=> '18957927725', //收款支付宝账号，以2088开头由16位纯数字组成的字符串，一般情况下收款账号就是签约账号
            "out_trade_no"  => $order['order_sn'], //商户订单号
            "subject"       =>"醉思凡商城订单", //订单名称，必填
            'body' => '醉思凡商城订单',//商品详情
            "total_fee" => $order['account'], //付款金额
           // "notify_url"  => SITE_URL.U('Payment/alipayNotify') , //服务器异步通知页面路径 //必填，不能修改
            "notify_url"    => 'http://www.zuisifan.com/index.php/Api/Payment/alipayNotify' , //服务器异步通知页面路径 //必填，不能修改
            "service" => 'mobile.securitypay.pay',   // // 产品类型，无需修改
            "payment_type"  => "1", // 支付类型 ，无需修改
            '_input_charset' => 'utf-8',
            'it_b_pay' => "30m",
            "show_url"  => "http://www.zuisifan.com", //收银台页面上，商品展示的超链接，必填
            'app_id' => '2017021605704778',
            'sign_type' => 'RSA',
        );

        $str = '';

        foreach ($parameter as $key => $val) {
            if ($key == 'sign_type' || $key == 'sign') {
                continue;
            } else {
                if ($str == '') {
                    $str = $key.'='.'"'.$val.'"';
                } else {
                    $str = $str . '&' . $key . '=' . '"'. $val .'"';
                }
            }
        }
        $sign = sign($str);
        $EncodeStr=urlencode($sign);//将加密后的字符串进行处理
        $str = $str.'&sign='.'"'.$EncodeStr.'"'.'&sign_type='.'"'.$parameter['sign_type'].'"';
        $res = array('msg'=>'获取成功','status'=>1,'result'=>$str);
        $this->ajaxReturn($res);
    }

    /**
     * app端发起支付宝,支付宝返回服务器端,  返回到这里
     * http://www.xx.com/index.php/Api/Payment/alipayNotify
     */
    public function alipayNotify(){
        //error_log(print_r($_POST,1),3,__FILE__.'.log');
        //error_log(print_r($_GET,1),3,__FILE__.'2.log');
        require_once("plugins/payment/alipayMobile/lib/alipay_notify.class.php");
        //计算得出通知验证结果
        $alipayNotify = new \AlipayNotify($this->alipay_config);

        $verify_result = $alipayNotify->verifyNotify();

        //error_log(print_r($verify_result,1),3,__FILE__.'3.log');
        //验证成功
        if($verify_result) 
        {                           
                $order_sn = $out_trade_no = trim($_POST['out_trade_no']); //商户订单号
                $trade_no = $_POST['trade_no'];//支付宝交易号
                $trade_status = $_POST['trade_status'];//交易状态

            if($_POST['trade_status'] == 'TRADE_FINISHED') {
                update_pay_status($order_sn); // 修改订单支付状态                
            } else if ($_POST['trade_status'] == 'TRADE_SUCCESS') {
                update_pay_status($order_sn); // 修改订单支付状态                
            }               
            M('order')->where("order_sn = '$order_sn'")->save(array('pay_code'=>'alipayMobile','pay_name'=>'app支付宝'));
            echo "success"; //  告诉支付宝支付成功 请不要修改或删除               
        }
        else 
        {                
            echo "fail"; //验证失败         
        }
    }


 
}
