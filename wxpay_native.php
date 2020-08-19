<?php

/**
 * 微信扫码支付
 */
class wxpay_native {

    /**
     * 支付信息初始化
     * @param array $payment_info
     */
    public function __construct($payment_info = array()) {
        define('WXN_APPID', $payment_info['payment_config']['wx_appid']);
        define('WXN_MCHID', $payment_info['payment_config']['wx_mch_id']);
        define('WXN_KEY', $payment_info['payment_config']['wx_key']);
        define('WXN_APPSECRET', $payment_info['payment_config']['wx_appsecret']);
        define('WXN_SSLCERT_PATH', $payment_info['payment_config']['wx_sslcert_path']);
        define('WXN_SSLKEY_PATH', $payment_info['payment_config']['wx_sslkey_path']);
    }

    /**
     * 组装包含支付信息的url(模式1)  失效
     */
    public function get_payforms() {
        require_once PLUGINS_PATH . '/payments/wxpay_native/lib/WxPay.Api.php';
        require_once PLUGINS_PATH . '/payments/wxpay_native/WxPay.NativePay.php';
        require_once PLUGINS_PATH . '/payments/wxpay_native/log.php';
        $notify = new NativePay();
        return $notify->GetPrePayUrl($order_info['pay_sn']);
    }

    /**
     * 组装包含支付信息的url(模式2)
     */
    public function get_payform($order_info) {
        require_once PLUGINS_PATH . '/payments/wxpay_native/lib/WxPay.Api.php';
        require_once PLUGINS_PATH . '/payments/wxpay_native/WxPay.NativePay.php';
        require_once PLUGINS_PATH . '/payments/wxpay_native/log.php';

        //统一下单
        $input = new WxPayUnifiedOrder();
        $input->SetBody(config('site_name') . $order_info['pay_sn'] . '订单');
        $input->SetAttach($order_info['order_type']);
        $input->SetOut_trade_no($order_info['pay_sn'].'_'.TIMESTAMP);//31个字符,微信限制为32字符以内  TIMESTAMP 用来防止做随机数,用户支付订单后取消,已产生的订单不能重复支付
        $input->SetTotal_fee(bcmul($order_info['api_pay_amount'] , 100,0));
        $input->SetTime_start(date("YmdHis"));
        $input->SetTime_expire(date("YmdHis", TIMESTAMP + 3600));
        $input->SetGoods_tag('');
        $input->SetNotify_url(str_replace('/index.php', '', HOME_SITE_URL) . '/payment/wxpay_native_notify.html');
        $input->SetTrade_type("NATIVE");
        $input->SetProfit_sharing("Y");     //设置分账参数
        $input->SetSub_Mch_id(config('sub_mch_id')); //子商户商户号
        //$input->SetOpenid($openId);
        $input->SetProduct_id($order_info['pay_sn']);
        $result = WxPayApi::unifiedOrder($input);

        if(isset($result["code_url"])){
            return $result["code_url"];
        }else{
            halt($result);
        }
    }

    /**
     * 异步验证
     */
    public function verify_notify() {
        require_once PLUGINS_PATH . '/payments/wxpay_native/lib/WxPay.Api.php';
        require_once PLUGINS_PATH . '/payments/wxpay_native/lib/WxPay.Notify.php';

        $notify = new \WxPayNotify();
        $notify->Handle(true);
        $xml = file_get_contents('php://input');
        $data = $notify->FromXml($xml);
        
        if (!array_key_exists("transaction_id", $data)) {
            $verify_notify = false;
        } else {
            $transaction_id = $data['transaction_id'];
            $input = new \WxPayOrderQuery();
            $input->SetTransaction_id($transaction_id);
            $wxpay = new \WxPayApi();
            $input->SetSub_Mch_id(config('sub_mch_id')); //子商户号
            $result = $wxpay->orderQuery($input);

            if (array_key_exists("return_code", $result) && array_key_exists("result_code", $result) && $result["return_code"] == "SUCCESS" && $result["result_code"] == "SUCCESS") {
                $verify_notify = TRUE;
            } else {
                $verify_notify = false;
            }
        }
        if ($verify_notify) {
            $notify_result = array(
                'out_trade_no' => $data["out_trade_no"], #商户订单号
                'trade_no' => $data['transaction_id'], #交易凭据单号
                'total_fee' => $data["total_fee"] / 100, #涉及金额
                'order_type'=>$data["attach"],
                'trade_status' => '1',
            );
        } else {
            $notify_result = array(
                'trade_status' => '0',
            );
        }
        return $notify_result;
    }
    
    
    /**
     * 原路退款
     */
    public function trade_refund($order_info,$refund_amount)
    {
        require_once PLUGINS_PATH . '/payments/wxpay_native/lib/WxPay.Api.php';
        require_once PLUGINS_PATH . '/payments/wxpay_native/lib/WxPay.Notify.php';
        
	    $transaction_id = $order_info['trade_no'];
	    //total_fee总订单价格
        $total_fee = ($order_info['total_order_amount']-$order_info['rcb_amount']-$order_info['pd_amount'])*100;
        $refund_fee = $refund_amount*100;
        $input = new WxPayRefund();
        $input->SetTransaction_id($transaction_id);
        $input->SetTotal_fee($total_fee);
        $input->SetRefund_fee($refund_fee);

        $input->SetOut_refund_no("sdkphp" . date("YmdHis"));
        $input->SetOp_user_id('');
        $wxpay = new \WxPayApi();
        $result = $wxpay->refund($input);
        
        if ($result['return_code'] == 'SUCCESS') {
            if ($result['result_code'] == 'SUCCESS') {
                return ds_callback(TRUE);
            } elseif ($result['err_code'] == 'NOTENOUGH') {//未结算资金不足时使用可用资金去退款
                $input->SetRefund_account('REFUND_SOURCE_RECHARGE_FUNDS');
                $result = $wxpay->refund($input);
                if ($result['return_code'] == 'SUCCESS') {
                    if ($result['result_code'] == 'SUCCESS') {
                        return ds_callback(TRUE);
                    } else {
                        return ds_callback(FALSE, $result['err_code_des']);
                    }
                } else {
                    return ds_callback(FALSE, $result['return_msg']);
                }
            } else {
                return ds_callback(FALSE, $result['err_code_des']);
            }
        } else {
            return ds_callback(FALSE, $result['return_msg']);
        }
    }
    
