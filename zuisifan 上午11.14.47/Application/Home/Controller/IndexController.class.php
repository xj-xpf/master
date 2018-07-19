<?php
/**
 * Author: xx.com
 * this is test
 */
namespace Home\Controller;
use Think\Page;
use Think\Verify;
use Home\Logic\UsersLogic;

class IndexController extends BaseController {

    public function index(){

        // 如果是手机跳转到 手机模块
        if(true == isMobile()){
            header("Location: ".U('Mobile/Index/index'));
        }

        $hot_goods = $hot_cate = $cateList = array();
        $sql = "select a.goods_name,a.goods_id,a.shop_price,a.market_price,a.cat_id,b.parent_id_path,b.name from __PREFIX__goods as a left join ";
        $sql .= " __PREFIX__goods_category as b on a.cat_id=b.id where a.is_hot=1 and a.is_on_sale=1 order by a.sort";//二级分类下热卖商品
        $index_hot_goods = M()->query($sql);//首页热卖商品
		if($index_hot_goods){
			foreach($index_hot_goods as $val){
				$cat_path = explode('_', $val['parent_id_path']);
				$hot_goods[$cat_path[1]][] = $val;
			}
		}


        $hot_category = M('goods_category')->where("is_hot=1 and level=3 and is_show=1")->select();//热门三级分类
        foreach ($hot_category as $v){
        	$cat_path = explode('_', $v['parent_id_path']);
        	$hot_cate[$cat_path[1]][] = $v;
        }

        foreach ($this->cateTrre as $k=>$v){
            if($v['is_hot']==1){
        		$v['hot_goods'] = empty($hot_goods[$k]) ? '' : $hot_goods[$k];
        		$v['hot_cate'] = empty($hot_cate[$k]) ? '' : $hot_cate[$k];
        		$cateList[] = $v;
        	}
        }

        //banner图下热卖商品
        //$hot_sql = "select * from `__PREFIX__goods` where is_new = 1 and is_hot = 1 and is_recommend = 1 and is_on_sale = 1 order by sort desc limit 4";
        $hot_seller = M('goods')->where(array('is_hot'=>1, 'is_on_sale'=>1))->order(array('sort'=>'desc'))->limit(4)->select();

        //新闻公告
        //$col_sql = "select * from `__PREFIX__article`  where cat_id = 4 order by article_id desc limit 4";
        $now_time = time();
        $not_col = M('article')->where(array('cat_id'=>4, 'is_open'=> 1, 'publish_time'=>array('elt', $now_time) ))->order(array('article_id'=>'desc'))->limit(4)->select();

        //$new_sql = "select * from `__PREFIX__article`  where cat_id = 3 order by article_id desc limit 4";
        $not_new = M('article')->where(array('cat_id'=>6, 'is_open'=> 1, 'publish_time'=>array('elt', $now_time) ))->order(array('article_id'=>'desc'))->limit(4)->select();

        //$category_sql="select * from `__PREFIX__goods_category` where is_show = 1 and `level` = 1  limit 7";
        $category_list = M('goods_category')->where(array('is_show'=>1, 'level'=>1))->limit(7)->select();
        // p($category_list);
        foreach($category_list as $key => $value){
            $cat_id_arr = getCatGrandson($value['id']); // 找到某个大类下面的所有子分类id
            $cat_id_str = implode(',',$cat_id_arr);
            $category_list[$key]['second'] = M('goods_category')->where(array('is_show'=>1, 'parent_id'=>$value['id']))->select();

            $category_list[$key]['goods'] = M('goods')->where(array('cat_id'=>array('in',$cat_id_str),'is_on_sale'=>1))-> order(array('sort'=>'desc','goods_id'=>'desc'))->limit(1)->select();
            $category_list[$key]['goods2'] = M('goods')->where(array('cat_id'=>array('in',$cat_id_str),'is_on_sale'=>1))-> order(array('sort'=>'desc','goods_id'=>'desc'))->limit(1,6)->select();
        }


        $this->assign('cateList',$cateList);
        $this->assign('hot_seller', $hot_seller);
        $this->assign('not_col', $not_col);
        $this->assign('not_new', $not_new);
        $this->assign('category_list', $category_list);
        $this->display();
    }

