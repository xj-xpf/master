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
 * Author: 宇宙人     
 * Date: 2015-09-09
 */
namespace Admin\Controller;
use Admin\Logic\GoodsLogic;
use Admin\Model;
use Think\AjaxPage;
use Think\Page;

class GoodsController extends BaseController {
    
    /**
     *  商品分类列表
     */
    public function categoryList(){                
                
        $GoodsLogic = new GoodsLogic();               
        $cat_list = $GoodsLogic->goods_cat_list();        
        //print_r($cat_list);
        $this->assign('cat_list',$cat_list);        
        $this->display();        
    }
    
    /**
     * 添加修改商品分类
     */
    public function addEditCategory(){
        
            $GoodsLogic = new GoodsLogic();        
            if(IS_GET)
            {
                $goods_category_info = D('GoodsCategory')->where('id='.I('GET.id',0))->find();            
                $cat_list = $GoodsLogic->goods_cat_list();      

                if($_GET['id'] > 0)// 如果是编辑分类, 不能选择 上级分类为自己            
                    $cat_list =  $GoodsLogic->remove_cat($cat_list,$cat_list[$_GET['id']]['parent_id_path']);                         
                $this->assign('cat_list',$cat_list);            
                $this->assign('goods_category_info',$goods_category_info);      
                $this->display('_category');     
                exit;
            }

            $GoodsCategory = D('GoodsCategory'); //

            $type = $_POST['id'] > 0 ? 2 : 1; // 标识自动验证时的 场景 1 表示插入 2 表示更新                        
            //ajax提交验证
            if($_GET['is_ajax'] == 1)
            {
                C('TOKEN_ON',false);
                if(!$GoodsCategory->create(NULL,$type))// 根据表单提交的POST数据创建数据对象                 
                {
                    //  编辑
                    $return_arr = array(
                        'status' => -1,
                        'msg'   => '',
                        'data'  => $GoodsCategory->getError(),
                    );
                    $this->ajaxReturn(json_encode($return_arr));
                }else {
                    //  form表单提交
                    C('TOKEN_ON',true);

                    if ($type == 2)
                    {
                        $GoodsCategory->save(); // 写入数据到数据库
                        $GoodsLogic->refresh_cat($_POST['id']);
                    }
                    else
                    {
                        $insert_id = $GoodsCategory->add(); // 写入数据到数据库
                        $GoodsLogic->refresh_cat($insert_id);
                    }
                    $return_arr = array(
                        'status' => 1,
                        'msg'   => '操作成功',
                        'data'  => array('url'=>U('Admin/Goods/categoryList')),
                    );
                    $this->ajaxReturn(json_encode($return_arr));

                }  
            }

    }
    
    
    /**
     * 删除分类
     */
    public function delGoodsCategory(){
        
        $GoodsCategory = M("GoodsCategory");                
        $count = $GoodsCategory->where("parent_id=".I('GET.id'))->count("id");   
        $count > 0 && $this->error('该分类下还有分类不得删除!',U('Admin/Goods/categoryList'));
        
        $GoodsCategory->where("id=".I('GET.id'))->delete();   
        $this->success("操作成功!!!",U('Admin/Goods/categoryList'));
    }
    
    
    /**
     *  商品列表
     */
    public function goodsList(){     
        
        //$a = get_defined_constants(true);
        //$a2 = print_r($a['user'],true);
        //file_put_contents('a.html', $a2);     
        //\Admin\Logic\UpgradeLogic::checkVersion();                 
        //\Admin\Logic\UpgradeLogic::OneKeyUpgrade();                
        $brandList =  M("Brand")->select();     
        $categoryList =  M("GoodsCategory")->select();     
        $this->assign('categoryList',$categoryList);
        $this->assign('brandList',$brandList);
        $this->display();                       
//        $return_arr = array('status'=>0或1或2, 'msg'=>'提示语','data'=>array()或字符串或数字, 前端需要什么就什么);                       
    }
    
