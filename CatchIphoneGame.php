<?php
/**
 * 抓取itunes上的手机游戏信息
 *
 * @author huangjialin
 * @version 1.0
 * @notice   
 *  
 *  php index.php --c=Tools_CatchIphoneGame
 *  php index.php --c=Tools_CatchIphoneGame 	--a=GetCatLink
 *  php index.php --c=Tools_CatchIphoneGame	--a=GetDetailLink
 */
 
//ini_set ('display_errors',1);
//error_reporting(E_ALL);   
set_time_limit(1800000000) ;

include  "./FetchHtml/SimpleHtmlDom.php";
class Tools_CatchIphoneGame extends Page_Abstract {
	
	//超时时间
	private  $timeout = 5000;
	
	//存放分类链接的文件
	private  $catdatafilename = "iphonegamecatlist.txt";

	private  $proRoot = "/home/huangjialin/catch";
	
	
	//分类对应关系
	private $data = array(
				/*角色扮演*/
				'https://itunes.apple.com/cn/genre/ios-you-xi-jiao-se-ban-yan-you-xi/id7014?mt=8'=>1,
				/*休闲益智*/
				'https://itunes.apple.com/cn/genre/ios-you-xi-zhuo-mian-you-xi/id7004?mt=8'=>2,
				'https://itunes.apple.com/cn/genre/ios-you-xi-pu-ke-pai-you-xi/id7005?mt=8'=>2,
				'https://itunes.apple.com/cn/genre/ios-you-xi-yu-le-chang-you-xi/id7006?mt=8'=>2,
				'https://itunes.apple.com/cn/genre/ios-you-xi-tou-zi-you-xi/id7007?mt=8'=>2,
				'https://itunes.apple.com/cn/genre/ios-you-xi-jiao-yu-you-xi/id7008?mt=8'=>2,
				'https://itunes.apple.com/cn/genre/ios-you-xi-jia-ting-you-xi/id7009?mt=8'=>2,
				'https://itunes.apple.com/cn/genre/ios-you-xi-zhi-li-you-xi/id7012?mt=8'=>2,
				'https://itunes.apple.com/cn/genre/ios-you-xi-xiao-you-xi/id7018?mt=8'=>2,
				'https://itunes.apple.com/cn/genre/ios-you-xi-wen-zi-you-xi/id7019?mt=8'=>2,
				/*体育运动*/
				'https://itunes.apple.com/cn/genre/ios-you-xi-ti-yu/id7016?mt=8'=>4,
				/*模拟经营*/
				'https://itunes.apple.com/cn/genre/ios-you-xi-mo-ni-you-xi/id7015?mt=8'=>5,
				/*策略战棋*/
				'https://itunes.apple.com/cn/genre/ios-you-xi-ce-e-you-xi/id7017?mt=8'=>7,
				/*赛车竞速*/
				'https://itunes.apple.com/cn/genre/ios-you-xi-sai-che-you-xi/id7013?mt=8'=>13,
				/*动作闯关*/
				'https://itunes.apple.com/cn/genre/ios-you-xi-dong-zuo-you-xi/id7001?mt=8'=>18,
				'https://itunes.apple.com/cn/genre/ios-you-xi-tan-xian-you-xi/id7002?mt=8'=>18,
				'https://itunes.apple.com/cn/genre/ios-you-xi-jie-ji-you-xi/id7003?mt=8'=>18,
				/*音乐舞蹈*/
				'https://itunes.apple.com/cn/genre/ios-you-xi-yin-le/id7011?mt=8'=>30
			
	);
			
	public function validate(NB_Request $input, NB_Response $output) {
	
		if (!parent::baseValidate($input, $output)) {
			return false;
		}
		return true;
	}
 	