    /**
     *  公告详情页
     */
    public function notice(){
        $this->display();
    }

    // 二维码
    public function qr_code(){
        // 导入Vendor类库包 Library/Vendor/Zend/Server.class.php
         require_once 'ThinkPHP/Library/Vendor/phpqrcode/phpqrcode.php';
          //import('Vendor.phpqrcode.phpqrcode');
            error_reporting(E_ERROR);
            $url = urldecode($_GET["data"]);
            \QRcode::png($url);
    }

    // 验证码
    public function verify()
    {
        //验证码类型
        $type = I('get.type') ? I('get.type') : '';
        $fontSize = I('get.fontSize') ? I('get.fontSize') : '40';
        $length = I('get.length') ? I('get.length') : '4';

        $config = array(
            'fontSize' => $fontSize,
            'length' => $length,
            'useCurve' => true,
            'useNoise' => false,
        );
        $Verify = new Verify($config);
        $Verify->entry($type);
    }

    // 限时秒杀
//    public function promoteList()
//    {
//        $count =  M('flash_sale')->where(time()." >= start_time and ".time()." <= end_time ")->count();// 查询满足要求的总记录数
//        $Page = new \Think\Page($count,20);// 实例化分页类 传入总记录数和每页显示的记录数
//        $show = $Page->show();// 分页显示输出
//        $this->assign('page',$show);// 赋值分页输出
//        $goodsList = M('flash_sale')->join('ln_goods as g on g.goods_id = ln_flash_sale.goods_id ')->where(time()." >= start_time and ".time()." <= end_time")->limit($Page->firstRow.','.$Page->listRows)->select(); // 找出这个商品
//        $this->assign('goodsList',$goodsList);
//        $this->display();
//    }


    // 促销活动页面
    public function promoteList()
    {
        //当商品促销结束时，----将商品表中的prom_type修改为0；prom_id修改为0；
        $condition_overdue['end_time'] = array('lt', time());
        $promoteList = M('flash_sale')->field('id')->where($condition_overdue)->select();
        foreach ($promoteList as $k => $value) {
            $promo_id = $value['id'];
            $condition_goods['prom_id'] = $promo_id;
            $update['prom_type'] = 0;
            $update['prom_id'] = 0;
            M('Goods')->where($condition_goods)->save($update);
//        }
            $condition['prom_type'] = array('neq', 0);
            $condition['prom_id'] = array('neq', 0);
            $condition['b.goods_name'] = array('neq', '');
            $condition['b.start_time'] = array('lt', time());
            $condition['b.end_time'] = array('gt', time());
            $condition['a.is_on_sale'] = array('eq', 1);
            $condition['a.prom_type'] = array('eq', 1);
//            $goodsList = M('flash_sale')->select();
            $goodsList = M('flash_sale')->join('ln_goods as g on g.goods_id = ln_flash_sale.goods_id ')->where(time()." >= start_time and ".time()." <= end_time")->limit($Page->firstRow.','.$Page->listRows)->select(); // 找出这个商品
            $this->assign('goodsList', $goodsList);
            $this->display();
        }
    }





    function truncate_tables (){
        $model = new \Think\Model(); // 实例化一个model对象 没有对应任何数据表
        $tables = $model->query("show tables");
        $table = array('tp_admin','tp_config','tp_region','tp_system_module','tp_admin_role','tp_system_menu','tp_article_cat');
        foreach($tables as $key => $val)
        {
           // if(!in_array($val['tables_in_lnshop'], $table))
               // echo "truncate table ".$val['tables_in_lnshop'].' ; ';
              //  echo "<br/>";
        }
    }
    //通过ajax加载菜单
    public function ajax_get_menu(){
        $menu = M('navigation')->where(array('is_show'=>1))->order(array('sort'=>'desc'))->select();
        $result = array('status'=>1,'msg'=>'','result'=>$menu); // 返回结果状态
        exit(json_encode($result));
    }

