<?php
/**
 * Author: xx.com
 */
namespace Home\Controller;
use Home\Logic\CartLogic;
use Home\Logic\GoodsLogic;
use Think\AjaxPage;
use Think\Page;
use Think\Verify;
class GoodsController extends BaseController {
    public function index(){
        $this->display();
    }


   /**
    * 商品详情页
    */
    public function goodsInfo(){

        //  form表单提交
        C('TOKEN_ON',true);
        $goodsLogic = new GoodsLogic();
        $goods_id = I("get.id");
        $goods = M('Goods')->where("goods_id = $goods_id")->find();
        if(empty($goods) || ($goods['is_on_sale'] == 0)){
        	$this->error('该商品已经下架',U('Index/index'));
        }else{
            if(cookie('user_id') && $goods_id){
                //添加浏览记录
                $goodsLogic->addgoodsView(cookie('user_id'), $goods_id);
            }
        }

        if($goods['brand_id']){
            $brnad = M('brand')->where("id =".$goods['brand_id'])->find();
            $goods['brand_name'] = $brnad['name'];
        }
        $goods_images_list = M('GoodsImages')->where("goods_id = $goods_id")->select(); // 商品 图册
        $goods_attribute = M('GoodsAttribute')->getField('attr_id,attr_name'); // 查询属性
        $goods_attr_list = M('GoodsAttr')->where("goods_id = $goods_id")->select(); // 查询商品属性表
        $filter_spec = $goodsLogic->get_spec($goods_id);
        //商品是否正在限时抢购促销中
        if($goods['prom_type'] == 1)
        {
            $goods['flash_sale'] = get_goods_promotion($goods['goods_id']);
            var_dump($goods['flash_sale']);
            $flash_sale = M('flash_sale')->where("id = {$goods['prom_id']}")->find();
            $this->assign('flash_sale',$flash_sale);
        }

        if($goods['prom_type'] == 3){
            $goods['promotion'] = get_goods_promotion($goods['goods_id']);
        }

        $freight_free = tpCache('shopping.freight_free'); // 全场满多少免运费
        $spec_goods_price  = M('spec_goods_price')->where("goods_id = $goods_id")->getField("key,price,store_count"); // 规格 对应 价格 库存表
        M('Goods')->where("goods_id=$goods_id")->save(array('click_count'=>$goods['click_count']+1 )); //统计点击数
        $commentStatistics = $goodsLogic->commentStatistics($goods_id);// 获取某个商品的评论统计
        $point_rate = tpCache('shopping.point_rate');
        //重新设置商品的评论数--之前是获取的所有评论数，后台未审核通过的评论也统计进去了-liuyang
        $goods['comment_count'] = $commentStatistics['c0'];
        //获取未选定规格商品信息
        $filter_spec2 = $goodsLogic->get_spec_other($goods_id);
        //获取选定规格商品信息
        $item_array = $goodsLogic->getSpecInput($goods_id, $filter_spec2);
        //推荐商品列表
        $recommened_goods = M('goods')->where(array('is_recommend'=>1))->order(array('goods_id'=> 'desc'))->limit(10)->select();

        $after_sale = M('article')->where(array('article_id'=> 1))->find();

        //默认运费
        $shipping_code = tpCache('shopping.default_shipping');
        $shipping_area_id = M("ShippingArea")->where("shipping_code = '$shipping_code' and is_default = 1")->getField('shipping_area_id');
        if(!$shipping_area_id){
            $shipping_area_id = M("ShippingArea")->where("shipping_code = '$shipping_code'")->getField('shipping_area_id');
        }
        $shipping_config = M('ShippingArea')->where(array('shipping_area_id' => $shipping_area_id))->getField('config');
        $shipping_config  = unserialize($shipping_config);
        $shipping_config['money'] = $shipping_config['money'] ? $shipping_config['money'] : 0;

        $this->assign('freight_free', $freight_free);// 全场满多少免运费
        $this->assign('spec_goods_price', json_encode($spec_goods_price,true)); // 规格 对应 价格 库存表
        $this->assign('navigate_goods',navigate_goods($goods_id,1));// 面包屑导航
        $this->assign('commentStatistics',$commentStatistics);//评论概览
        $this->assign('goods_attribute',$goods_attribute);//属性值
        $this->assign('goods_attr_list',$goods_attr_list);//属性列表
        $this->assign('filter_spec',$filter_spec);//规格参数
        $this->assign('goods_images_list',$goods_images_list);//商品缩略图
        $this->assign('siblings_cate',$goodsLogic->get_siblings_cate($goods['cat_id']));//相关分类
        $this->assign('look_see',$goodsLogic->get_look_see($goods));//看了又看
        $this->assign('goods',$goods);
        $this->assign('point_rate',$point_rate);
        $this->assign('item_array', $item_array);
        $this->assign('recommened_goods', $recommened_goods);
        $this->assign('after_sale', $after_sale);
        $this->assign('config_money', $shipping_config['money']);
//        $str = $goodsLogic->getSpecInput($goods_id, $filter_spec);
//        $this->assign('str', $str);
        $this->display();
    }