    /**
     *  商品列表
     */
    public function ajaxGoodsList(){            
        
        $where = ' 1 = 1 '; // 搜索条件                
        I('intro')    && $where = "$where and ".I('intro')." = 1" ;
        I('cat_id')   && $where = "$where and cat_id = ".I('cat_id') ;
        I('brand_id') && $where = "$where and brand_id = ".I('brand_id') ;
        (I('is_on_sale') !== '') && $where = "$where and is_on_sale = ".I('is_on_sale') ;                
        // 关键词搜索
        
        if(!empty($_REQUEST['key_word']))
        {
            $where = "$where and (goods_name like '%".I('key_word')."%' or goods_sn like '%".I('key_word')."%')" ;
        }
        
        $model = M('Goods');
        $count = $model->where($where)->count();
        $Page       = new AjaxPage($count,5);
        /**  搜索条件下 分页赋值
        foreach($condition as $key=>$val) {
            $Page->parameter[$key]   =   urlencode($val);
        }
        */
        $show = $Page->show();
        $order_str = "`{$_POST['orderby1']}` {$_POST['orderby2']}";
        $goodsList = $model->where($where)->order($order_str)->limit($Page->firstRow.','.$Page->listRows)->select();

        $catList = D('goods_category')->select();
        $catList = convert_arr_key($catList, 'id');
        // print_r($catList);
        $this->assign('catList',$catList);
        $this->assign('goodsList',$goodsList);
        $this->assign('page',$show);// 赋值分页输出
        $this->display();         
    }    
    
    
    /**
     * 添加修改商品
     */
    public function addEditGoods(){
        
            $GoodsLogic = new GoodsLogic();                         
            $Goods = D('Goods'); //
            $type = $_POST['goods_id'] > 0 ? 2 : 1; // 标识自动验证时的 场景 1 表示插入 2 表示更新                        
            //ajax提交验证
            if(($_GET['is_ajax'] == 1) && IS_POST)
            {                
                C('TOKEN_ON',false);
                if(!$Goods->create(NULL,$type))// 根据表单提交的POST数据创建数据对象                 
                {
                    //  编辑
                    $return_arr = array(
                        'status' => -1,
                        'msg'   => '',
                        'data'  => $Goods->getError(),
                    );
                    $this->ajaxReturn(json_encode($return_arr));
                }else {
                    //  form表单提交
                   // C('TOKEN_ON',true);
                    
                    if ($type == 2)
                    {
                        $goods_id = $_POST['goods_id'];                                                
                        $Goods->save(); // 写入数据到数据库                        
                        $Goods->afterSave($goods_id);
                    }
                    else
                    {                           
                        $goods_id = $insert_id = $Goods->add(); // 写入数据到数据库
                        $Goods->afterSave($goods_id);
                    }                                        
                    
                    $GoodsLogic->saveGoodsAttr($goods_id, $_POST['goods_type']); // 处理商品 属性
                    
                    $return_arr = array(
                        'status' => 1,
                        'msg'   => '操作成功',                        
                        'data'  => array('url'=>U('Admin/Goods/goodsList')),
                    );
                    $this->ajaxReturn(json_encode($return_arr));
                }  
            }
            
            
            
            $goodsInfo = D('Goods')->where('goods_id='.I('GET.id',0))->find();
            $cat_list = $GoodsLogic->goods_cat_list();
            $brandList =  M("Brand")->select();
            $goodsType = M("GoodsType")->select();
            $this->assign('cat_list',$cat_list);      
            $this->assign('brandList',$brandList);
            $this->assign('goodsType',$goodsType);
            $this->assign('goodsInfo',$goodsInfo);  // 商品详情   
            $this->assign('goodsImages',M("GoodsImages")->where('goods_id ='.$_GET['id'])->select());  // 商品相册                        
            $this->initEditor(); // 编辑器
            $this->display('_goods');                                     
    } 
    
    
    /**
     * 修改Goods表的指定字段的指定值
     */
    public function changeGoodsField(){      
            $Goods = M("Goods");
            $data[$_REQUEST['field']] = I('GET.value');
            $data['goods_id'] = I('GET.id');           
            $Goods->save($data); // 根据条件保存修改的数据
    }
    
