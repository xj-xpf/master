<?php
/**
 * Author: xx.com
 */
namespace Api\Controller;

use Think\Controller;

class WxpayController extends BaseController
{
    /*public function _initialize()
    {
        $wxPay = M('plugin')->where(array('type'=>'payment','code'=>'appWeixinPay'))->find();
        if(!$wxPay){
            $res = array('msg'=>'没有配置微信支付插件','status'=>-1);
            $this->ajaxReturn($res);
        }
        $wxPayVal = unserialize($wxPay['config_value']);
        if(!$wxPayVal['appid'] || !$wxPayVal['key'] || !$$wxPayVal['mchid']){
            $res = array('msg'=>'没有配置微信支付插件参数','status'=>-1);
            $this->ajaxReturn($res);
        }
        require_once("plugins/payment/weixin/app_notify/Wxpay/WxPayApi.class.php");
        require_once("plugins/payment/weixin/app_notify/Wxpay/WxPayUnifiedOrder.class.php");
    }*/


    /**
     * 支付通知
     */
    /*public function  notify()
    {
        $xml = $GLOBALS['HTTP_RAW_POST_DATA'];
        $result = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);
        if($result['return_code'] == 'SUCCESS'){
            $order_sn = substr($result['out_trade_no'],0,18);
            update_pay_status($order_sn);
        }

        $test = array('return_code'=>'SUCCESS','return_msg'=>'OK');
        header('Content-Type:text/xml; charset=utf-8');
        exit(arrayToXml($test));
    }*/

    /**
     * 统一下单
     */
    /*public function dopay()
    {
        header('Access-Control-Allow-Origin: *');
        header('Content-type: text/plain');
        require_once("plugins/payment/weixin/app_notify/Wxpay/WxPayConfig.class.php");
        require_once("plugins/payment/weixin/app_notify/Wxpay/WxPayNotify.class.php");
        require_once("plugins/payment/weixin/app_notify/Wxpay/WxPayReport.class.php");
        require_once("plugins/payment/weixin/app_notify/Wxpay/WxPayResults.class.php");

        $order_sn = I('order_sn',0);
        $order = M('order')->where(array('order_sn'=>$order_sn))->find();
        if(!$order){
            $res = array('msg'=>'该订单不存在','status'=>-1);
            $this->ajaxReturn($res);
        }
        // 获取支付金额
        $amount = $order['order_amount'];
        $total = floatval($amount);
        $total = round($total * 100); // 将元转成分
        if (empty($total)) {
            $total = 100;
        }
        // 商品名称
        $shop_info = tpCache('shop_info');
        $subject = $shop_info['store_name'];
        $detail = "订单金额";
        $native = "NATIVE";
        // 订单号，示例代码使用时间值作为唯一的订单ID号
        $unifiedOrder = new \WxPayUnifiedOrder();
        $WxPayApi = new \WxPayApi();
        $unifiedOrder->SetBody($subject);//商品或支付单简要描述

        $WxPayConfig = \WxPayConfig::getInstance();
        $unifiedOrder->SetAppid($WxPayConfig::$APPID);//appid
        $unifiedOrder->SetMch_id($WxPayConfig::$MCHID);//商户标识
        $unifiedOrder->SetNonce_str($WxPayApi::getNonceStr($length = 32));//随机字符串
        $unifiedOrder->SetDetail($detail);//详情
        $unifiedOrder->SetOut_trade_no($order_sn.time());//交易号
        $unifiedOrder->SetTotal_fee($total);//交易金额
        $unifiedOrder->SetTrade_type("APP");//应用类型
        $unifiedOrder->SetSpbill_create_ip($_SERVER['REMOTE_ADDR']);//发起充值的ip
        $unifiedOrder->SetNotify_url($WxPayConfig::$NOTIFY_URL);//交易成功通知url
        //$unifiedOrder->SetTrade_type($native);//支付类型
        $unifiedOrder->SetProduct_id(time());

        $result = $WxPayApi::unifiedOrder($unifiedOrder);

        if (is_array($result)) {
            $res = array('msg'=>'获取成功','status'=>1,'result'=>$result);
        }else{
            $res = array('msg'=>'获取失败','status'=>-1,'result'=>$result);
        }
        $this->ajaxReturn($res);
    }*/
    /*
     * 配置参数
     */
    public function __construct() {
        $this->config = C ( 'ZSF_WX_PAY' ); // 引入配置
    }
    
