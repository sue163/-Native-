官方文档：https://pay.weixin.qq.com/wiki/doc/api/allocation_sl.php?chapter=24_1&index=1
说明：
1、在统一下单API加入profit_sharing参数
2、添加分账接收方API
3、调用分账请求API

步骤：
1、wxpay_native.php文件get_payform方法添加：$input->SetProfit_sharing("Y");
2、wxpay_native.php文件添加：
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

3、WxPay.Api.php文件添加：
/**
	 * 添加分账接收方
	 */
public static function profitsharingAddReceiver($inputObj, $timeOut = 6)
{
    $url = "https://api.mch.weixin.qq.com/pay/profitsharingaddreceiver";

    $inputObj->SetAppid(WxPayConfig::APPID);//公众账号ID
    $inputObj->SetMch_id(WxPayConfig::MCHID);//商户号
    $inputObj->SetNonce_str(self::getNonceStr());//随机字符串

    //签名
    $inputObj->SetSign('hash_hmac');
    $xml = $inputObj->ToXml();
    $startTimeStamp = self::getMillisecond();//请求开始时间
    $response = self::postXmlCurl($xml, $url, false, $timeOut);
    $result = WxPayResults::Init($response,'hash_hmac');
    self::reportCostTime($url, $startTimeStamp, $result);//上报请求花费时间
    //$result = self::xml_to_array($response);
    return $result;
}

/**
	 * 单次分账
	 */
public static function profitsharing($inputObj, $timeOut = 6)
{
    $url = "https://api.mch.weixin.qq.com/secapi/pay/profitsharing";

    $inputObj->SetAppid(WxPayConfig::APPID);//公众账号ID
    $inputObj->SetMch_id(WxPayConfig::MCHID);//商户号
    $inputObj->SetNonce_str(self::getNonceStr());//随机字符串

    //签名
    $inputObj->SetSign('hash_hmac');
    $xml = $inputObj->ToXml();

    $startTimeStamp = self::getMillisecond();//请求开始时间
    $response = self::postXmlCurl($xml, $url, true, $timeOut);
    $result = WxPayResults::Init($response,'hash_hmac');
    self::reportCostTime($url, $startTimeStamp, $result);//上报请求花费时间
    return $result;
}

4、WxPay.Data.php文件添加：

/**
 * 
 * 分账对象
 * @author widyhu
 *
 */
class WxPayProfitSharing extends WxPayDataBase
{	
	/**
	* 设置分账接收方的公众账号ID
	* @param string $value 
	**/
	public function SetSubAppid($value)
	{
		$this->values['sub_appid'] = $value;
	}
	/**
	* 获取分账接收方的公众账号ID的值
	* @return 值
	**/
	public function GetSubAppid()
	{
		return $this->values['sub_appid'];
	}
	/**
	* 判断分账接收方的公众账号ID是否存在
	* @return true 或 false
	**/
	public function IsSubAppidSet()
	{
		return array_key_exists('sub_appid', $this->values);
	}


	/**
	* 设置分账接收方商户号
	* @param string $value 
	**/
	public function SetSub_Mch_id($value)
	{
		$this->values['sub_mch_id'] = $value;
	}
	/**
	* 获取分账接收方商户号
	* @return 值
	**/
	public function GetSub_Mch_id()
	{
		return $this->values['sub_mch_id'];
	}
	/**
	* 判断分账接收方商户号是否存在
	* @return true 或 false
	**/
	public function IsSubMch_idSet()
	{
		return array_key_exists('sub_mch_id', $this->values);
	}


	/**
	* 设置分账接收方对象
	* @param string $value 
	**/
	public function SetReceiver($value)
	{
		$this->values['receiver'] = $value;
	}
	/**
	* 获取分账接收方对象
	* @return 值
	**/
	public function GetReceiver()
	{
		return $this->values['receiver'];
	}
	/**
	* 判断分账接收方对象是否存在
	* @return true 或 false
	**/
	public function IsReceiverSet()
	{
		return array_key_exists('receiver', $this->values);
	}

	/**
	* 设置分账交易号
	* @param string $value 
	**/
	public function SetTransaction_id($value)
	{
		$this->values['transaction_id'] = $value;
	}
	/**
	* 获取分账交易号的值
	* @return 值
	**/
	public function GetTransaction_id()
	{
		return $this->values['transaction_id'];
	}
	/**
	* 判断分账交易号是否存在
	* @return true 或 false
	**/
	public function IsTransaction_idSet()
	{
		return array_key_exists('transaction_id', $this->values);
	}


	/**
	* 设置分账订单号
	* @param string $value 
	**/
	public function SetOut_order_no($value)
	{
		$this->values['out_order_no'] = $value;
	}
	/**
	* 获取分账订单号的值
	* @return 值
	**/
	public function GetOut_order_no()
	{
		return $this->values['out_order_no'];
	}
	/**
	* 判断分账订单号是否存在
	* @return true 或 false
	**/
	public function IsOut_order_noSet()
	{
		return array_key_exists('out_order_no', $this->values);
	}


