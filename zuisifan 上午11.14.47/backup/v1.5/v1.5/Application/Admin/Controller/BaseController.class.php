<?php

/**
 * lnshop
 * ============================================================================
 * 版权所有 2015-2027 山东XX网络科技有限公司，并保留所有权利。
 * 网站地址: http://www.xx.com
 * ----------------------------------------------------------------------------
 * 这不是一个自由软件！您只能在不用于商业目的的前提下对程序代码进行修改和使用 .
 * 不允许对程序代码以任何形式任何目的的再发布。
 * ============================================================================
 * Author: 当燃
 * Date: 2015-09-09
 */

namespace Admin\Controller;
use Think\Controller;
use Admin\Model;
use Admin\Logic\UpgradeLogic;
class BaseController extends Controller {

    /**
     * 析构函数
     */
    function __construct() {
        parent::__construct();
        $upgradeLogic = new UpgradeLogic();
        $upgradeMsg = $upgradeLogic->checkVersion(); //升级包消息                
        $this->assign('upgradeMsg',$upgradeMsg);        
   }    
    
    /*
     * 初始化操作
     */
    public function _initialize() {
     
        $this->assign('action',ACTION_NAME);
        //过滤不需要登陆的行为
        if(in_array(ACTION_NAME,array('login','logout','vertify')))
            return;
        if(session('admin_id') > 0){
            return;//已经登陆
        }else{
            //$this->error('请先登陆',U('Admin/Admin/login'));
        }                
    }
}