    // 获取预支付订单
    public function pay() {
        $order_sn = I('order_sn', '');
        $order = M('order')->where(array('order_sn'=>$order_sn))->find();
        if(!$order){
            $res = array('msg'=>'该订单不存在','status'=>-1);
            $this->ajaxReturn($res);
        }
        if($order['pay_status'] == 1){
            $this->error('此订单，已完成支付!');
        }
        // 获取支付金额
        $amount = $order['order_amount'];
        $total = floatval($amount);
        $paymoney = round($total * 100); // 将元转成分
        // 验证金额
        if ($paymoney <= 0) {
            $res = array('msg'=>'此订单金额无效！','status'=>-1);
            $this->ajaxReturn($res);
        }
        
        $body = $this->config['app_name'].'-' . '订单支付';
        $out_trade_no = $order_sn;
        $total_fee = $paymoney; // 微信支付 单位为分
        $url = $this->config ['pay_api']; // "https://api.mch.weixin.qq.com/pay/unifiedorder";
        
        $notify_url = $this->config ["notify_url"];
        
        $onoce_str = $this->getRandChar ( 32 );
        
        $data ["appid"] = $this->config ["app_id"];
        $data ["body"] = $body;
        $data ["mch_id"] = $this->config ['mch_id'];
        $data ["nonce_str"] = $onoce_str;
        $data ["notify_url"] = $notify_url;
        $data ["out_trade_no"] = $out_trade_no;
        $data ["spbill_create_ip"] = $this->get_client_ip ();
        $data ["total_fee"] = $total_fee;
        $data ["trade_type"] = "APP";
        $s = $this->getSign ( $data );
        $data ["sign"] = $s;
        $xml = $this->arrayToXml ( $data );
        $response = $this->postXmlCurl ( $xml, $url );
        $re = $this->xmlstr_to_array ( $response );
        if ($re ['return_code'] == 'FAIL') {
            $res = array('msg'=>'获取失败','status'=>-1,'result'=>$re['return_msg']);
            $this->ajaxReturn($res);
        }
        
        // 二次签名
        $reapp = $this->getOrder ( $re ['prepay_id'] );
        // 将微信返回的结果xml转成数组
        $res = array('msg'=>'返回签名','status'=>1,'result'=>$reapp);
        $this->ajaxReturn($res);
         
    }