	public function doDefault(NB_Request $input, NB_Response $output) {
		
		$list = file("{$this->proRoot}/Html/data/000eb6587b1789cb64fa1a9bac0ac670.txt");
		 
		$db = Db_YouXiKu::instance();
		
		#获取代理服务器
		$useIpArr = array();
		for ($i=0;$i<10;$i++){
			$useIpArr[] =   NB_Api::run("Service.FetchHtml.getProxyData" , array('retryCnt' => 5));
		}
		
		if($list){
			$catid = (int)array_pop($list);
			 
			foreach ($list  as $deLink){
						$html = $this->curlPost($deLink,$useIpArr,$postdata='',$timeout=$this->timeout);
						if(!$html){
							 continue;
						}
					
						$gameInfo = new simple_html_dom();
						$gameInfo->load($html);
						
						#游戏名称
						$name 		= $gameInfo->find("h1",0)->plaintext;
						
						#存在，不重复记录
						 
						$isExistSql = "select * from yxk_sj_game where name='{$name}'";
					 
						if($db->getRow($isExistSql)){
							echo "game".iconv('utf-8', 'gbk', $name)."Exist!\n";
							continue;
						}else{
							echo "game".iconv('utf-8', 'gbk', $name)." not Exist!\n";
							 
						}
						continue;
						#公司名称
						$cpnameInfo 	= $gameInfo->find("h2",1)->plaintext;
						$cpname			=  str_replace("开发商：", '', $cpnameInfo);
						 
						$firstLetter = $this->getFirstLetter($name);
						
					 
						#游戏介绍
						$jieshao 	= $gameInfo->find("div[class=product-review]",0)->children(1)->plaintext;
						
						$jieshao   =  htmlspecialchars(strip_tags($jieshao));
						
						 
						#价格
						$priceInfo	= $gameInfo->find('div[class=price]',0)->plaintext;
						$payType = 3;
					   
						if( $priceInfo =='免费'){
							$payType =4 ;
						}else{
							$payType =3 ;
						}
						#发行时间
						$timeInfoData 	= $gameInfo->find('li[class=release-date]',0)->plaintext;
						
						$timeInfo = str_replace(array("更新日期:  ","发布于:  ","日"), '', $timeInfoData); 
						$timeInfo = str_replace(array("年","月"), '-', $timeInfo);
		
						
					 	$time = strtotime($timeInfo);
				 		 
						#游戏封面
						$guidePicBox = $gameInfo->find('#left-stack div[class=product] div[class=artwork]',0);
						$guideImg = $guidePicBox->innertext;
					 
						$guidePic = mb_substr($guideImg,strpos($guideImg,'src-swap="')+10,strpos($guideImg,'" class="artwork"')-(strpos($guideImg,'src-swap="')+10));;
				 		 
						#软件平台
						$softPlatformInfo = $gameInfo->find('#left-stack div[class=product] p',0);
						$softPlatform = $softPlatformInfo->plaintext;
						$isIos = 0;
						$isIosHD = 0;
						//iPad
						 if(strpos($softPlatform, "iPad")!==false){
						 	$isIosHD = 1;
						 }
						 //iPhone、iPod touch
						 if(strpos($softPlatform, "iPhone")!==false||strpos($softPlatform, "iPod")!==false){
						 	$isIos = 1;
						 }
						 
						#游戏截图
						$cutPicList = array();
						foreach ( $gameInfo->find('div[class=iphone-screen-shots] div div[class=lockup]') as $cutpiccontent){
								$cutPicList[] = 	$cutpiccontent->children(0)->src;
						}
						
						#下载地址
						$downUrl =$deLink;
						$creatTime = time();

					 	$sql = "insert into  yxk_gameinfo (id,name,keywords,abbreviation,payType,developer,pubTime,";
					 	$sql.= "hardPlatform,category,status,is_catch_from_itunes,firstLetter,createBy,createTime,pictureQuality) ";
					 	$sql.= "values (null,'{$name}','{$name}','{$name}','{$payType}','{$cpname}','{$time}',2,'{$catid}',0,1,'{$firstLetter}','catchiTunes','{$creatTime}',3)";
					 	$gameid = 0;
					 
					  	 if($db->query($sql)){
					 		$gameid = $db->lastInsertId();
					 		$sqlIntro = "insert into yxk_intro (game_id,intro) values ('{$gameid}','{$jieshao}')";
					 		if($gameid && !empty($jieshao)){
					 			if($db->query($sqlIntro)){
					 				echo  "success ~~";
					 			}
					 		}
					 	} 
					 	
					 	if($gameid){
					 	 
					 		/**
					 		 记录游戏截图地址
					 		 */
					 		if(!empty($cutPicList)){
					 			foreach ($cutPicList as $pic){
					 				$params = array(
					 						'picurl' => $pic,
					 						'savePath' 	 => $this->proRoot.'/Html/Admin/upload/youxiku/gamePic/',
					 						'picSize' 	 => 'original',
					 				);
					 				$picId  = Helper_Admin_YXK_Pic::uploadPictureCatch($params);
					 				//图片上传成功 把生成图片id添加到数据库中
					 				if($picId){
					 					$createTime = time();
					 					$createBy   = "iTunes";
					 					$sql        = "INSERT INTO `yxk_pic_test`(`picId`,`gameId`,`category`,`createTime`,`createBy`) 
					    							VALUES('{$picId}','{$gameid}',3,'{$createTime}','{$createBy}') ";
					 					$db->query($sql);
					 				}
					 			}
					 		}
					 		
					 		
					 		/**
					 		 上传封面图
					 		 */
					 		$params = array(
					 				'picurl' => $guidePic,
					 				'savePath' => $this->proRoot.'/Html/Admin/upload/youxiku/gameCover/',
					 				'picSize' => 'original',
					 		);
					 		//获得图片上传的id
					 		$picId = Helper_Admin_YXK_Pic::uploadGameCoverCatch($params);
					 		if($picId){
					 			$sql  = "update yxk_gameinfo set cover='{$picId}' where id='{$gameid}'";
					 			$db->query($sql);
					 		}
					 		
					 		/**
					 		 * 记录游戏iTunes上游戏综述页
					 		 */
					 	 	if(!empty($downUrl)){
					 	 		$sql  = "INSERT INTO `ab_downloadresource_catch`(`gameId`,`resourceLink`)
					 	 		VALUES('{$gameid}','{$downUrl}') ";
					 	 		$db->query($sql);
					 	 	}
					 		
					 	 	/**
					 	 	 * 软件平台关联
					 	 	 */
					 	 	if($isIos==1){
					 	 		$sql="insert  into yxk_game_to_softplatform(game_id,soft_id)values('{$gameid}',4)";
					 	 		$db->query($sql);
					 	 	}
					 	 	if($isIosHD==1){
					 	 		$sql="insert  into yxk_game_to_softplatform(game_id,soft_id)values('{$gameid}',20)";
					 	 		$db->query($sql);
					 	 	}
					 	 
					 		
					 	}
					 	sleep(10);
			}
			
		}else{
			echo  "oh  no~ ";
		}
		exit;
	}
	
	
	