	/**
	* 设置分账接收方分账金额信息
	* @param string $value 
	**/
	public function SetReceivers($value)
	{
		$this->values['receivers'] = $value;
	}
	/**
	* 获取分账接收方分账金额信息的值
	* @return 值
	**/
	public function GetReceivers()
	{
		return $this->values['receivers'];
	}
	/**
	* 判断分账接收方分账金额信息是否存在
	* @return true 或 false
	**/
	public function IsReceiversSet()
	{
		return array_key_exists('receivers', $this->values);
	}
	/**
	 * 设置分账参数
	 */
	public function SetProfit_sharing($value)
    {
        $this->values['profit_sharing'] = $value;
    }
	/**
	 * 获取分账参数
	 */
    public function GetProfit_sharing()
    {
        return $this->values['profit_sharing'];
    } 
	/**
	* 判断分账参数是否存在
	* @return true 或 false
	**/
    public function IsProfit_sharingSet()
    {
        return array_key_exists('profit_sharing', $this->values);
	}

	/**
	 * 生成签名
	 * @return 签名，本函数不覆盖sign成员变量，如要设置签名需要调用SetSign方法赋值
	 */
	/* public function MakeSignHash()
	{
		//签名步骤一：按字典序排序参数
		ksort($this->values);
		$string = $this->ToUrlParams();
		//签名步骤二：在string后加入KEY WxPayConfig::KEY
		$string = $string . "&key=".WxPayConfig::KEY;
		//签名步骤三：HMAC-SHA256加密
        $string = hash_hmac("sha256",$string,WxPayConfig::KEY);
		//签名步骤四：所有字符转为大写
		$this->values['sign'] = strtoupper($string);
		return $this->values['sign'];
	} */

	/**
	* 设置签名，详见签名生成算法
	* @param string $value 
	**/
	/* public function SetSignHash()
	{
		$sign = $this->MakeSignHash();
		$this->values['sign'] = $sign;
		return $sign;
	} */
	
	/**
	* 获取签名，详见签名生成算法的值
	* @return 值
	**/
	/* public function GetSignHash()
	{
		return $this->values['sign'];
	} */
	
	/**
	* 判断签名，详见签名生成算法是否存在
	* @return true 或 false
	**/
	/* public function IsSignHashSet()
	{
		return array_key_exists('sign', $this->values);
	} */

	/**
	* 设置随机字符串，不长于32位。推荐随机数生成算法
	* @param string $value 
	**/
	public function SetNonce_str($value)
	{
		$this->values['nonce_str'] = $value;
	}
	/**
	* 获取随机字符串，不长于32位。推荐随机数生成算法的值
	* @return 值
	**/
	public function GetNonce_str()
	{
		return $this->values['nonce_str'];
	}
	/**
	* 判断随机字符串，不长于32位。推荐随机数生成算法是否存在
	* @return true 或 false
	**/
	public function IsNonce_strSet()
	{
		return array_key_exists('nonce_str', $this->values);
	}

	/**
	* 设置微信支付分配的商户号
	* @param string $value 
	**/
	public function SetMch_id($value)
	{
		$this->values['mch_id'] = $value;
	}
	/**
	* 获取微信支付分配的商户号的值
	* @return 值
	**/
	public function GetMch_id()
	{
		return $this->values['mch_id'];
	}
	/**
	* 判断微信支付分配的商户号是否存在
	* @return true 或 false
	**/
	public function IsMch_idSet()
	{
		return array_key_exists('mch_id', $this->values);
	}

	/**
	* 设置微信分配的公众账号ID
	* @param string $value 
	**/
	public function SetAppid($value)
	{
		$this->values['appid'] = $value;
	}
	/**
	* 获取微信分配的公众账号ID的值
	* @return 值
	**/
	public function GetAppid()
	{
		return $this->values['appid'];
	}
	/**
	* 判断微信分配的公众账号ID是否存在
	* @return true 或 false
	**/
	public function IsAppidSet()
	{
		return array_key_exists('appid', $this->values);
	}

}

5、WxPay.Data.php签名相关接口修改：
    /**
	* 设置签名，详见签名生成算法
	* @param string $value 
	**/
	public function SetSign($type='md5')
	{
		$sign = $this->MakeSign($type);
		$this->values['sign'] = $sign;
		return $sign;
	}
    /**
	 * 生成签名
	 * @return 签名，本函数不覆盖sign成员变量，如要设置签名需要调用SetSign方法赋值
	 */
	public function MakeSign($type='md5')
	{
		//签名步骤一：按字典序排序参数
		ksort($this->values);
		$string = $this->ToUrlParams();
		//签名步骤二：在string后加入KEY
		$string = $string . "&key=".WxPayConfig::KEY;
		//签名步骤三：
		if($type == 'md5'){ //MD5加密
			$string = md5($string);
		}else{ //hash_hmac加密
			$string = hash_hmac("sha256",$string,WxPayConfig::KEY);
		}
		//签名步骤四：所有字符转为大写
		$result = strtoupper($string);
		return $result;
	}
    /**
	 * 
	 * 检测签名md5
	 */
	public function CheckSign($type='md5')
	{
		//fix异常
		if(!$this->IsSignSet()){
			throw new WxPayException("签名错误！");
		}
		
		$sign = $this->MakeSign($type);
		if($this->GetSign() == $sign){
			return true;
		}
		throw new WxPayException("签名错误！");
	}
    /**
     * 将xml转为array
     * @param string $xml
     * @throws WxPayException
     */
	public static function Init($xml,$type='md5')
	{	
		$obj = new self();
		$obj->FromXml($xml);
		if($obj->values['return_code'] != 'SUCCESS'){
			 return $obj->GetValues();
		}
		$obj->CheckSign($type);
        return $obj->GetValues();
	}
