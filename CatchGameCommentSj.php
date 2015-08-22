<?php
/**
 * 游戏库手机游戏评论抓取
 *
 * @author haungjl
 * @version 1.0
 * 
 */
 
 //ini_set ('display_errors',1);
 //error_reporting(E_ALL);

class Tools_CatchGameCommentSj extends Page_Abstract {
	
	private $hardId = 0;    		#硬件平台ID
	
	public function __construct(NB_Request $input, NB_Response $output) {
		parent::__construct($input, $output);
	}

	public function validate(NB_Request $input, NB_Response $output) {
	
		if (!parent::baseValidate($input, $output)) {
			return false;
		}

		return true;
	}
 	
	
	public function doDefault(NB_Request $input, NB_Response $output) {
 		NB_File::load('/home/huangjialin/catch/Html/api/simple_html_dom.php');
 		$html = new simple_html_dom();
		$order_by = 2;	#添加时间
		$param = array(
		    'hardPlatform'	=> 2,
				
		);
		#获取游戏列表
		$count = array_merge($param,array('isCount'=>1));
		$output->countgames = Helper_YouXiKu_Base::getGameInfo($count);
		if ($output->countgames ) {
			$nowpage =  $input->get('page')&&  $input->get('page') >0 ?  $input->get('page') :'1';
			$glist 	 = array('game_num' => 100,'page' => $nowpage);
			$games = Helper_YouXiKu_Base::getGameInfo(array_merge($param,$glist) );
		}
		$apiUrl = "http://xiazai.zol.com.cn/search?type=3&wd=";
		foreach ($games as $key =>$v){
			if(!$v['id'] || empty($v['name']) ){
			    $fp = fopen("./falsezol.log","a+");
				fwrite($fp,$v['id']."\n");
				fclose($fp); 
				continue; 
			}
			$res = NB_Http::curlPage(array('url' => $apiUrl.$v['name'], 'timeout'  => 2));
		 
			$html->load($res);
    		$list = $html->find('.results-box .results-text .item .item-header a');
		
    		
			 foreach ($list as $obj) {
	               echo $v['name'].">>".$obj->href."\r\n";
	               
	               if(strpos($obj->href,'.shtml')===FALSE){
	               		continue ;
	               }
	               
	               
	               $contentHtml = NB_Http::curlPage(array('url' => $obj->href, 'timeout'  => 2));
	               $html->load($contentHtml);
	               
	             
	                if(strpos($obj->href,'.shtml')){
	                	$contentList = $html->find('.discuss-area .discuss-item .discuss-content .post-text p');
	                }else{
	                	$contentList = $html->find('.user-list  li p');
	                	
	                }
	               	foreach ($contentList as $con){
	               		$content = $con->innertext;
	               		$comSay = htmlspecialchars(trim(strip_tags($content)));
	               		$comSay = iconv(ICONV_SOURCE,ICONV_DEST,$comSay);
	               		
	               		$comSay = preg_replace(array('#NB.*网友：#i','#.*：#'),array('',''),$comSay);
	               	
	               		if(empty($comSay) || !self::isValidData($comSay)){
	               		 	$fp = fopen("./falsezol.log","a+");
	               			fwrite($fp,$v['id']."\n");
	               			fclose($fp) ; 
	               			
	               			continue ;
	               		}else{
	               			  
	               			$userIp = NB_Config::get('Admin_Ip');
	               			$srand  = rand(0,count($userIp)-1);
	               			$userIp = trim($userIp[$srand]);
		               		$parr['com_point']		= rand(5,10);
		               		$parr['com_say'] 		= str_replace(array('豌豆'),array('**'),$comSay);
		               		$parr['com_say'] 		= str_replace('豌','**',$comSay);
		               		$parr['com_say'] 		= str_replace('荚','**',$comSay);
		               		$parr['com_say'] 		= str_replace('豆','**',$comSay);
		               		$parr['com_softid']     = $v['id'];
		               		$parr['com_type']       = 4;
		               		$parr['com_softname']   = $v['name'];
							$year   = date('Y');
							$month  = date('m');
							$day 	= date('d')-rand(1,25);
							$h 		= date('H')-rand(1,5);
							$min   	= date('i')-rand(1,10);
							$sec  	= rand(10,55);   		
		               		$parr['com_date']       = $year.'-'.$month.'-'.$day.' '.$h.':'.$min.':'.$sec;
		               								 
		               		$parr['com_userip']     = $userIp;
		               		
		               		$isSam = Helper_YouXiKu_Comment::getComment(array('gameId'=>$v['id'],'comType'=>4,'isWhere'=>" and com_say='{$comSay}' "));
		               		$parr['com_check']		= 1;
		               		$address				= NB_Api::run("Service.Area.getIp" , array('ip'=>$parr['com_userip'],'setCookie'=>0));
		               		$address 				= $address ? $address['province'] : '本地局域网';
		               		$parr['area']			= iconv(ICONV_SOURCE,ICONV_DEST,$address);;
		               	  	 
		               		if (!$isSam) {
		               			$sql = " insert into ab_comments ".Libs_Global_String::valueClause($parr);
		               			 //echo $sql."\r\n";
		               			 if(Db_AbabYouXiKu::instance()->query($sql)){
		               			 	$fp = fopen("./successzol.log","a+");
		               				fwrite($fp,$v['id']."\n");
		               				fclose($fp);  
		               			}else{
		               			    $fp = fopen("./falsezol.log","a+");
		               				fwrite($fp,$v['id']."\n");
		               				fclose($fp); 
		               			} 
		               			
		               		}
	               		}
	               		
	               	}
	                
	              
	               break;
	               
	        }	
			sleep(5);
		}
		
		
		exit;
	}
	
	
	public function isValidData($s){
		if(preg_match("/([\x{4e00}-\x{9fa5}].+)\\1{4,}/u",$s)){
			return false;//同字重复５次以上
		}elseif(preg_match("/^[0-9a-zA-Z]*$/",$s)){
			return false;//全数字，全英文或全数字英文混合的
		}elseif(strlen($s)<5){
			return false;//输入字符长度过短
		}
		return true;
	}
	
	 
}