    /**
     * 商品类型  用于设置商品的属性
     */
    public function goodsTypeList(){
        $model = M("GoodsType");                
        $count = $model->count();        
        $Page  = new Page($count,100);
        $show  = $Page->show();
        $goodsTypeList = $model->order("id desc")->limit($Page->firstRow.','.$Page->listRows)->select();
        $this->assign('show',$show);
        $this->assign('goodsTypeList',$goodsTypeList);
        $this->display('goodsTypeList');
    }
    
    
    /**
     * 添加修改编辑  商品属性类型
     */
    public  function addEditGoodsType(){
        
            $_GET['id'] = $_GET['id'] ? $_GET['id'] : 0;            
            $model = M("GoodsType");           
            if(IS_POST)
            {
                    $model->create();
                    if($_GET['id'])
                        $model->save();
                    else
                        $model->add();
                    
                    $this->success("操作成功!!!",U('Admin/Goods/goodsTypeList'));               
                    exit;
            }           
           $goodsType = $model->find($_GET['id']);
           $this->assign('goodsType',$goodsType);
           $this->display('_goodsType');           
    }
    
    /**
     * 商品属性列表
     */
    public function goodsAttributeList(){       
        $goodsTypeList = M("GoodsType")->select();
        $this->assign('goodsTypeList',$goodsTypeList);
        $this->display();
    }
    
    
    /**
     *  商品属性列表
     */
    public function ajaxGoodsAttributeList(){            
        //ob_start('ob_gzhandler'); // 页面压缩输出
        $where = ' 1 = 1 '; // 搜索条件                        
        I('type_id')   && $where = "$where and type_id = ".I('type_id') ;                
        // 关键词搜索               
        $model = M('GoodsAttribute');
        $count = $model->where($where)->count();
        $Page       = new AjaxPage($count,13);
        $show = $Page->show();
        $goodsTypeList = $model->where($where)->order('`order` desc,attr_id DESC')->limit($Page->firstRow.','.$Page->listRows)->select();
        $this->assign('goodsTypeList',$goodsTypeList);
        $this->assign('page',$show);// 赋值分页输出
        $this->display();         
    }   
    
    /**
     * 添加修改编辑  商品属性
     */
    public  function addEditGoodsAttribute(){
                        
            $model = D("GoodsAttribute");                      
            $type = $_POST['attr_id'] > 0 ? 2 : 1; // 标识自动验证时的 场景 1 表示插入 2 表示更新             
            if(($_GET['is_ajax'] == 1) && IS_POST)//ajax提交验证
            {                
                C('TOKEN_ON',false);
                if(!$model->create(NULL,$type))// 根据表单提交的POST数据创建数据对象                 
                {
                    //  编辑
                    $return_arr = array(
                        'status' => -1,
                        'msg'   => '',
                        'data'  => $model->getError(),
                    );
                    $this->ajaxReturn(json_encode($return_arr));
                }else {                   
                   // C('TOKEN_ON',true); //  form表单提交
                    if ($type == 2)
                    {
                        $model->save(); // 写入数据到数据库                        
                    }
                    else
                    {
                        $insert_id = $model->add(); // 写入数据到数据库                        
                    }
                    $return_arr = array(
                        'status' => 1,
                        'msg'   => '操作成功',                        
                        'data'  => array('url'=>U('Admin/Goods/goodsAttributeList')),
                    );
                    $this->ajaxReturn(json_encode($return_arr));
                }  
            }                
           // 点击过来编辑时                 
           $_GET['attr_id'] = $_GET['attr_id'] ? $_GET['attr_id'] : 0;       
           $goodsTypeList = M("GoodsType")->select();           
           $goodsAttribute = $model->find($_GET['attr_id']);           
           $this->assign('goodsTypeList',$goodsTypeList);                   
           $this->assign('goodsAttribute',$goodsAttribute);
           $this->display('_goodsAttribute');           
    }  
    