    // 获取预支付订单
    public function rechangePay($order_id) {
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
        // 获取支付金额
        $amount = $order['account'];
        $total = floatval($amount);
        $paymoney = round($total * 100); // 将元转成分
        // 验证金额
        if ($paymoney <= 0) {
            $res = array('msg'=>'此充值订单金额无效！','status'=>-1);
            $this->ajaxReturn($res);
        }
        
        $body = $this->config['app_name'].'-' . '充值订单支付';
        $out_trade_no = $order['order_sn'];
        $total_fee = $paymoney; // 微信支付 单位为分
        $url = $this->config ['pay_api']; // "https://api.mch.weixin.qq.com/pay/unifiedorder";
        
        $notify_url = $this->config ["notify_url"];
        
        $onoce_str = $this->getRandChar ( 32 );
        
        $data ["appid"] = $this->config ["app_id"];
        $data ["body"] = $body;
        $data ["mch_id"] = $this->config ['mch_id'];
        $data ["nonce_str"] = $onoce_str;
        $data ["notify_url"] = $notify_url;
        $data ["out_trade_no"] = $out_trade_no;
        $data ["spbill_create_ip"] = $this->get_client_ip ();
        $data ["total_fee"] = $total_fee;
        $data ["trade_type"] = "APP";
        $s = $this->getSign ( $data );
        $data ["sign"] = $s;
        $xml = $this->arrayToXml ( $data );
        $response = $this->postXmlCurl ( $xml, $url );
        $re = $this->xmlstr_to_array ( $response );
        if ($re ['return_code'] == 'FAIL') {
            $res = array('msg'=>'获取失败','status'=>-1,'result'=>$re['return_msg']);
            $this->ajaxReturn($res);
        }
        
        // 二次签名
        $reapp = $this->getOrder ( $re ['prepay_id'] );
        // 将微信返回的结果xml转成数组
        $res = array('msg'=>'返回签名','status'=>1,'result'=>$reapp);
        $this->ajaxReturn($res);
         
    }
    
    
    /**
     * 回调地址
     */
    public function notify(){
        /*  $config = array(
                'mch_id' => $this->config['mch_id'],
                'appid' => $this->config['app_id'],
                'key' => $this->config['key'],
        ); */
        $postStr = $GLOBALS["HTTP_RAW_POST_DATA"];
        error_log($postStr, 3, './str.txt');
        /*
         $postStr = '<xml>
         <appid><![CDATA[wx00e5904efec77699]]></appid>
         <attach><![CDATA[支付测试]]></attach>
         <bank_type><![CDATA[CMB_CREDIT]]></bank_type>
         <cash_fee><![CDATA[1]]></cash_fee>
         <fee_type><![CDATA[CNY]]></fee_type>
         <is_subscribe><![CDATA[Y]]></is_subscribe>
         <mch_id><![CDATA[1220647301]]></mch_id>
         <nonce_str><![CDATA[a0tZ41phiHm8zfmO]]></nonce_str>
         <openid><![CDATA[oU3OCt5O46PumN7IE87WcoYZY9r0]]></openid>
         <out_trade_no><![CDATA[550bf2990c51f]]></out_trade_no>
         <result_code><![CDATA[SUCCESS]]></result_code>
         <return_code><![CDATA[SUCCESS]]></return_code>
         <sign><![CDATA[F6F519B4DD8DB978040F8C866C1E6250]]></sign>
         <time_end><![CDATA[20150320181606]]></time_end>
         <total_fee>1</total_fee>
         <trade_type><![CDATA[JSAPI]]></trade_type>
         <transaction_id><![CDATA[1008840847201503200034663980]]></transaction_id>
         </xml>';
        */
        $postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
        if ($postObj === false) {
            error_log('parse xml error', 3, './wechat_errorlog.txt');
        }
        if ($postObj->return_code != 'SUCCESS') {
            error_log($postObj->return_msg, 3, './wechat_errorlog.txt');
        }
        if ($postObj->result_code != 'SUCCESS') {
            error_log($postObj->err_code, 3, './wechat_errorlog.txt');
        }
        $arr = (array)$postObj;
        unset($arr['sign']);
        
        
        if ($this->getSign($arr) == $postObj->sign) { 
            if ($arr['return_code']=='SUCCESS' && $arr['result_code']=='SUCCESS') {
                if($_POST['trade_status'] == 'TRADE_FINISHED') {
                    update_pay_status($order_sn); // 修改订单支付状态                
                } else if ($_POST['trade_status'] == 'TRADE_SUCCESS') {
                    update_pay_status($order_sn); // 修改订单支付状态                
                }               
                M('order')->where("order_sn = '$order_sn'")->save(array('pay_code'=>'weixin','pay_name'=>'微信支付'));
                $r_arr['return_code']='SUCCESS';
                $r_arr['return_msg']='回调成功';
                //返回给微信
                echo $this->arrayToXml($r_arr);
            } 
            // $mch_id = $postObj->mch_id; //微信支付分配的商户号
            // $appid = $postObj->appid; //微信分配的公众账号ID
            // $openid = $postObj->openid; //用户在商户appid下的唯一标识
            // $transaction_id = $postObj->transaction_id;//微信支付订单号
            // $out_trade_no = $postObj->out_trade_no;//商户订单号
            // $total_fee = $postObj->total_fee; //订单总金额，单位为分
            // $is_subscribe = $postObj->is_subscribe; //用户是否关注公众账号，Y-关注，N-未关注，仅在公众账号类型支付有效
            // $attach = $postObj->attach;//商家数据包，原样返回
            // $time_end = $postObj->time_end;//支付完成时间
            //echo '<xml><return_code><![CDATA[SUCCESS]]></return_code><return_msg><![CDATA[OK]]></return_msg></xml>';
        }
        $r_arr['return_code']='FAIL';
        $r_arr['return_msg']='回调失败';
        //返回给微信
        echo $this->arrayToXml($r_arr);
        
        
    }
    
    // 执行第二次签名，才能返回给客户端使用
    public function getOrder($prepayId) {
        $data ["appid"] = $this->config ["app_id"];
        $data ["noncestr"] = $this->getRandChar ( 32 );
        ;
        $data ["package"] = "Sign=WXPay";
        $data ["partnerid"] = $this->config ['mch_id'];
        $data ["prepayid"] = $prepayId;
        $data ["timestamp"] = time ();
        $s = $this->getSign ( $data );
        $data ["sign"] = $s;
        
        return $data;
    }
    
    /*
     * 生成签名
     */
    function getSign($Obj) {
        foreach ( $Obj as $k => $v ) {
            $Parameters [strtolower ( $k )] = $v;
        }
        // 签名步骤一：按字典序排序参数
        ksort ( $Parameters );
        $String = $this->formatBizQueryParaMap ( $Parameters, false );
        // echo "【string】 =".$String."</br>";
        // 签名步骤二：在string后加入KEY
        $String = $String . "&key=" . $this->config ['key'];
        // echo "<textarea style='width: 50%; height: 150px;'>$String</textarea> <br />";
        // 签名步骤三：MD5加密
        $result_ = strtoupper ( md5 ( $String ) );
        return $result_;
    }
    
    // 获取指定长度的随机字符串
    function getRandChar($length) {
        $str = null;
        $strPol = "ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789abcdefghijklmnopqrstuvwxyz";
        $max = strlen ( $strPol ) - 1;
        
        for($i = 0; $i < $length; $i ++) {
            $str .= $strPol [rand ( 0, $max )]; // rand($min,$max)生成介于min和max两个数之间的一个随机整数
        }
        
        return $str;
    }
    
