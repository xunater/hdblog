<?php
class JVideoDown{
	private $_cookie;
	private $_sourceUrl;
	private $_lessonUrls;
	private $_playUrls;
	private $_videoUrls;
	/**
	 * 
	 */
	public function __construct($sourceUrl='',$lessUrl='',$cookie){
		if(isset($lessUrl) && $lessUrl != ''){
			$lessId = substr($lessUrl, strripos($lessUrl,'/')+1,strripos($lessUrl,'.')-strripos($lessUrl,'/')-1);
			$this->_lessonUrls = (array)$lessId;
		}else{
			$this->_sourceUrl = $sourceUrl;
			$this->_lessonUrls = $this->get_lesson_urls();
		}
		
		$this->_cookie = $cookie;
		$this->_playUrls = $this->get_play_urls();
		$this->_videoUrls = $this->get_video_urls();
	}
	/**
	 * 获取源课程列表页，课程的url地址
	 */
	public function get_lesson_urls(){
		$sourceContent = file_get_contents($this->_sourceUrl);
		/*$ch = curl_init($this->_sourceUrl);
		// curl_setopt($ch,CURLOPT_FOLLOWLOCATION,1);
		// curl_setopt($ch,CURLOPT_HTTPHEADER,array('Host:www.kuaixuewang.com'));
		curl_setopt($ch,CURLOPT_RETURNTRANSFER,1);
		$sourceContent = curl_exec($ch);
		curl_close($ch);
		*/
		preg_match_all('/li id="(\d+)"/',$sourceContent,$matches);
		return $matches[1];
	}
	/**
	 * 根据课程列表页获取视频播放页url
	 */
	public function get_play_urls(){
		// echo '<pre>';
		$play_urls = array();
		foreach($this->_lessonUrls as $less_id){
			// var_dump($less_id);
			$less_url = 'http://www.jikexueyuan.com/course/'.$less_id.'.html';
			// $less = array('less_id'=>$less_id,'less_url'=>'http://www.jikexueyuan.com/course/'.$less_id.'.html');
			$tmpText = file_get_contents($less_url);
			$pattern = '/http:\/\/www\.jikexueyuan\.com\/course\/'.$less_id.'_(\d+)\.html/';
			preg_match_all($pattern,$tmpText,$matches);
			$playUrl = array_flip(array_flip($matches[0]));
			$tmpArr = array('lesson'=>$less_id,'play_urls'=>$playUrl);
			$play_urls[] = $tmpArr;
		}
		// var_dump($play_urls);
		// echo '</pre>';
		return $play_urls;
	}

	/**
	 * 根据视频播放页url获取视频真实地址
	 */
	public function get_video_urls(){
		// echo '<pre>';
		$videoUrls = array();
		foreach ($this->_playUrls as $lessonInfo) {
			$videoArr = array(
				'lesson'=>$lessonInfo['lesson']
			);
			foreach($lessonInfo['play_urls'] as $play_url){
				$s = curl_init();
				curl_setopt($s,CURLOPT_URL,$play_url); 
				curl_setopt($s,CURLOPT_FOLLOWLOCATION,1);
				curl_setopt($s,CURLOPT_HTTPHEADER,array('Host:www.jikexueyuan.com'));
				curl_setopt($s,CURLOPT_HEADER,'Mozilla/5.0 (iPhone; CPU iPhone OS 8_0 like Mac OS X) AppleWebKit/600.1.3 (KHTML, like Gecko) Version/8.0 Mobile/12A4345d Safari/600.1.4'); 
				curl_setopt($s,CURLOPT_RETURNTRANSFER,1); 
				curl_setopt($s,CURLOPT_COOKIE,$this->_cookie); 
				$vPageContent = curl_exec($s);
				curl_close($s);
				// return $vPageContent;
				$match = array();
				preg_match('/src="(.*\.mp4)"/',$vPageContent,$match);
				// preg_match('/<title>(.*) - 快学网<\/title>/',$vPageContent,$match2);
				$title = substr($play_url, strripos($play_url,'/')+1,strripos($play_url,'.')-strripos($play_url,'/')-1);
				$videoArr['videoInfo'][] = array('url'=>$match[1],'title'=>$title);
			}
			
			$videoUrls[] = $videoArr;
		}
		// var_dump($videoUrls);
		// echo '</pre>';
		return $videoUrls;
	}

	/**
	 * 根据视频地址保存视频到本地
	 */
	public function save_video(){
		// return;
		// header("Content-Type: application/force-download");
		foreach($this->_videoUrls as $less){
			// 创建目录
			if(!is_dir($less['lesson'])){
				mkdir($less['lesson']);
			}
			echo $less['lesson']."start------->\r\n";
			foreach($less['videoInfo'] as $video){
				$filename = $less['lesson'].'/'.$video['title'].".mp4";
				if(!file_exists($filename)){
					$curl = curl_init($video['url']);
					curl_setopt($curl,CURLOPT_RETURNTRANSFER,1);
					$v = curl_exec($curl);
					curl_close($curl);
					$tp = @fopen($filename, 'a');
					fwrite($tp, $v);
					fclose($tp);
					echo $video['title'].".mp4 completed!\r\n";
				}
				// exit;
			}
			echo $less['lesson']."end-------->\r\n";
		}



	}
}

// $sourceUrl = 'http://www.kuaixuewang.com/dev/list-4-3.html';
$sourceUrl = 'http://www.jikexueyuan.com/course/php/1-0-0/?pageNum=1';
$cookie = 'stat_uuid=1448693716836271036592; uname=1332020711; uid=1197132; code=O6FSPK; authcode=0643%2FVXcYSLNpvdaGrMzeCQzI%2B85DEflmrbaCILAAhXMOjBXHaaOYt1HOQR1U9OHMGstqrXpfRdBNy054uYgnuumsEDOP7qvc7LCca3HNY0GjqkTWXPhOCM3WpItig; level_id=3; is_expire=1; domain=3439112340; QINGCLOUDELB=84b10773c6746376c2c7ad1fac354ddfd562b81daa2a899c46d3a1e304c7eb2b|VllWu|VllPy; Hm_lvt_f3c68d41bda15331608595c98e9c3915=1447134802,1447135462,1448693711; Hm_lpvt_f3c68d41bda15331608595c98e9c3915=1448695486; _ga=GA1.2.1586850857.1448693715; MECHAT_LVTime=1448695486139; MECHAT_CKID=cookieVal=006600144238547779243850; connect.sid=s%3AEpENV0iJ8fMXqYyJ59juD0gwDCSj6H_2.cEgM6NG9WJUm1NdlDrk%2FWiamGSDCrgZIlvpcodUeap0; stat_fromWebUrl=; undefined=; stat_ssid=1448921505563; stat_isNew=0';
//http://www.jikexueyuan.com/course/2232.html
$d = new JVideoDown($sourceUrl,'',$cookie);
@ini_set('memory_limit','-1');
set_time_limit(0);
$d->save_video();

?>