    /**
     * 更改指定表的指定字段
     */
    public function updateField(){
        $primary = array(
                'goods' => 'goods_id',
                'goods_category' => 'id',
                'brand' => 'id',            
                'goods_attribute' => 'attr_id',            
        );        
        $model = D($_POST['table']);
        $model->$primary[$_POST['table']] = $_POST['id'];
        $model->$_POST['field'] = $_POST['value'];        
        $model->save();   
        $return_arr = array(
            'status' => 1,
            'msg'   => '操作成功',                        
            'data'  => array('url'=>U('Admin/Goods/goodsAttributeList')),
        );
        $this->ajaxReturn(json_encode($return_arr));
    }
    /**
     * 动态获取商品属性输入框 根据不同的数据返回不同的输入框类型
     */
    public function ajaxGetAttrInput(){
        $GoodsLogic = new GoodsLogic();
        $str = $GoodsLogic->getAttrInput($_REQUEST['goods_id'],$_REQUEST['type_id']);
        exit($str);
    }
        
    /**
     * 删除商品
     */
    public function delGoods()
    {
        $Goods = M("Goods"); 
        $Goods->where('goods_id ='.$_GET['id'])->delete(); 
        $return_arr = array('status' => 1,'msg' => '操作成功','data'  =>'',);   //$return_arr = array('status' => -1,'msg' => '删除失败','data'  =>'',);        
        $this->ajaxReturn(json_encode($return_arr));
    }
    
    /**
     * 品牌列表
     */
    public function brandList(){  
        $model = M("Brand");                
        $count = $model->count();        
        $Page  = new Page($count,10);
        $show  = $Page->show();
        $brandList = $model->order("`order` desc")->limit($Page->firstRow.','.$Page->listRows)->select();
        $this->assign('show',$show);
        $this->assign('brandList',$brandList);
        $this->display('brandList');
    }
    
    /**
     * 添加修改编辑  商品品牌
     */
    public  function addEditBrand(){        
            $_GET['id'] = $_GET['id'] ? $_GET['id'] : 0;            
            $model = M("Brand");           
            if(IS_POST)
            {
                    $model->create();
                    if($_GET['id'])
                        $model->save();
                    else
                        $_GET['id'] = $model->add();
                    
                    $this->success("操作成功!!!",U('Admin/Goods/brandList'));               
                    exit;
            }           
           $brand = $model->find($_GET['id']);
           $this->assign('brand',$brand);
           $this->display('_brand');           
    }    
    
    /**
     * 删除品牌
     */
    public function delBrand()
    {
        $model = M("Brand"); 
        $model->where('id ='.$_GET['id'])->delete(); 
        $return_arr = array('status' => 1,'msg' => '操作成功','data'  =>'',);   //$return_arr = array('status' => -1,'msg' => '删除失败','data'  =>'',);        
        $this->ajaxReturn(json_encode($return_arr));
    }      
    
    /**
     * 初始化编辑器链接     
     * 本编辑器参考 地址 http://fex.baidu.com/ueditor/
     */
    private function initEditor()
    {
        $this->assign("URL_upload", U('Admin/Ueditor/imageUp',array('savepath'=>'goods'))); // 图片上传目录
        $this->assign("URL_imageUp", U('Admin/Ueditor/imageUp',array('savepath'=>'article'))); //  不知道啥图片
        $this->assign("URL_fileUp", U('Admin/Ueditor/fileUp',array('savepath'=>'article'))); // 文件上传s
        $this->assign("URL_scrawlUp", U('Admin/Ueditor/scrawlUp',array('savepath'=>'article')));  //  图片流
        $this->assign("URL_getRemoteImage", U('Admin/Ueditor/getRemoteImage',array('savepath'=>'article'))); // 远程图片管理
        $this->assign("URL_imageManager", U('Admin/Ueditor/imageManager',array('savepath'=>'article'))); // 图片管理        
        $this->assign("URL_getMovie", U('Admin/Ueditor/getMovie',array('savepath'=>'article'))); // 视频上传
        $this->assign("URL_Home", "");
    }    
}