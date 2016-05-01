<?php
define("TOKEN", "weiphp");
define("DATABASE_DOMAIN","w.rdc.sae.sina.com.cn:3307");
define("MYNAME","summerlittleleo");
define("WELCOME","哇关注我的你一定很帅！/::$ \n如果你再帮我扩一下简直就帅炸了都快赶上我了\n关注我不能让你更有文化\n但是颜值可以更高啊！\n至于推送内容 不定期更新 木有主题 好吧我比较随性~~~\n【以上风骚的内容绝对不是我写的】");
define("DEFAULTWEL","○有两个游戏可以玩了哦，回复\n  ●游戏1  ●游戏2\n反正都很无聊~~\n○主页猫实在是太懒了，有什么话就直接说吧~~~不一定回哦");
define("GAME1OPTION", "请选择\n●攻击  ●防守  ●背包\n●商店  ●退出");
define("SPLITLINE", "\n———————————\n");
define("StoreOption", "春节特惠\n●1.血瓶     价格：1猫粮\n请输入要购买商品的序号或退出");
define("LIFEPLUS", 200);//血瓶的回血量
define("BagOption", "请输入序号或者关闭");//背包的选项
define("DAttackMax", 250);
define("DAttackMin", 100);
define("SAttackMax", 200);
define("SAttackMin", 110);
define("COUNT", 8);

//连接数据库
ADO::Connect();

/*调试模式
if(empty($_GET))
{
	echo "hahahahahahaha";
}
else
{
	//调试选项
	if (isset($_GET['args']))
	{
		//ADO::Add($_GET['args']);
		
		echo ADO::Exist($_GET['args']);
		
	}
	//首次连接验证
	if (isset($_GET['echostr']))
	{
		$checker = new WeiChatCheck();
		$checker->valid();
	}
}
*/
$wechatObj = new WeiChatService();
$wechatObj->responseMsg();//入口

//程序入口
class WeiChatService
{
	
    public function responseMsg()
    {

		//get post data, May be due to the different environments
		$postStr = $GLOBALS["HTTP_RAW_POST_DATA"];

      	//extract post data
		if (!empty($postStr)){
                /* libxml_disable_entity_loader is to prevent XML eXternal Entity Injection,
                   the best way is to check the validity of xml by yourself */
                libxml_disable_entity_loader(true);
              	$postObj = simplexml_load_string($postStr, 'SimpleXMLElement', LIBXML_NOCDATA);
                
				//获取用户名
				$fromUsername = $postObj->FromUserName;
                $toUsername = $postObj->ToUserName;
				
				//创建数据库记录
				if(!ADO::Exist($fromUsername))
				{
					ADO::Add($fromUsername);
				}
				
				
				$msgHandler = new MessageHandler($fromUsername);
				
				//分流
				switch($postObj->MsgType)
				{
					//事件消息
					case "event":
						$resultStr = $msgHandler->EventResponse($postObj);
						break;
					//普通文字消息
					case "text":
						$text = trim($postObj->Content);
						$resultStr = $msgHandler->TextResponse($text);
						break;
					case "image":
						$resultStr = $msgHandler->GenTextMessage("不要发图片好咩");
						break;
					//其它消息
					default:
						$resultStr = $msgHandler->GenTextMessage("说点什么好呢？😳");
				}
				
				//发送
				echo $resultStr;

        }else {
        	echo "";
        	exit;
        }
    }
}

//首次连接验证
class WeiChatCheck
{
	public function valid()
    {
        $echoStr = $_GET["echostr"];
		//echo $echoStr;
		
        //valid signature , option
        if($this->checkSignature()){
        	echo $echoStr;
        	exit;
        }
    }
	
	private function checkSignature()
	{
        // you must define TOKEN by yourself
        if (!defined("TOKEN")) {
            throw new Exception('TOKEN is not defined!');
        }
        
        $signature = $_GET["signature"];
        $timestamp = $_GET["timestamp"];
        $nonce = $_GET["nonce"];
        		
		$token = TOKEN;
		$tmpArr = array($token, $timestamp, $nonce);
        // use SORT_STRING rule
		sort($tmpArr, SORT_STRING);
		$tmpStr = implode( $tmpArr );
		$tmpStr = sha1( $tmpStr );
		
		if( $tmpStr == $signature ){
			return true;
		}else{
			return false;
		}
	}
}