	/**
	 * 获取分类链接
	 *
	 * @param NB_Request $input
	 * @param NB_Response $output
	 */
	public function doGetCatLink(NB_Request $input, NB_Response $output) {
		$html = NB_Api::run("Service.FetchHtml.getHtmlOrDom" , array(
				'url'            => 'https://itunes.apple.com/cn/genre/ios-you-xi/id6014?mt=8',   #URL
				'charset'     => 'UTF-8',         				#对方页面编码
				'timeout'    => $this->timeout,               		#超时时间
				'getDom'    => 1,               				#是否获得Dom
				'referer'      => 'www.google.com.hk',   		#指定referer
				'proxy'        => '1',          				#是否使用代理
		));
		$total =0;
			
		if($html){
			$catLink = "";
			foreach($html->find('ul[class=top-level-subgenres] li') as $li){
					
					#分类链接
					$link = $li->children(0)->href;
					echo $link."success\n";
					$catLink .=$link."\n";
					sleep(10);
				}
				NB_File::write($catLink, "./{$this->catdatafilename}");
		}else{
					echo  "整失败了~";
		}
		exit;
	}
	
	
	/**
	 * 获取综述页链接
	 * @param NB_Request $input
	 * @param NB_Response $output
	 */
	public function doGetDetailLink(NB_Request $input, NB_Response $output) {
		
				NB_File::write(time(), "{$this->proRoot}/Html/data/log.txt");
				$timeout    = $this->timeout;
			 
				$list = file("{$this->proRoot}/Html/{$this->catdatafilename}");
				 
				if($list){
					
					foreach ($list  as $link){
						$detailLink = "";
						 $key = trim($link);
						 $detailLinkDataFileName = md5($link).".txt";
						 if(file_exists("{$this->proRoot}/Html/data/{$detailLinkDataFileName}")){
						 	continue  ;
						 }
						 $gamelist = NB_Api::run("Service.FetchHtml.getHtmlOrDom" , array(
								'url'            => $link,   #URL
								'charset'     => 'UTF-8',         				#对方页面编码
								'timeout'    => $timeout,               		#超时时间
								'getDom'    => 1,               					#是否获得Dom
								'referer'      => 'www.google.com.hk',   #指定referer
								'proxy'        => '1',          						#是否使用代理
						));
						if($gamelist){
							foreach($gamelist->find('div[class=column]') as $div){
								foreach ($div->find("ul li") as $innli){
									#综述页链接
									$deLink = $innli->children(0)->href;
									echo $deLink."success\n";
									$detailLink .=$deLink."\n";
									sleep(10);
								}
							}
							$detailLink.="{$this->data[$key]}\n";
						 
							NB_File::write($detailLink, "{$this->proRoot}/Html/data/{$detailLinkDataFileName}");
						}else{
							echo  "catch detail error";
						}
					}
				}else{
					echo  "empty list";
				}
				
				exit;
	}
			