    /**
     * 获取可发货地址
     */
    public function getRegion()
    {
        $goodsLogic = new GoodsLogic();
        $region_list = $goodsLogic->getRegionList();//获取配送地址列表
        $region_list['status'] = 1;
        $this->ajaxReturn($region_list);
    }

    /**
     * 商品列表页
     */
    public function goodsList(){

        /*$key = md5($_SERVER['REQUEST_URI'].$_POST['start_price'].'_'.$_POST['end_price']);
        $html = S($key);
        if(!empty($html))
        {
            exit($html);
        }*/

        $filter_param = array(); // 帅选数组
        $id = I('get.id',1); // 当前分类id
        $brand_id = I('get.brand_id',0);
        $spec = I('get.spec',0); // 规格
        $attr = I('get.attr',''); // 属性
        $sort = I('get.sort','goods_id'); // 排序
        $sort_asc = I('get.sort_asc','asc'); // 排序
        $price = I('get.price',''); // 价钱
        $start_price = trim(I('post.start_price','0')); // 输入框价钱
        $end_price = trim(I('post.end_price','0')); // 输入框价钱
        if($start_price && $end_price) $price = $start_price.'-'.$end_price; // 如果输入框有价钱 则使用输入框的价钱

        $filter_param['id'] = $id; //加入帅选条件中
        $brand_id  && ($filter_param['brand_id'] = $brand_id); //加入帅选条件中
        $spec  && ($filter_param['spec'] = $spec); //加入帅选条件中
        $attr  && ($filter_param['attr'] = $attr); //加入帅选条件中
        $price  && ($filter_param['price'] = $price); //加入帅选条件中

        $goodsLogic = new \Home\Logic\GoodsLogic(); // 前台商品操作逻辑类

        // 分类菜单显示
        $goodsCate = M('GoodsCategory')->where("id = $id")->find();// 当前分类

        //($goodsCate['level'] == 1) && header('Location:'.U('Home/Channel/index',array('cat_id'=>$id))); //一级分类跳转至大分类馆
        $cateArr = $goodsLogic->get_goods_cate($goodsCate);
        // 帅选 品牌 规格 属性 价格
        $cat_id_arr = getCatGrandson ($id);
        //$filter_goods_id = M('goods')->where("is_on_sale=1 and cat_id in(".  implode(',', $cat_id_arr).")")->cache(true)->getField("goods_id",true);

        $filter_goods_id = M('goods')->where("is_on_sale=1 and cat_id in(".  implode(',', $cat_id_arr).")")->getField("goods_id",true);

        // 过滤帅选的结果集里面找商品
        if($brand_id || $price)// 品牌或者价格
        {
            $goods_id_1 = $goodsLogic->getGoodsIdByBrandPrice($brand_id,$price); // 根据 品牌 或者 价格范围 查找所有商品id
            $filter_goods_id = array_intersect($filter_goods_id,$goods_id_1); // 获取多个帅选条件的结果 的交集
        }
        if($spec)// 规格
        {
            $goods_id_2 = $goodsLogic->getGoodsIdBySpec($spec); // 根据 规格 查找当所有商品id
            $filter_goods_id = array_intersect($filter_goods_id,$goods_id_2); // 获取多个帅选条件的结果 的交集
        }
        if($attr)// 属性
        {
            $goods_id_3 = $goodsLogic->getGoodsIdByAttr($attr); // 根据 规格 查找当所有商品id
            $filter_goods_id = array_intersect($filter_goods_id,$goods_id_3); // 获取多个帅选条件的结果 的交集
        }

        $filter_menu  = $goodsLogic->get_filter_menu($filter_param,'goodsList'); // 获取显示的帅选菜单
        $filter_price = $goodsLogic->get_filter_price($filter_goods_id,$filter_param,'goodsList'); // 帅选的价格期间
        $filter_brand = $goodsLogic->get_filter_brand($filter_goods_id,$filter_param,'goodsList',1); // 获取指定分类下的帅选品牌
        //$filter_spec  = $goodsLogic->get_filter_spec($filter_goods_id,$filter_param,'goodsList',1); // 获取指定分类下的帅选规格

        $filter_attr  = $goodsLogic->get_filter_attr($filter_goods_id,$filter_param,'goodsList',1); // 获取指定分类下的帅选属性
        $count = count($filter_goods_id);
        $page = new Page($count,40);
        if($count > 0)
        {
            $goods_list = M('goods')->where("goods_id in (".  implode(',', $filter_goods_id).")")->order("$sort $sort_asc")->limit($page->firstRow.','.$page->listRows)->select();
            $filter_goods_id2 = get_arr_column($goods_list, 'goods_id');
            if($filter_goods_id2)
            //$goods_images = M('goods_images')->where("goods_id in (".  implode(',', $filter_goods_id2).")")->cache(true)->select();
            $goods_images = M('goods_images')->where("goods_id in (".  implode(',', $filter_goods_id2).")")->select();
        }

        //$goods_category = M('goods_category')->where('is_show=1')->cache(true)->getField('id,name,parent_id,level'); // 键值分类数组
       $goods_category = M('goods_category')->where('is_show=1')->getField('id,name,parent_id,level'); // 键值分类数组
        $navigate_cat = navigate_goods($id); // 面包屑导航

        $this->assign('goods_list',$goods_list);
        $this->assign('navigate_cat',$navigate_cat);
        $this->assign('goods_category',$goods_category);
        $this->assign('goods_images',$goods_images);  // 相册图片
        $this->assign('filter_menu',$filter_menu);  // 帅选菜单
        //$this->assign('filter_spec',$filter_spec);  // 帅选规格
        $this->assign('filter_attr',$filter_attr);  // 帅选属性
        $this->assign('filter_brand',$filter_brand);  // 列表页帅选属性 - 商品品牌
        $this->assign('filter_price',$filter_price);// 帅选的价格期间
        $this->assign('goodsCate',$goodsCate);
        $this->assign('cateArr',$cateArr);
        $this->assign('filter_param',$filter_param); // 帅选条件
        $this->assign('cat_id',$id);
        $this->assign('page',$page);// 赋值分页输出
        C('TOKEN_ON',false);
        $html = $this->fetch();
        //S($key,$html);
        echo $html;
    }

