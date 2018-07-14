<?php
/**
 * Author: xx.com
 * Date: 2016-03-19
 */

namespace Home\Logic;

use Think\Model\RelationModel;
/**
 *
 * Class orderLogic
 * @package Home\Logic
 */
class OrderLogic extends RelationModel
{
    //
    public function check_order_outtime(){

        //获取24小时前时间戳
        $mark_time = time() - 24 * 60 * 60;

        //查询超过24小时未支付订单，并修改状态；
        $where['add_time'] = array('lt',$mark_time);
        $where['pay_status'] = 0;
        $where['order_status'] = array('neq', 5);
        $order_list = M('order')->field('order_id')->where($where)->select();

        $action_note = '逾期未支付，订单作废';
        $status_desc = '订单作废';

        $data['order_status'] = 5;
        $data['admin_note'] = $action_note;
        foreach($order_list as $key => $value){
            M('order')->where(array('order_id'=>$value['order_id']))->save($data);
            logOrder($value['order_id'],$action_note,$status_desc,0);
        }
    }
}