//统一的消息处理程序
class MessageHandler
{
	private $username;
	
	public function __construct($fromUsername)
	{
		$this->username = $fromUsername;
	}
	
	//生成文字消息
	public function GenTextMessage($text)
	{
		//文字消息回复模板
		$textTpl = "<xml>
							<ToUserName><![CDATA[%s]]></ToUserName>
							<FromUserName><![CDATA[%s]]></FromUserName>
							<CreateTime>%s</CreateTime>
							<MsgType><![CDATA[text]]></MsgType>
							<Content><![CDATA[%s]]></Content>
							<FuncFlag>0</FuncFlag>
							</xml>";	
		return sprintf($textTpl, $this->username, MYNAME , time(), $text);					
	}
	
	public function TextResponse($text)
	{
		$reply = "";
		$Mode = ADO::getMode($this->username);
		switch($Mode)                                                 /*所有文字消息处理入口*/
		{                                                             /*Mode 0 :自由交谈  Mode 1 游戏1  Mode 2 游戏2*/	                                                                
			case 1:
				$game = new Game($this->username);
				$reply = $game->Play($text);                               
				break;
			case 0:
				$talk = new FreeTalk();
				$reply = $talk->getContent($text,$this->username);
				break;
			case 2:
				$game2 = new Game2($this->username);
				$reply = $game2->Play($text); 
				break;
		}
		switch($reply[0])
		{
			case 0:
				if($reply[1]=="") $reply[1]="说点什么好呢？😳";

				//保存用户聊天记录
				//include 'io.php';
				//IO::Write($this->username,$text,$reply[1]);

				$result = $this->GenTextMessage($reply[1]);
				break;
			case 1:
				$result = $this->GenPicMessage($reply[1]);
				break;
		}		
		return $result;
	}
	public function EventResponse($postObj)
	{
		$reply = "";
		$EventType = $postObj->Event;
		//是否为点击事件
		if($EventType=="CLICK")
		{
			$EventKey = $postObj->EventKey;
			//是否为游戏1
			if($EventKey=="game1")                             /*所有按钮处理入口*/
			{
				$game = new Game($this->username);
				$reply = $game->Welcome();
				ADO::setMode($this->username,1);
			}
			
		}
		else if($EventType=="subscribe")
		{
			$reply = WELCOME."\n\n".DEFAULTWEL;                    /*关注事件处理*/
		}
		
		
		$result = $this->GenTextMessage($reply);
		return $result;
	}

	//生成图片消息
	public function GenPicMessage($MediaId)
	{
		$picTpl = "<xml>
			<ToUserName><![CDATA[%s]]></ToUserName>
			<FromUserName><![CDATA[%s]]></FromUserName>
			<CreateTime>%s</CreateTime>
			<MsgType><![CDATA[image]]></MsgType>
			<Image>
				<MediaId><![CDATA[%s]]></MediaId>
			</Image>
			</xml>";
		return sprintf($picTpl, $this->username, MYNAME , time(), $MediaId);
	}
	
}

//自由交谈模式
class FreeTalk
{
	public function getContent($text,$username)
	{
		$result = "";
		if(strpos($text,":") === 0||strpos($text,"：") === 0)
		{
			$result = "嗯";
		}
		else if(stristr($text,"在吗")!="")
		{
			$result = "不在~";
		}
		else if(stristr($text,"佳蕾")!=""||$text=="夏天的大号猫")
		{
			if((stristr($text,"傻")!=""||stristr($text,"sa")!="")&&stristr($text,"不")=="")
			{
				$result = "你才傻呢，哼~";
			}
			else if (stristr($text,"傻不傻")!="") 
			{
				$result = "不傻";
			}
			else
			{
				$result = "喵~";
			}
		}
		else if(stristr($text,"爱你")!="")
		{
			$result = "哦(´-ω-`)";
		}
		else if($text == "游戏1"||$text == "玩游戏1"||$text == "玩游戏")                                   /*回复命中关键词入口*/
		{
			$game = new Game($username);
			$result = $game->Welcome();
			ADO::setMode($username,1);
		}
		else if($text == "游戏2"||$text == "玩游戏2")
		{
			$game = new Game2($username);
			$result = $game->Welcome();
			ADO::setMode($username,2);
		}
		else if($text == "游戏3"||$text == "玩游戏3")
		{
			$result = "敬请期待...";
		}
		else if(stristr($text,"王渝")!="")
		{
			$result = "叫我干嘛？";
			if((stristr($text,"傻")!=""||stristr($text,"sa")!="")&&stristr($text,"不")=="")
				$result = "😒";
		}
		else if(stristr($text,"王瑜")!="")
		{
			$result = "你把我名字打错了！";
		}
		else if((stristr($text,"傻")!=""||stristr($text,"sa")!="")&&stristr($text,"不")=="")
		{
			$result = "你才傻呢，哼~";
		}
		else if($text == "你好"||$text == "hi"||$text == "hello")
		{
			$result = "你好😄";
		}
		else
		{
			$result = DEFAULTWEL;
			//$result = "说点什么好呢？";
		}
		
		
		return array(0,$result);
	}
}

