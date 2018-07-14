<?php
return array(
   'API_SECRET_KEY'=>'xx.com', // app 调用的签名秘钥
   'ZSF_WX_PAY'=>array(
		'app_name'=>'醉思凡',
		#统一下单api，不需要修改
		'pay_api'=>'https://api.mch.weixin.qq.com/pay/unifiedorder',
		'app_id'=>'wxd6d5ac1a3ffbfec5', 
		#商户号
		'mch_id'=>'1289005001',
		//api密钥
		'key'=>'zuisifanzuisifanzuisifanzuisifan',
		#异步回调地址
		'notify_url'=>'http://shopv1.host3.xx.com/index.php/Api/Wxpay/notify',
	),
);