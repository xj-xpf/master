<?php
/**
 * Author: Author: xx.com
 * Date: 2015-09-09
 * 公共逻辑类  将放到Application\Common\Logic\   由于很多模块公用 将不在放到某个单独模下面
 */

namespace Common\Logic;

use Think\Model;
//use Think\Page;

/**
 * 分销逻辑层
 * Class CatsLogic
 * @package Home\Logic
 */
class DistributLogic //extends Model
{
     public function hello(){
        echo 'function hello(){'; 
     }
     
     /**
      * 生成分销记录
      */
     public function rebate_log($order)
     {       
         $user = M('users')->where("user_id = {$order['user_id']}")->find();
                           
         $pattern = tpCache('distribut.pattern'); // 分销模式  
         $first_rate = tpCache('distribut.first_rate'); // 一级比例
         $second_rate = tpCache('distribut.second_rate'); // 二级比例  

         
         //按照商品分成 每件商品的佣金拿出来
         if($pattern  == 0) 
         {
            // 获取所有商品分类 
             $cat_list =  M('goods_category')->getField('id,parent_id,commission_rate');             
             $order_goods = M('order_goods')->where("order_id = {$order['order_id']}")->select(); // 订单所有商品
             $commission = 0;
             foreach($order_goods as $k => $v)
             {
                 $tmp_commission = 0;
                 $goods = M('goods')->where("goods_id = {$v['goods_id']}")->find(); // 单个商品的佣金
                 $tmp_commission = $goods['commission'];
                 // 如果商品没有设置分佣,则找他所属分类看是否设置分佣
                                        
                 $tmp_commission = $tmp_commission  * $v['goods_num']; // 单个商品的分佣乘以购买数量
                 $commission += $tmp_commission; // 所有商品的累积佣金
             }                        
         }else{
             $order_rate = tpCache('distribut.order_rate'); // 订单分成比例  
             $commission = ($order['order_amount'] + $order['user_money'] + $order['integral_money']) * ($order_rate / 100); // 订单的商品总额 乘以 订单分成比例  支付金额 = 实际金额支付 + 余额支付
         }
                  
         // 如果这笔订单没有分销金额
         if($commission == 0)
             return false;

            $first_money = $commission * ($first_rate / 100); // 一级赚到的钱
            $second_money = $commission * ($second_rate / 100); // 二级赚到的钱

            /*//  微信消息推送
            $wx_user = M('wx_user')->find();
            $jssdk = new \Mobile\Logic\Jssdk($wx_user['appid'],$wx_user['appsecret']);*/
                  
          // 一级 分销商赚 的钱. 小于一分钱的 不存储
         if($user['first_leader'] > 0 && $first_money > 0.01)
         {
            $data = array(             
                'user_id' =>$user['first_leader'],
                'buy_user_id'=>$user['user_id'],
                'nickname'=>$user['nickname'],
                'order_sn' => $order['order_sn'],
                'order_id' => $order['order_id'],
                'goods_price' => $order['order_amount'] + $order['user_money'] + $order['integral_money'],     //实际支付总金额 = 实际金额支付 + 余额支付 + 积分支付
                'money' => $first_money,
                'level' => 1,
                'create_time' => time(),             
            );                  
            M('rebate_log')->add($data);
         }
          // 二级 分销商赚 的钱.
         if($user['second_leader'] > 0 && $second_money > 0.01)
         {         
            $data = array(
                'user_id' =>$user['second_leader'],
                'buy_user_id'=>$user['user_id'],
                'nickname'=>$user['nickname'],
                'order_sn' => $order['order_sn'],
                'order_id' => $order['order_id'],
                'goods_price' => $order['order_amount'] + $order['user_money'] + $order['integral_money'],     //实际支付总金额 = 实际金额支付 + 余额支付 + 积分支付
                'money' => $second_money,
                'level' => 2,
                'create_time' => time(),             
            );                  
            M('rebate_log')->add($data);
         }

         M('order')->where("order_id = {$order['order_id']}")->save(array("is_distribut"=>1));  //修改订单为已经分成
     }

     /**
      * 自动分成 符合条件的 分成记录
      */
     function auto_confirm(){
         
         $switch = tpCache('distribut.switch');
         if($switch == 0)
             return false;
         
         $today_time = time();
         $distribut_date = tpCache('distribut.date');
         //$distribut_time = $distribut_date * (60 * 60 * 24); // 计算天数 时间戳
         $distribut_time = $distribut_date * (30 * 60); // 计算天数 时间戳
         $rebate_log_arr = M('rebate_log')->where("status = 2 and ($today_time - confirm) >  $distribut_time")->select();
         foreach ($rebate_log_arr as $key => $val)
         {
             accountLog($val['user_id'], $val['money'], 0,"订单:{$val['order_sn']}分佣",$val['money']);             
             $val['status'] = 3;
             $val['confirm_time'] = $today_time;
             $val['remark'] = $val['remark']."满{$distribut_date}天,程序自动分成.";
             M("rebate_log")->where("id = {$val[id]}")->save($val);
         }
     }
}