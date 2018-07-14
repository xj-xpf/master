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
class UpgradeLogicController extends Controller {

    /**
     * 析构函数
     */
    function __construct() {
        parent::__construct();
 
   }    
   /**
    * 一键升级
    */
   function OneKeyUpgrade(){
        $upgradeLogic = new UpgradeLogic();
        $msg = $upgradeLogic->OneKeyUpgrade(); //升级包消息
        exit($msg);
   }
    
 
}