//问题模式
class Question
{
	public function getContent($text,$username)
	{
		$result = "收到你的答案了哦";
		return $result;
	}
}

//猜数游戏
class Game2
{
	private $username;
	//private $cache;
	//private $_key;
	private $num;
	private $count;
	
	public function __construct($fromUsername)
	{
		$this->username = $fromUsername;
		//$this->cache = new Cache();
		//$this->_key = $this->username."game2";
		if(($this->num=$this->getNum())==0)
		{
			$t = rand(1,1000);
			$this->num = $t;
			$this->setNum($t);
		}
		$this->getCount();
	}
	
	public function Welcome()
	{
		return "来玩一个猜数游戏，范围是1到1000，规则你懂的。。。。如果在".COUNT."次内猜中奖励猫粮一粒\n输入“退出”结束游戏";
	}
	public function Play($text)
	{
		$re = "";
		if(stristr($text,"退出")!="")
		{
			if(stristr($text,"不")!="") return "";
			ADO::setMode($this->username,0);
			$re = "○退出游戏".SPLITLINE.DEFAULTWEL;
			$this->Clear();
		}
		else if($text == "玩游戏1"||$text == "游戏1"||$text == "玩游戏")
		{
			$re = "先要退出才能玩哦😜";
		}
		else if(!is_numeric($text))
		{
			$re = "😒";				
		}
		else
		{
			if($text>1000||$text<1) return array(0,"😒");
			$this->count--;
			$this->saveCount();
			if($text==$this->num)
			{
				$re = "对了！👏👏👏";
				ADO::setMode($this->username,0);
				if ($this->count>=0) {
					$re = sprintf("对了！,用了%d次,奖励一粒猫粮👏👏👏",COUNT-$this->count);
					ADO::Update($this->username,"game_money",ADO::Find($this->username,"game_money")+1);
				}
				$this->Clear();
			}
			else if($text>$this->num)
			{
				$re = "高了";
			}
			else
			{
				$re = "低了";
			}
		}
		return array(0,$re);
	}
	
	public function getNum()
	{
		return ADO::Find($this->username,"game2");
	}

	public function setNum($num)
	{
		ADO::Update($this->username,"game2",$num);
	}

	public function getCount()
	{
		$this->count = ADO::Find($this->username,"game2_count");
	}

	public function saveCount()
	{
		ADO::Update($this->username,"game2_count",$this->count);
	}

	private function Clear()
	{
		ADO::Update($this->username,"game2",0);
		ADO::Update($this->username,"game2_count",COUNT);
	}

}

//大魔王游戏
class Game                                                         /* 返回数组（数字，内容/MediaId）0:文字 1:图片   */
{
	private $username;
	//private $cache;
	//private $_key;

	private $DHP;
	private $SHP;
	private $bottle;
	private $money;
	private $mode;
	private $store_mode;
	private $bag_mode;
	
	public function __construct($fromUsername)
	{
		$this->username = $fromUsername;
		//$this->cache = new Cache();
		//$this->_key = $this->username."game1";

		//从数据库读取数据
		$this->getData();
		$this->getMode();
		$this->getStoreMode();
		$this->getBagMode();
	}
	
