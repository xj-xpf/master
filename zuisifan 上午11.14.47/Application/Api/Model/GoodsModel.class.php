<?php
/**
 * lnshop
 * 商品模型
 * @auther：dyr
 */
namespace Api\Model;

use Think\Model;


class GoodsModel extends Model
{
    private $model;
    private $db_prefix;
    public function _initialize()
    {
        $this->db_prefix = C('DB_PREFIX');
        $this->model = M('');
        parent::_initialize();
    }

    /**
     * 获取促销商品数据
     * @return mixed
     */
    public function getPromotionGoods()
    {
        //当商品促销结束时，----将商品表中的prom_type修改为0；prom_id修改为0；
        $condition_overdue['end_time'] = array('lt',time());
        $promoteList = M('PromGoods')->field('id')->where($condition_overdue)->select();
        foreach($promoteList as $k => $value){
            $promo_id = $value['id'];
            $condition_goods['prom_id'] =  $promo_id;
            $update['prom_type'] = 0;
            $update['prom_id'] = 0;
            M('Goods')->where($condition_goods)->save($update);
        }
        
        $condition['prom_type'] = array('neq',0);
        $condition['prom_id'] = array('neq',0);
        $condition['b.name'] = array('neq','');
        $condition['b.start_time'] = array('lt',time());
        $condition['b.end_time'] = array('gt',time());
        $condition['a.is_on_sale'] = array('eq',1);
        $condition['a.prom_type'] = array('eq',3);
        $promotion_goods = M('Goods')->alias('a')->field('a.*,b.name promname,b.start_time,b.end_time')
            ->join('ln_prom_goods  as b on a.prom_id = b.id ','left')
            ->where($condition)
            ->order('b.start_time asc')
            ->select();
        return $promotion_goods;
    }

    /**
     * 获取精品商品数据
     * @return mixed
     */
    public function getHighQualityGoods()
    {
        $goods_where = array('is_recommend' => 1, 'is_on_sale' => 1);
        $orderBy = array('sort' => 'desc');
        $promotion_goods = M('goods')
            ->field('goods_id,goods_name,shop_price')
            ->where($goods_where)
            ->order($orderBy)
            ->limit(9)
            ->select();
        return $promotion_goods;
    }

    /**
     * 获取新品商品数据
     * @return mixed
     */
    public function getNewGoods()
    {
        $goods_where = array('is_new' => 1,  'is_on_sale' => 1);
        $orderBy = array('sort' => 'desc');
        $new_goods = M('goods')
            ->field('goods_id,goods_name,shop_price')
            ->where($goods_where)
            ->order($orderBy)
            ->limit(9)
            ->select();
        return $new_goods;
    }

    /**
     * 获取热销商品数据
     * @return mixed
     */
    public function getHotGood()
    {
        $goods_where = array('is_hot' => 1,  'is_on_sale' => 1);
        $orderBy = array('sort' => 'desc');
        $new_goods = M('goods')
            ->field('goods_id,goods_name,shop_price')
            ->where($goods_where)
            ->order($orderBy)
            ->limit(9)
            ->select();
        return $new_goods;
    }

    /**
     * 获取首页轮播图片
     * @return mixed
     */
    public function getHomeAdv()
    {
        $adv = M('ad')->where('pid = 2')->field(array('ad_link','ad_name','ad_code'))->cache(true,LNSHOP_CACHE_TIME)->select();
        //广告地址转换
        foreach($adv as $k=>$v){
            if(!strstr($v['ad_link'],'http')){
                $adv[$k]['ad_link'] = SITE_URL.$v['ad_link'];
            }
            $adv[$k]['ad_code'] = SITE_URL.$v['ad_code'];
        }
        return $adv;
    }
}