    //微信登录
    public function webchatLogin(){
        $code = I('code','');
        $url = "https://api.weixin.qq.com/sns/oauth2/access_token?appid=wx5e56b5c8a0bfce5c&secret=d5d8b984ff50e57a215501b5cacacc70&code=".$code."&grant_type=authorization_code";

        $res = file_get_contents($url);//获取token等数据{"access_token":"dbERFaeRnx9_fj1UoZq2fIILakAnoOOQ15hXY9BEEEQr5TmmDCDgA0JeZs4helmx4EafHLQ5x4pyI3S1DSU6n-1P5zyfhonFcfru9iePpBw","expires_in":7200,"refresh_token":"AzgntVNMOao16gT2BwmQrAo-Xl9sQ2xxyB1kRUverPLcldpM9VrYMSdL6P2lqKaODwRr4AvhmOmUXPn82DQbGykTQDJUxdcRfX-n0_VSrMM","openid":"ob-3d1bC80TY9recz1zq3B-GV7N8","scope":"snsapi_login","unionid":"owOtgv_EoX4PPWiyMNIAu_OjIFmU"}
        $resStd = json_decode($res);//数据格式stdClass Object
        //将std格式的数据转换成纯数组
        $resArray = object_array($resStd);
        //刷新或续期access_token使用
        $urlRefresh = "https://api.weixin.qq.com/sns/oauth2/refresh_token?appid=wx5e56b5c8a0bfce5c&grant_type=refresh_token&refresh_token=".$resArray['refresh_token'];

        $newToken = file_get_contents($urlRefresh);
        $newTokenStd = json_decode($newToken);//数据格式stdClass Object
        //将std格式的数据转换成纯数组
        $newTokenArray = object_array($newTokenStd);
        //最后获取用户的个人信息
        $urlUserInfo = "https://api.weixin.qq.com/sns/userinfo?access_token=".$newTokenArray['access_token']."&openid=".$newTokenArray['openid'];
        $userInfo = file_get_contents($urlUserInfo);
        $userInfoStd = json_decode($userInfo);//数据格式stdClass Object
        //将std格式的数据转换成纯数组
        $userInfoArray = object_array($userInfoStd);


        //判断用户是否存在，如果存在就是登录，如果不存在，就按照注册然后再跳转
        $where['openid'] = $userInfoArray['openid'];
        //$where['status'] = 0;
        $info = M('Users')->where($where)->field()->find();
        if($info){
            //跳转到首页，同时存cookie
            session('user',null);
            session('user',$userInfo);
            $this->redirect('Home/Index/index');
        }else{
            $logic = new UsersLogic();
            $data = $logic->thirdLogin($userInfoArray);
            /*$data['wx_unionid'] = $userInfoArray['unionid'];//微信的唯一标识
            $data['wx_usernick'] = $userInfoArray['nickname'];
            $data['qq_avatar_thumb'] = $userInfoArray['headimgurl'];
            $data['username'] = $userInfoArray['nickname'];
            $userauth = session('user_auth');*/

            /*if(!$userauth){
                $res = M('User')->add($data);
                $userInfoWeb = M('User')->where('id='.$res)->field()->find();

            }else{
                $user_id = $userauth['id'];
                M('User')->where('id='.$user_id)->save($data);
                $userInfoWeb = M('User')->where('id='.$user_id)->find();
            }*/


            //$userInfoWeb['username']=  $userInfoWeb['wx_usernick'];
            session('user',null);
            session('user',$data);
            $this->redirect('Home/Index/index');

        }
    }

}