	public function Welcome()
	{
		return "大魔王出现👿，快攻击他！（听说有1000滴血）".SPLITLINE.GAME1OPTION;
	}
	
	public function Ex()
	{
		$re = "";
		if(stristr($text,"不")!="") return "";
		ADO::setMode($this->username,0);
		$re = "○退出游戏".SPLITLINE.DEFAULTWEL;
		$this->Clear();
		return $re;
	}

	public function Attack()
	{
		$re = "";
		$attackd = rand(SAttackMin,SAttackMax);
		$attacks = rand(DAttackMin,DAttackMax);
		$this->DHP-=$attackd;
		$this->SHP-=$attacks;
		if($this->DHP<=0)
		{
			ADO::setMode($this->username,0);
			$re = "击败了大魔王！\n👏👏👏🎉🎉🎉/:handclap/:handclap/:handclap";
			$this->Clear();
		}
		else if($this->SHP<=0)
		{
			ADO::setMode($this->username,0);
			$re = "大魔王击败了你";
			$this->Clear();
		}
		else
		{
			$this->setData();
			$re = sprintf("○对大魔王造成伤害%d点伤害\n○大魔王对你造成伤害%d点伤害\n○大魔王还剩%d滴血\n○你还剩%d滴血".SPLITLINE.GAME1OPTION,$attackd,$attacks,$this->DHP,$this->SHP);
		}
		return $re;
	}

	public function Defend()
	{
		$re = "";
		$attacks = rand(1,10);
		$this->SHP-=$attacks;
		if($this->SHP<=0)
		{
			ADO::setMode($this->username,0);
			$re = "大魔王击败了你";
			$this->Clear();
		}
		else
		{
			$this->setData();
			$re = sprintf("○大魔王对你造成伤害%d点伤害\n○大魔王还剩%d滴血\n○你还剩%d滴血".SPLITLINE.GAME1OPTION,$attacks,$this->DHP,$this->SHP);
		}	
		return $re;	
	}

	public function BagWel()
	{
		$re = "";
		$re = sprintf("背包里有如下东西\n1.血瓶   数量:%d\n2.猫粮   数量:%d".SPLITLINE.BagOption,$this->bottle,$this->money);
		return $re;
	}

	private function Bag($text)
	{
		$re = "";
		switch ($this->bag_mode) {
			case 0:
				switch ($text) {
					case "1":
						$this->setBagMode(1);
						$re = "数量：";
						break;
					case "2":
						$re = "猫粮是用来购买商品的~~~".SPLITLINE.sprintf("背包里有如下东西\n1.血瓶   数量:%d\n2.猫粮   数量:%d".SPLITLINE.BagOption,$this->bottle,$this->money).BagOption;
						break;
					case "关闭":
						$re = "○关闭背包".SPLITLINE.GAME1OPTION;
						$this->setMode(0);
						break;
					default:
						$re = "额......😒";
						break;
				}
				break;
			case 1:
				if($text=="关闭")
				{
					$re = "○关闭背包".SPLITLINE.GAME1OPTION;
					$this->setBagMode(0);
					$this->setMode(0);
				}
				else if(is_numeric($text)&&$text>0)
				{
					$mbottle = $this->bottle - $text;
					if ($mbottle>=0) 
					{
						$this->SHP+=LIFEPLUS*$text;
						$re = sprintf("○恢复了%d点血，你还有%d血".SPLITLINE,LIFEPLUS*$text,$this->SHP);
						$this->bottle = $mbottle;
					}
					else
					{
						$re = "没有足够血瓶了".SPLITLINE;
					}
					$re = $re.sprintf("背包里有如下东西\n1.血瓶   数量:%d\n2.猫粮   数量:%d".SPLITLINE.BagOption,$this->bottle,$this->money);
				}
				$this->setBagMode(0);
				break;
		}
		$this->setData();
		return $re;
	}

	private function StoreWel()
	{
		$re = "";
		$re = StoreOption;	
		//$this->setMode(0);
		return $re;
	}
	