    /**
     *  查询配送地址，并执行回调函数
     */
    public function region()
    {
        $fid = I('fid');
        $callback = I('callback');
        $parent_region = M('region')->field('id,name')->where(array('parent_id'=>$fid))->select();
        echo $callback.'('.json_encode($parent_region).')';
        exit;
    }

    /**
     * 商品物流配送和运费
     */
    public function dispatching()
    {
        $goods_id = I('goods_id');//143
        $region_id = I('region_id');//28242
        $goods_logic = new GoodsLogic();
        $dispatching_data = $goods_logic->getGoodsDispatching($goods_id,$region_id);
        $this->ajaxReturn($dispatching_data);
    }

    /**
     * 商品搜索列表页
     */
    public function search()
    {
       //C('URL_MODEL',0);
        $filter_param = array(); // 帅选数组
        $id = I('get.id',0); // 当前分类id
        $brand_id = I('brand_id',0);
        $sort = I('sort','goods_id'); // 排序
        $sort_asc = I('sort_asc','asc'); // 排序
        $price = I('price',''); // 价钱
        $start_price = trim(I('start_price','0')); // 输入框价钱
        $end_price = trim(I('end_price','0')); // 输入框价钱
        if($start_price && $end_price) $price = $start_price.'-'.$end_price; // 如果输入框有价钱 则使用输入框的价钱
        $q = urldecode(trim(I('q',''))); // 关键字搜索
        empty($q) && $this->error('请输入搜索词');


        $id && ($filter_param['id'] = $id); //加入帅选条件中
        $brand_id  && ($filter_param['brand_id'] = $brand_id); //加入帅选条件中
        $price  && ($filter_param['price'] = $price); //加入帅选条件中
        $q  && ($_GET['q'] = $filter_param['q'] = $q); //加入帅选条件中

        $goodsLogic = new \Home\Logic\GoodsLogic(); // 前台商品操作逻辑类

        $where  = array(
            'is_on_sale' => 1
        );
        //引入
        if(file_exists(PLUGIN_PATH.'coreseek/sphinxapi.php'))
        {
            require_once(PLUGIN_PATH.'coreseek/sphinxapi.php');
            $cl = new \SphinxClient();
            $cl->SetServer(C('SPHINX_HOST'), C('SPHINX_PORT'));
            $cl->SetConnectTimeout(10);
            $cl->SetArrayResult(true);
            $cl->SetMatchMode(SPH_MATCH_ANY);
            $res = $cl->Query($q, "mysql");
            if($res){
                $goods_id_array = array();
                foreach ($res['matches'] as $key => $value) {
                    $goods_id_array[] = $value['id'];
                }
                if(!empty($goods_id_array)){
                    $where['goods_id'] = array('in',$goods_id_array);
                }else{
                    $where['goods_id'] = 0;
                }
            }else{
                $where['goods_name'] = array('like','%'.$q.'%');
            }
        }else{
            $where['goods_name'] = array('like','%'.$q.'%');
        }


        if($id)
        {
            $cat_id_arr = getCatGrandson ($id);
            $where['cat_id'] = array('in',implode(',', $cat_id_arr));
        }

        $search_goods = M('goods')->where($where)->getField('goods_id,cat_id');
        $filter_goods_id = array_keys($search_goods);
        $filter_cat_id = array_unique($search_goods); // 分类需要去重
        if($filter_cat_id)
        {
            $cateArr = M('goods_category')->where("id in(".implode(',', $filter_cat_id).")")->select();
            $tmp = $filter_param;
            foreach($cateArr as $k => $v)
            {
                $tmp['id'] = $v['id'];
                $cateArr[$k]['href'] = U("/Home/Goods/search",$tmp);
            }
        }
        // 过滤帅选的结果集里面找商品
        if($brand_id || $price)// 品牌或者价格
        {
            $goods_id_1 = $goodsLogic->getGoodsIdByBrandPrice($brand_id,$price); // 根据 品牌 或者 价格范围 查找所有商品id
            $filter_goods_id = array_intersect($filter_goods_id,$goods_id_1); // 获取多个帅选条件的结果 的交集
        }

        $filter_menu  = $goodsLogic->get_filter_menu($filter_param,'search'); // 获取显示的帅选菜单
        $filter_price = $goodsLogic->get_filter_price($filter_goods_id,$filter_param,'search'); // 帅选的价格期间
        $filter_brand = $goodsLogic->get_filter_brand($filter_goods_id,$filter_param,'search',1); // 获取指定分类下的帅选品牌

        $count = count($filter_goods_id);
        $page = new Page($count,20);
        if($count > 0)
        {
            $goods_list = M('goods')->where("is_on_sale=1 and goods_id in (".  implode(',', $filter_goods_id).")")->order("$sort $sort_asc")->limit($page->firstRow.','.$page->listRows)->select();
            $filter_goods_id2 = get_arr_column($goods_list, 'goods_id');
            if($filter_goods_id2)
            $goods_images = M('goods_images')->where("goods_id in (".  implode(',', $filter_goods_id2).")")->select();
        }

        $this->assign('goods_list',$goods_list);
        $this->assign('goods_images',$goods_images);  // 相册图片
        $this->assign('filter_menu',$filter_menu);  // 帅选菜单
        $this->assign('filter_brand',$filter_brand);  // 列表页帅选属性 - 商品品牌
        $this->assign('filter_price',$filter_price);// 帅选的价格期间
        $this->assign('cateArr',$cateArr);
        $this->assign('filter_param',$filter_param); // 帅选条件
        $this->assign('cat_id',$id);
        $this->assign('page',$page);// 赋值分页输出
        $this->assign('q',I('q'));
        C('TOKEN_ON',false);
        $this->display();
    }