    /**
     * 转账
     */
    public function fund_transfer($withdraw_info)
    {
        require_once PLUGINS_PATH . '/payments/wxpay_native/lib/WxPay.Api.php';
        
        
        $inputObj = new WxPayResults();
	    $inputObj->SetData('mch_appid', WxPayConfig::APPID);
        $inputObj->SetData('mchid', WxPayConfig::MCHID);
        $inputObj->SetData('nonce_str', WxPayApi::getNonceStr());
        $inputObj->SetData('partner_trade_no', $withdraw_info['pdc_sn']);
        $inputObj->SetData('openid', $withdraw_info['pdc_bank_no']);
        $inputObj->SetData('check_name', 'NO_CHECK');
        $inputObj->SetData('amount', $withdraw_info['pdc_amount']*100);
        $inputObj->SetData('desc', config('site_name')."账户提现");
        $inputObj->SetData('spbill_create_ip', $_SERVER['REMOTE_ADDR']);
        $inputObj->SetSign();//签名
        $xml = $inputObj->ToXml();

        $url='https://api.mch.weixin.qq.com/mmpaymkttransfers/promotion/transfers';
        
        $startTimeStamp = WxPayApi::getMillisecond(); //请求开始时间
        $response = WxPayApi::postXmlCurl($xml, $url, true, 6);
        $obj=new WxPayResults();
        $result = $obj->FromXml($response);

        if($result['return_code'] == 'SUCCESS'){
            if($result['result_code'] == 'SUCCESS'){
                return ds_callback(TRUE,'',array('pdc_trade_sn'=>$result['payment_no']));
            }else{
                return ds_callback(FALSE, $result['err_code_des']);
            }
        }else{
            return ds_callback(FALSE, $result['return_msg']);
        }
    }
    /**
     * 添加分账接收方
     * 参数receiver说明：
     * type：           分账接收方类型：MERCHANT_ID：商户ID、PERSONAL_OPENID：个人openid（由父商户APPID转换得到）、PERSONAL_SUB_OPENID: 个人sub_openid（由子商户APPID转换得到）
     * account：        分账接收方账号：类型是MERCHANT_ID时，是商户ID、类型是PERSONAL_OPENID时，是个人openid、类型是PERSONAL_SUB_OPENID时，是个人sub_openid
     * name：           分账接收方全称：类型是MERCHANT_ID时，是商户全称（必传）、类型是PERSONAL_OPENID时，是个人姓名（选传，传则校验）、 分账接收方类型是PERSONAL_SUB_OPENID时，是个人姓名（选传，传则校验）
     * relation_type：  与分帐方的关系类型：子商户与接收方的关系。本字段值为枚举：SERVICE_PROVIDER：服务商、STORE：门店 、STAFF：员工  、STORE_OWNER：店主、PARTNER：合作伙伴、HEADQUARTER：总部、BRAND：品牌方、DISTRIBUTOR：分销商、USER：用户、SUPPLIER：供应商、CUSTOM：自定义 
     * 
     */
    public static function addProfitSharing($receiver=array())
    {
        require_once PLUGINS_PATH . '/payments/wxpay_native/lib/WxPay.Api.php';
        
        $WxOrderData = new WxPayProfitSharing();
        $WxOrderData->SetSub_Mch_id(config('sub_mch_id')); //子商户号
        $WxOrderData->SetReceiver(json_encode($receiver)); //分账接收方信息
        
        $wxOrder = \WxPayApi::profitsharingAddReceiver($WxOrderData);
        return $wxOrder;
    }

    /**
     * 分账
     * receivers参数说明：
     * type         分账接收方类型：MERCHANT_ID：商户ID、PERSONAL_OPENID：个人openid（由父商户APPID转换得到）、PERSONAL_SUB_OPENID: 个人sub_openid（由子商户APPID转换得到）
     * account      分账接收方帐号：类型是MERCHANT_ID时，是商户ID、类型是PERSONAL_OPENID时，是个人openid、类型是PERSONAL_SUB_OPENID时，是个人sub_openid
     * amount       分账金额：分账金额，单位为分，只能为整数，不能超过原订单支付金额及最大分账比例金额
     * description  分账描述：分账的原因描述，分账账单中需要体现
     */
    public static function profitSharing($data=array())
    {
        require_once PLUGINS_PATH . '/payments/wxpay_native/lib/WxPay.Api.php';
        $WxOrderData = new WxPayProfitSharing();
        $WxOrderData->SetSub_Mch_id(config('sub_mch_id')); //子商户号
        $WxOrderData->SetTransaction_id($data['transaction_id']); //微信订单号
        $WxOrderData->SetOut_order_no($data['pay_sn']); //商户分账单号
        $WxOrderData->SetReceivers(json_encode($data['receivers'])); //分账接收方信息
        $wxOrder = \WxPayApi::profitsharing($WxOrderData);
        return $wxOrder;
    }

}
