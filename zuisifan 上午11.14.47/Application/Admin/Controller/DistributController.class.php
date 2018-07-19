<?php
/**
 * Author: xx.com
 * Date: 2016-03-09
 */

namespace Admin\Controller;
use Think\Page;
use Admin\Logic\GoodsLogic;

class DistributController extends BaseController {
    
    /*
     * 初始化操作
     */
    public function _initialize() {
       parent::_initialize();
    }    
    
    /**
     * 分销树状关系
     */
    public function tree(){                
        $where = 'is_distribut = 1 and first_leader = 0';
        
        if($_POST['user_id'])
            $where = "user_id = '{$_POST['user_id']}'";
        
        $list = M('users')->where($where)->select();        
        $this->assign('list',$list);
        $this->display();
    }
 
    /**
     * 分销商列表
     */
    public function distributor_list(){
    	$condition['is_distribut']  = 1;
    	$nickname = I('nickname');
    	if(!empty($nickname)){
    		$condition['nickname'] = array('like',"%$nickname%");
    	}
    	$count = M('users')->where($condition)->count();
    	$Page = new Page($count,10);
    	$show = $Page->show();
    	$user_list = M('users')->where($condition)->order('distribut_money DESC')->limit($Page->firstRow.','.$Page->listRows)->select();
    	foreach ($user_list as $k=>$val){
    		$user_list[$k]['fisrt_leader'] = M('users')->where(array('first_leader'=>$val['user_id']))->count();
//二级    		$user_list[$k]['second_leader'] = M('users')->where(array('second_leader'=>$val['user_id']))->count();
//三级    		$user_list[$k]['third_leader'] = M('users')->where(array('third_leader'=>$val['user_id']))->count();
    		$user_list[$k]['lower_sum'] = $user_list[$k]['fisrt_leader'] +$user_list[$k]['second_leader'] + $user_list[$k]['third_leader'];
    	}
    	$this->assign('page',$show);
    	$this->assign('user_list',$user_list);
    	$this->display();
    }
    
    /**
     * 分销设置
     */
    public function set(){                       
        header("Location:".U('Admin/System/index',array('inc_type'=>'distribut')));
        exit;
    }
    
    public function goods_list(){
    	$GoodsLogic = new GoodsLogic();
    	$brandList = $GoodsLogic->getSortBrands();
    	$categoryList = $GoodsLogic->getSortCategory();
    	$this->assign('categoryList',$categoryList);
    	$this->assign('brandList',$brandList);
    	$where = ' commission > 0 ';
    	$cat_id = I('cat_id');
    	if($cat_id > 0)
    	{
    		$grandson_ids = getCatGrandson($cat_id);
    		$where .= " and cat_id in(".  implode(',', $grandson_ids).") "; // 初始化搜索条件
    	}
    	$key_word = I('key_word') ? trim(I('key_word')) : '';
    	if($key_word)
    	{
    		$where = "$where and (goods_name like '%$key_word%' or goods_sn like '%$key_word%')" ;
    	}
    	I('brand_id') && $where = "$where and brand_id = ".I('brand_id') ;
    	$model = M('Goods');
    	$count = $model->where($where)->count();
    	$Page  = new Page($count,10);
    	$show = $Page->show();
    	$goodsList = $model->where($where)->order('sales_sum desc')->limit($Page->firstRow.','.$Page->listRows)->select();
    	$this->assign('goodsList',$goodsList);
    	$this->assign('page',$show);
    	$this->display();
    }
 

    
    /**
     * 分成记录
     */
    public function rebate_log()
    { 
        $model = M("rebate_log"); 
        $status = I('status');
        $user_id = I('user_id');
        $order_sn = I('order_sn');        
        $create_time = I('create_time');
        $create_time = $create_time  ? $create_time  : date('Y-m-d',strtotime('-1 year')).' - '.date('Y-m-d',strtotime('+1 day'));
                       
        $create_time2 = explode(' - ',$create_time);
        $where = " create_time >= '".strtotime($create_time2[0])."' and create_time <= '".strtotime($create_time2[1])."' ";
        
        if($status === '0' || $status > 0)
            $where .= " and status = $status ";        
        $user_id && $where .= " and user_id = $user_id ";
        $order_sn && $where .= " and order_sn like '%{$order_sn}%' ";
                        
        $count = $model->where($where)->count();
        $Page  = new Page($count,16);        
        $list = $model->where($where)->order("`id` desc")->limit($Page->firstRow.','.$Page->listRows)->select();
        
        $this->assign('create_time',$create_time);        
        $show  = $Page->show();                 
        $this->assign('show',$show);
        $this->assign('list',$list);
        C('TOKEN_ON',false);
        $this->display();    
    }
    
    /**
     * 获取某个人下级元素
     */    
    public  function ajax_lower()
    {
        $list = M('users')->where("first_leader =".$_GET[id])->select();
        $this->assign('list',$list);
        $this->display();                
    }
    
    /**
     * 修改编辑 分成 
     */
    public  function editRebate(){        
        $id = I('id');
        $model = M("rebate_log");
        $rebate_log = $model->find($id);
        if(IS_POST)
        {
                $model->create();
                
                // 如果是确定分成 将金额打入分佣用户余额
                if($model->status == 3 && $rebate_log['status'] != 3)
                {
                    $account_log_info = accountLog($model->user_id, $rebate_log['money'], 0,"订单:{$rebate_log['order_sn']}分佣",$rebate_log['money']);
                    if(!$account_log_info['is_success']){
                        $this->error($account_log_info['message']);
                        exit;
                    }
                }                
                $model->save();                               
                $this->success("操作成功!!!",U('Admin/Distribut/rebate_log'));               
                exit;
        }                      
       
       $user = M('users')->where("user_id = {$rebate_log[user_id]}")->find();       
            
       if($user['nickname'])        
           $rebate_log['user_name'] = $user['nickname'];
       elseif($user['email'])        
           $rebate_log['user_name'] = $user['email'];
       elseif($user['mobile'])        
           $rebate_log['user_name'] = $user['mobile'];            
       
       $this->assign('user',$user);
       $this->assign('rebate_log',$rebate_log);
       $this->display();           
    }        
            

}