    /**
     * 商品咨询ajax分页
     */
    public function ajax_consult(){
        $goods_id = I("goods_id",'0');
        $consult_type = I('consult_type','0'); // 0全部咨询  1 商品咨询 2 支付咨询 3 配送 4 售后

        $where = " is_show = 1 and parent_id = 0 and goods_id = $goods_id";
        if($consult_type > 0)
            $where .= " and consult_type = $consult_type ";

        $count = M('GoodsConsult')->where($where)->count();
        $page = new AjaxPage($count,5);
        $show = $page->show();
        $list = M('GoodsConsult')->where($where)->order("id desc")->limit($page->firstRow.','.$page->listRows)->select();
        $replyList = M('GoodsConsult')->where("parent_id > 0")->order("id desc")->select();

        $this->assign('consultCount',$count);// 商品咨询数量
        $this->assign('consultList',$list);// 商品咨询
        $this->assign('replyList',$replyList); // 管理员回复
        $this->assign('page',$show);// 赋值分页输出
        $this->display();
    }

    /**
     * 商品评论ajax分页
     */
    public function ajaxComment(){
        $goods_id = I("goods_id",'0');
        $commentType = I('commentType','1'); // 1 全部 2好评 3 中评 4差评
        if($commentType==5){
        	$where = "is_show = 1 and  goods_id = $goods_id and parent_id = 0 and img !='' ";
        }else{
        	$typeArr = array('1'=>'0,1,2,3,4,5','2'=>'4,5','3'=>'3','4'=>'0,1,2');
        	$where = "is_show = 1 and  goods_id = $goods_id and parent_id = 0 and ceil((deliver_rank + goods_rank + service_rank) / 3) in($typeArr[$commentType])";
        }
        $count = M('Comment')->where($where)->count();

        $page = new AjaxPage($count,5);
        $show = $page->show();
        $list = M('Comment')->alias('c')->join('LEFT JOIN __USERS__ u ON u.user_id = c.user_id')->where($where)->order("c.comment_id desc")->limit($page->firstRow.','.$page->listRows)->select();
        $replyList = M('Comment')->where("is_show = 1 and  goods_id = $goods_id and parent_id > 0")->order("add_time desc")->select();

        foreach($list as $k => $v){
            $list[$k]['img'] = unserialize($v['img']); // 晒单图片
        }
        $this->assign('commentlist',$list);// 商品评论
        $this->assign('replyList',$replyList); // 管理员回复
        $this->assign('page',$show);// 赋值分页输出
        $this->display();
    }