	private  function  curlPost($url,$useIpArr,$postdata='',$timeout=2){
		$rand =mt_rand(0,9);
		$useIp = $useIpArr[$rand];
		$timeout = (int)$timeout;
		if(0 == $timeout || empty($url))return false;
		$ch = curl_init();
		curl_setopt ($ch, CURLOPT_URL, $url);
		curl_setopt ($ch, CURLOPT_POSTFIELDS, $postdata);
		curl_setopt ($ch, CURLOPT_POST, true);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('X-FORWARDED-FOR:'.$useIp['ip'], 'CLIENT-IP:'.$useIp['ip']));  //构造IP
		curl_setopt($ch, CURLOPT_REFERER, "http://www.gosoa.com/");   //构造来路
		curl_setopt ($ch, CURLOPT_AUTOREFERER, true);
		curl_setopt ($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
		curl_setopt ($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_TIMEOUT, $timeout);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
		$content = curl_exec( $ch );
		curl_close ( $ch );
		return $content;
	}
	
	private function getFirstLetter($str){
		$fchar = ord($str{0});
		if($fchar >= ord("A") and $fchar <= ord("z") )return strtoupper($str{0});
		$s1 = iconv("UTF-8","gb2312", $str);
		$s2 = iconv("gb2312","UTF-8", $s1);
		if($s2 == $str){$s = $s1;}
		else{$s = $str;}
		$asc = ord($s{0}) * 256 + ord($s{1}) - 65536;
		if($asc >= -20319 and $asc <= -20284) return "A";
		if($asc >= -20283 and $asc <= -19776) return "B";
		if($asc >= -19775 and $asc <= -19219) return "C";
		if($asc >= -19218 and $asc <= -18711) return "D";
		if($asc >= -18710 and $asc <= -18527) return "E";
		if($asc >= -18526 and $asc <= -18240) return "F";
		if($asc >= -18239 and $asc <= -17923) return "G";
		if($asc >= -17922 and $asc <= -17418) return "I";
		if($asc >= -17417 and $asc <= -16475) return "J";
		if($asc >= -16474 and $asc <= -16213) return "K";
		if($asc >= -16212 and $asc <= -15641) return "L";
		if($asc >= -15640 and $asc <= -15166) return "M";
		if($asc >= -15165 and $asc <= -14923) return "N";
		if($asc >= -14922 and $asc <= -14915) return "O";
		if($asc >= -14914 and $asc <= -14631) return "P";
		if($asc >= -14630 and $asc <= -14150) return "Q";
		if($asc >= -14149 and $asc <= -14091) return "R";
		if($asc >= -14090 and $asc <= -13319) return "S";
		if($asc >= -13318 and $asc <= -12839) return "T";
		if($asc >= -12838 and $asc <= -12557) return "W";
		if($asc >= -12556 and $asc <= -11848) return "X";
		if($asc >= -11847 and $asc <= -11056) return "Y";
		if($asc >= -11055 and $asc <= -10247) return "Z";
		return null;
	}
	
	private function pinyin($zh){
		$ret = "";
		$s1 = iconv("UTF-8","gb2312", $zh);
		$s2 = iconv("gb2312","UTF-8", $s1);
		if($s2 == $zh){$zh = $s1;}
		for($i = 0; $i < strlen($zh); $i++){
			$s1 = substr($zh,$i,1);
			$p = ord($s1);
			if($p > 160){
				$s2 = substr($zh,$i++,2);
				$ret .= $this->getFirstLetter($s2);
			}else{
				$ret .= $s1;
			}
		}
		return $ret;
	}
	
	 
}