	private function Store($text)
	{		
		$re = "";
		//$this->getStoreMode();
		switch ($this->store_mode) 
		{
			case 0:
				switch ($text) {
					case "1":
						$this->setStoreMode(1);
						$re = "数量：";
						break;
					case '退出':
						$re = "○退出商店".SPLITLINE.GAME1OPTION;
						$this->setStoreMode(0);
						$this->setMode(0);
						break;
					default:
						$re = "额......😒";
						break;
				}
				break;
			case 1:
				if($text=="退出")
				{
					$re = "○退出商店".SPLITLINE.GAME1OPTION;
					$this->setStoreMode(0);
					$this->setMode(0);
				}
				else if(is_numeric($text)&&$text>0)
				{
					$money2 = $this->money - 1*$text;
					if ($money2>=0) 
					{
						$re = "购买了".$text."个血瓶\n还剩".$money2."猫粮".SPLITLINE.StoreOption;
						$this->setStoreMode(0);
						$this->bottle+=$text;
						$this->money = $money2;
					}
					else
					{
						$re = "猫粮不足".SPLITLINE.StoreOption;
						$this->setStoreMode(0);
					}
				}
				else
				{
					$re = "😒";
				}
				break;
	
		}
		$this->setData();
		return $re;
	}

	public function Play($text)
	{
		$re = "";
		//$this->getMode();
		//echo $this->mode;
		//echo "\n";
		$goodwords = array("美丽","漂亮","可爱","好","膜");
		switch ($this->mode) 
		{
			case 0:
				if(stristr($text,"退出")!="")
				{
					$re = $this->Ex();
				}
				else if($text == "什么好"||$text == "点什么好")
				{
					$re = "我不知道说你什么好😒";
				}
				else if(stristr($text,"攻击")!="")
				{
					$re = $this->Attack();
				}
				else if(stristr($text,"防守")!="")
				{
					$re = $this->Defend();
				}
				else if(stristr($text,"背包")!="")
				{
					$this->setMode(2);
					$re = $this->BagWel();
				}
				else if (stristr($text,"商店")!="") 
				{
					$this->setMode(1);
					$re = $this->StoreWel();
				}
				else if(stristr($text,"膜沧")!=""&&stristr($text,"不")=="")
				{
					$this->SHP+=200;
					$this->setData();
					$re = sprintf("○恢复了%d点血，你还有%d血".SPLITLINE.GAME1OPTION,LIFEPLUS,$this->SHP);
				}
				else if((stristr($text,"膜王渝")!=""||stristr($text,"膜王神")!=""||stristr($text,"膜王大神")!="")&&stristr($text,"不")=="")
				{
					$this->SHP+=200;
					$this->setData();
					$re = sprintf("○恢复了%d点血，你还有%d血".SPLITLINE.GAME1OPTION,LIFEPLUS,$this->SHP);
				}
				else if(stristr($text,"佳蕾")!=""&&stristr($text,"不")=="")
				{
					$flag;
					foreach ($goodwords as $value)
					{
						if (stristr($text,$value)!="") 
						{
							$flag = true;
							break;
						}
					}
					if (flag)
					{
						$this->SHP+=200;
						$this->setData();
						$re = sprintf("○恢复了%d点血，你还有%d血".SPLITLINE.GAME1OPTION,LIFEPLUS,$this->SHP);
					}
					else
					{
						$re = "额......😒";
					}
				}
				else if($text == "玩游戏2"||$text == "游戏2"||$text == "玩游戏")
				{
					$re = "先要退出才能玩哦😜";
				}
				else
				{
					$re = "额......😒";
				}
				break;
			case 1:
				$re = $this->Store($text);
				break;
			case 2:
				//背包
				$re = $this->Bag($text);
				break;
		}
		return array(0,$re);
	}

	private function getMode()
	{
		//return $this->cache->Get($this->_key."mode");	
		//echo ADO::Find($this->username,"game_mode");
		$this->mode = ADO::Find($this->username,"game_mode");
	}

	private function setMode($mode)
	{
		//$this->cache->Set($this->_key."mode",$mode);
		ADO::Update($this->username,"game_mode",$mode);
	}

	private function getStoreMode()
	{
		$this->store_mode = ADO::Find($this->username,"game_store_mode");
	}

	private function setStoreMode($store_mode)
	{
		ADO::Update($this->username,"game_store_mode",$store_mode);
	}

	private function getBagMode()
	{
		$this->bag_mode = ADO::Find($this->username,"game_bag_mode");
	}