    /**
     *  商品咨询
     */
    public function goodsConsult(){
        //  form表单提交
        C('TOKEN_ON',true);
        $goods_id = I("goods_id",'0'); // 商品id
        $consult_type = I("consult_type",'1'); // 商品咨询类型
        $username = I("username",'LNshop用户'); // 网友咨询
        $content = I("content"); // 咨询内容

        $verify = new Verify();
        if (!$verify->check(I('post.verify_code'),'consult')) {
            $this->error("验证码错误");
        }

        $goodsConsult = M('goodsConsult');
        if (!$goodsConsult->autoCheckToken($_POST))
        {
                $this->error('你已经提交过了!', U('/Home/Goods/goodsInfo',array('id'=>$goods_id)));
                exit;
        }

        $data = array(
            'goods_id'=>$goods_id,
            'consult_type'=>$consult_type,
            'username'=>$username,
            'content'=>$content,
            'add_time'=>time(),
        );
        $goodsConsult->add($data);
        $this->success('咨询已提交!', U('/Home/Goods/goodsInfo',array('id'=>$goods_id)));
    }

    /**
     * 用户收藏某一件商品
     * @param type $goods_id
     */
    public function collect_goods($goods_id)
    {
        $goods_id = I('goods_id');
        $goodsLogic = new \Home\Logic\GoodsLogic();
        $result = $goodsLogic->collect_goods(cookie('user_id'),$goods_id);
        exit(json_encode($result));
    }