    // 数组转xml
    function arrayToXml($arr) {
        $xml = "<xml>";
        foreach ( $arr as $key => $val ) {
            if (is_numeric ( $val )) {
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
            } else
                $xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
        }
        $xml .= "</xml>";
        return $xml;
    }
    
    // post https请求，CURLOPT_POSTFIELDS xml格式
    function postXmlCurl($xml, $url, $second = 30) {
        // 初始化curl
        $ch = curl_init ();
        // 超时时间
        curl_setopt ( $ch, CURLOPT_TIMEOUT, $second );
        // 这里设置代理，如果有的话
        // curl_setopt($ch,CURLOPT_PROXY, '8.8.8.8');
        // curl_setopt($ch,CURLOPT_PROXYPORT, 8080);
        curl_setopt ( $ch, CURLOPT_URL, $url );
        curl_setopt ( $ch, CURLOPT_SSL_VERIFYPEER, FALSE );
        curl_setopt ( $ch, CURLOPT_SSL_VERIFYHOST, FALSE );
        // 设置header
        curl_setopt ( $ch, CURLOPT_HEADER, FALSE );
        // 要求结果为字符串且输出到屏幕上
        curl_setopt ( $ch, CURLOPT_RETURNTRANSFER, TRUE );
        // post提交方式
        curl_setopt ( $ch, CURLOPT_POST, TRUE );
        curl_setopt ( $ch, CURLOPT_POSTFIELDS, $xml );
        // 运行curl
        $data = curl_exec ( $ch );
        // 返回结果
        if ($data) {
            curl_close ( $ch );
            return $data;
        } else {
            $error = curl_errno ( $ch );
            echo "curl出错，错误码:$error" . "<br>";
            echo "<a href='http://curl.haxx.se/libcurl/c/libcurl-errors.html'>错误原因查询</a></br>";
            curl_close ( $ch );
            return false;
        }
    }
    
    /*
     * 获取当前服务器的IP
     */
    function get_client_ip() {
        if ($_SERVER ['REMOTE_ADDR']) {
            $cip = $_SERVER ['REMOTE_ADDR'];
        } elseif (getenv ( "REMOTE_ADDR" )) {
            $cip = getenv ( "REMOTE_ADDR" );
        } elseif (getenv ( "HTTP_CLIENT_IP" )) {
            $cip = getenv ( "HTTP_CLIENT_IP" );
        } else {
            $cip = "unknown";
        }
        return $cip;
    }
    
    // 将数组转成uri字符串
    function formatBizQueryParaMap($paraMap, $urlencode) {
        $buff = "";
        ksort ( $paraMap );
        foreach ( $paraMap as $k => $v ) {
            if ($urlencode) {
                $v = urlencode ( $v );
            }
            $buff .= strtolower ( $k ) . "=" . $v . "&";
        }
        $reqPar;
        if (strlen ( $buff ) > 0) {
            $reqPar = substr ( $buff, 0, strlen ( $buff ) - 1 );
        }
        return $reqPar;
    }
    
    /**
     * xml转成数组
     */
    function xmlstr_to_array($xmlstr) {
        $doc = new \DOMDocument ();
        $doc->loadXML ( $xmlstr );
        return $this->domnode_to_array ( $doc->documentElement );
    }
    function domnode_to_array($node) {
        $output = array ();
        switch ($node->nodeType) {
            case XML_CDATA_SECTION_NODE :
            case XML_TEXT_NODE :
                $output = trim ( $node->textContent );
                break;
            case XML_ELEMENT_NODE :
                for($i = 0, $m = $node->childNodes->length; $i < $m; $i ++) {
                    $child = $node->childNodes->item ( $i );
                    $v = $this->domnode_to_array ( $child );
                    if (isset ( $child->tagName )) {
                        $t = $child->tagName;
                        if (! isset ( $output [$t] )) {
                            $output [$t] = array ();
                        }
                        $output [$t] [] = $v;
                    } elseif ($v) {
                        $output = ( string ) $v;
                    }
                }
                if (is_array ( $output )) {
                    if ($node->attributes->length) {
                        $a = array ();
                        foreach ( $node->attributes as $attrName => $attrNode ) {
                            $a [$attrName] = ( string ) $attrNode->value;
                        }
                        $output ['@attributes'] = $a;
                    }
                    foreach ( $output as $t => $v ) {
                        if (is_array ( $v ) && count ( $v ) == 1 && $t != '@attributes') {
                            $output [$t] = $v [0];
                        }
                    }
                }
                break;
        }
        return $output;
    }


}

?>