	private function setBagMode($bag_mode)
	{
		ADO::Update($this->username,"game_bag_mode",$bag_mode);
	}

	private function isFirstRun()
	{
		$r;
		if (ADO::Find($this->username,"game")==0) 
		{
			$r = true;
		}
		else
		{
			$r = false;
		}
		return $r;
	}

	private function getData()
	{
		$this->DHP = ADO::Find($this->username,"game_dhp");
		$this->SHP = ADO::Find($this->username,"game_shp");
		$this->bottle = ADO::Find($this->username,"game_bottle");
		$this->money = ADO::Find($this->username,"game_money");
	}

	/*
	private function setData($DHP,$SHP,$bottle,$money)
	{
		$this->cache->Set($this->_key,array($DHP,$SHP,$bottle,$money));
	}
	*/

	private function initData()
	{
		//$this->cache->Set($this->_key,array(1000,1000,1,10));
	}

	private function setData()
	{
		//$this->cache->Set($this->_key,array($this->DHP,$this->SHP,$this->bottle,$this->money));
		ADO::Update($this->username,"game_dhp",$this->DHP);
		ADO::Update($this->username,"game_shp",$this->SHP);
		ADO::Update($this->username,"game_bottle",$this->bottle);
		ADO::Update($this->username,"game_money",$this->money);
	}

	private function Clear()
	{
		//$this->cache->Del($this->_key."mode");
		//$this->cache->Del($this->_key);
		ADO::Update($this->username,"game_dhp",1000);
		ADO::Update($this->username,"game_shp",1000);
		ADO::Update($this->username,"game_bottle",1);
		$this->setMode(0);
	}
	
}

//数据库方法类
class ADO
{
	
	private static $db_connect;
	
	public static function Connect()
	{
		self::$db_connect = mysqli_connect(SAE_MYSQL_HOST_M,SAE_MYSQL_USER,SAE_MYSQL_PASS,SAE_MYSQL_DB,SAE_MYSQL_PORT) or die("Unable to connect to the MySQL!");
		//mysqli_select_db(SAE_MYSQL_DB,$db_connect);
	}
	
	public static function Add($username)
	{		
		mysqli_query(self::$db_connect,sprintf("INSERT INTO weixin (userid) VALUES ('%s')",$username));
	}

	public static function Query($sql)
	{
		mysqli_query(self::$db_connect,$sql);
	}

	public static function QueryForResult($sql)
	{
		return mysqli_query(self::$db_connect,$sql);
	}

	public static function Find($username,$column)
	{
		$result = mysqli_query(self::$db_connect,sprintf("SELECT %s FROM weixin WHERE userid = '%s'",$column,$username));
		$row = mysqli_fetch_array($result);
		return $row[$column];
	}

	public static function Exist($username)
	{
		$result = mysqli_query(self::$db_connect,sprintf("SELECT userid FROM weixin WHERE userid = '%s'",$username));
		$num = mysqli_num_rows($result);
		if($num==0)
		{
			return false;
		}
		else
		{
			return true;
		}
	}
	
	public static function getMode($username)
	{
		$result = mysqli_query(self::$db_connect,sprintf("SELECT mode FROM weixin WHERE userid = '%s'",$username));
		$row = mysqli_fetch_array($result);
		return $row['mode'];
	}

	public static function setMode($username,$mode)
	{
		mysqli_query(self::$db_connect,sprintf("UPDATE weixin SET mode = %d WHERE userid = '%s'",$mode,$username));
	}



	public static function Update($username,$column,$numvalue)
	{
		mysqli_query(self::$db_connect,sprintf("UPDATE weixin SET %s = %d WHERE userid = '%s'",$column,$numvalue,$username));
	}

	public static function Close()
	{
		mysqli_close();
	}
	
}

/*memcache收费
//高速缓存
class Cache
{
	private $mmc;
	
	public function __construct()
	{
		$this->mmc = memcache_connect();      
		if ($this->mmc == false) echo "mc init failed\n";		
	}
	
	public function Set($key,$value)
	{
		memcache_set($this->mmc, $key, $value);
	}
	public function Get($key)
	{
		return memcache_get($this->mmc, $key);
	}
	public function Del($key)
	{
		memcache_delete($this->mmc, $key);
	}
}
*/

?>