    /**
     * 加入购物车弹出
     */
    public function open_add_cart()
    {
         $this->display();
    }

    /**
     * 积分商城
     */
    public function integralMall()
    {
        $cat_id = I('get.id');
        $minValue = I('get.minValue');
        $maxValue = I('get.maxValue');
        $brandType = I('get.brandType');
        $point_rate = tpCache('shopping.point_rate');
        $is_new = I('get.is_new',0);
        $exchange = I('get.exchange',0);
        $goods_where = array(
            'is_on_sale' => 1,
        );
        //积分兑换筛选
        $exchange_integral_where_array = array(array('gt',0));
        // 分类id
        if (!empty($cat_id)) {
            $goods_where['cat_id'] = array('in', getCatGrandson($cat_id));
        }
        //积分截止范围
        if (!empty($maxValue)) {
            array_push($exchange_integral_where_array, array('lt', $maxValue));
        }
        //积分起始范围
        if (!empty($minValue)) {
            array_push($exchange_integral_where_array, array('gt', $minValue));
        }
        //积分+金额
        if ($brandType == 1) {
            array_push($exchange_integral_where_array, array('exp', ' < shop_price* ' . $point_rate));
        }
        //全部积分
        if ($brandType == 2) {
            array_push($exchange_integral_where_array, array('exp', ' = shop_price* ' . $point_rate));
        }
        //新品
        if($is_new == 1){
            $goods_where['is_new'] = $is_new;
        }
        //我能兑换
        $user_id = cookie('user_id');
        if ($exchange == 1 && !empty($user_id)) {
            $user_pay_points = intval(M('users')->where(array('user_id' => $user_id))->getField('pay_points'));
            if ($user_pay_points !== false) {
                array_push($exchange_integral_where_array, array('lt', $user_pay_points));
            }
        }

        $goods_where['exchange_integral'] =  $exchange_integral_where_array;
        $goods_list_count = M('goods')->where($goods_where)->count();
        $page = new Page($goods_list_count, 15);
        $goods_list = M('goods')->where($goods_where)->limit($page->firstRow . ',' . $page->listRows)->select();
        $goods_category = M('goods_category')->where(array('level' => 1))->select();

        $this->assign('goods_list', $goods_list);
        $this->assign('page', $page->show());
        $this->assign('goods_list_count',$goods_list_count);
        $this->assign('goods_category', $goods_category);//商品1级分类
        $this->assign('point_rate', $point_rate);//兑换率
        $this->assign('nowPage',$page->nowPage);// 当前页
        $this->assign('totalPages',$page->totalPages);//总页数
        $this->display();
    }

    /**
     * 选择规格弹出相应商品信息
     */
    public function ajaxSelectSpec(){
        //获取规格信息 数组形式
        $spec = I("spec", null);
        $goods_id = I('goods_id', 0, 'intval');

        $goodsLogic = new \Home\Logic\GoodsLogic();
        $goods = M('Goods')->where("goods_id = $goods_id")->find();
        if(empty($goods) || ($goods['is_on_sale'] == 0)){
            $this->ajaxReturn(array('status'=>-1,'msg'=>'该商品已经下架'));
        }
        //获取未选定规格商品信息
        $filter_spec = $goodsLogic->get_spec_other($goods_id, $spec);
        //获取选定规格商品信息
        $str = $goodsLogic->getSpecInput($goods_id, $filter_spec);
        $this->ajaxReturn(array('status'=>1, 'msg'=>'操作成功','data'=>$str));
    }

    /**
     *选取商品加入清单操作
     */
    public function ajaxGoodsInfoCache(){

        $goods_id = I('post.goods_id');
        $goods = M('Goods')->where("goods_id = $goods_id")->find();
        $goods_item = I('post.goods_spec');     //获取规格
        $goods_price = I('post.goods_price');   //获取价格
        $goods_num = I('post.goods_num');       //获取数量

        $total_num = 0;                         //总数
        $total_price = 0;                       //总价

        $len = count($goods_num);
        for($i = 0;$i<$len;$i++){
            if($goods_num[$i] > 0){
                $total_num += $goods_num[$i];
                $goods_price_pre = round($goods_price[$i], 2) * 100/100;

                $total_price = $total_price + round(($goods_num[$i] * $goods_price_pre), 2)*100/100;

                //获取数量大于0的商品信息
                $goods_array[$goods_item[$i]]['num'] = $goods_num[$i];
                $goods_array[$goods_item[$i]]['item_key'] = $goods_item[$i];
            }
        }
        $buy_limit = 0;
        //商品是否正在促销中
        if($goods['prom_type'] == 1)
        {
            $goods['flash_sale'] = get_goods_promotion($goods['goods_id']);
            $flash_sale = M('flash_sale')->where("id = {$goods['prom_id']}")->find();
            $buy_limit = $flash_sale['buy_limit'];
        }

        if($buy_limit != 0 && $total_num >$buy_limit){
            $msg = '该商品限购'.$buy_limit.'件';
            $this->ajaxReturn(array('status'=>-1, 'msg'=>$msg, 'data'=>''));
        }

        //总价数据格式化
        //$total_price = $total_price * 100 / 100;
        //封装清单数据
        $goods_list = array();
        foreach($goods_array as $key => $value){
            if(count($goods_list)){
                //清单中已存在数据，取一级分类id
                $first_key = (reset($goods_list)['item_key']);
                $spec_id = M('spec_item')->where(array('id'=>$first_key))->getField('spec_id');
            }
            //查询规格名称
            $key_array = explode("_", $key);
            $first_where['id'] =  $key_array[0];

            if($spec_id)
                $first_where['spec_id'] = $spec_id;

            $first_item = M('spec_item')->where($first_where)->getField('item');
            if($first_item){
                $second_item = M('spec_item')->where(array('id'=>$key_array[1]))->getField('item');
            }else{
                $first_where['id'] =  $key_array[1];
                $first_item = M('spec_item')->where($first_where)->getField('item');
                $second_item = M('spec_item')->where(array('id'=>$key_array[0]))->getField('item');
            }

            $goods_selected['second_item'] = $second_item;              //商品第二个规格
            $goods_selected['num'] = $value['num'];                     //商品数量

            $goods_list[$first_item]['goods_num'] = $goods_list[$first_item]['goods_num'] + $value['num'];      //商品第一个规格 数量
            $goods_list[$first_item]['item_key'] = $goods_list[$first_item]['item_key'] ? $goods_list[$first_item]['key'] : $key_array[0];  //第一个规格的key值
            $goods_list[$first_item][] = $goods_selected;
        }

        $this->ajaxReturn(array('status'=>1, 'msg'=>'操作成功','data'=>array('total_num'=>$total_num, 'total_price'=>$total_price, 'goods_info'=>$goods_list)));

    }

}