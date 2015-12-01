<?php
class JVideoDown{
	private $_cookie;
	private $_sourceUrl;
	private $_lessons;
	private $_course;
	/**
	 * 
	 */
	public function __construct($sourceUrl='',$cookie){
		try {
			is_dir('tmp') || mkdir('tmp');
			if($sourceUrl!=''){
				preg_match('/course\/(.+)\//',$sourceUrl,$course);
				if(!$course[1]){
					throw new Exception("get course faild");
				}
				$this->_course = $course[1];
				$this->_sourceUrl = $sourceUrl;
				$this->_lessons = $this->get_lessons();
			}
			$this->_cookie = $cookie;
		} catch (Exception $e) {
			echo $e->getMessage();
		}

	}
	/**
	 * 获取源课程列表页，课程的url地址
	 */
	private function get_lessons(){
		$filename = 'tmp/'.md5($this->_sourceUrl).'lessonUrls.php';
		if(file_exists($filename) && filesize($filename)>0){
			return json_decode(file_get_contents($filename),true);
		}else{
			$sourceContent = file_get_contents($this->_sourceUrl);
			$pattern = '/<h2 class="lesson-info-h2">\s*<a href="(\S+\/(\d+)\.html)".*>(.*)<\/a><\/h2>/';
			preg_match_all($pattern,$sourceContent,$matches);
			$urls = $matches[1];
			$ids = $matches[2];
			$titles = $matches[3];
			$lessons = array();
			foreach ($urls as $key => $value) {
					$lessons[] = array(
						'id'=>$ids[$key],
						'title'=>$titles[$key],
						'url'=>$value
					);
			}
			file_put_contents($filename,json_encode($lessons));
			return $lessons;
		}
	}
	/**
	 * 根据课程列表页获取视频播放页url
	 */
	private function get_play_urls(){
		$filename = 'tmp/'.md5($this->_sourceUrl).'playUrls.php';
		if(file_exists($filename) && filesize($filename)>0){
			return json_decode(file_get_contents($filename),true);
		}else{
			$lessons = $this->get_lessons();
			if(!$lessons){
				throw new Exception("lessons not exists!");
				exit;
			}		
			foreach($lessons as $k => $lesson){
				$tmpText = file_get_contents($lesson['url']);
				// $pattern = '/http:\/\/www\.jikexueyuan\.com\/course\/'.$lesson['id'].'_(\d+)\.html/';
				// $pattern = '/<a href="([^"]*)" jktag="[^"]*">(.*)<\/a>\s*<p class="f_r">/';
				// $pattern = '/<i class="lessonmbers"><em>\d+<\/em><\/i>\s*<div class="text-box">\s*<h2>\s*<a href="([^"]*)" jktag="[^"]*"\s*>(.*)<\/a>\s*<p class="f_r">/';
				$pattern = '/<span class="sm-icon "><\/span>\s*<a href="([^"]*)" jktag="[^"]*">\d+\.(.*)<\/a>\s*<span class="lesson-time">/';
				preg_match_all($pattern,$tmpText,$matches);
				if(!$matches[0] || !is_array($matches[0])){
					throw new Exception("get_play_urls ERROR");
					exit;
				}
				$playUrls = $matches[1];
				$playTitles = $matches[2];
				foreach ($playUrls as $key => $value) {
					$lesson['playUrls'][] = array(
						'title'=>$playTitles[$key],
						'url'=>$value
					);
				}
				
				$lessons[$k] = $lesson;
			}
			file_put_contents($filename,json_encode($lessons));
			return $lessons;
		}
	}

	/**
	 * 根据视频播放页url获取视频真实地址
	 */
	private function get_video_urls(){
		$filename = 'tmp/'.md5($this->_sourceUrl).'videoUrls.php';
		if(file_exists($filename) && filesize($filename)>0){
			return json_decode(file_get_contents($filename),true);
		}else{
			$lessons = $this->get_play_urls();
			if(!$lessons){
				throw new Exception("get_video_urls not exists!");
			}
			// $videoUrls = array();
			foreach ($lessons as $key => $lessonInfo) {				
				foreach($lessonInfo['playUrls'] as $k => $play_url){
					$s = curl_init();
					curl_setopt($s,CURLOPT_URL,$play_url['url']); 
					curl_setopt($s,CURLOPT_FOLLOWLOCATION,1);
					curl_setopt($s,CURLOPT_HTTPHEADER,array('Host:www.jikexueyuan.com'));
					curl_setopt($s,CURLOPT_HEADER,'Mozilla/5.0 (iPhone; CPU iPhone OS 8_0 like Mac OS X) AppleWebKit/600.1.3 (KHTML, like Gecko) Version/8.0 Mobile/12A4345d Safari/600.1.4'); 
					curl_setopt($s,CURLOPT_RETURNTRANSFER,1); 
					curl_setopt($s,CURLOPT_COOKIE,$this->_cookie); 
					$vPageContent = curl_exec($s);
					curl_close($s);
					
					preg_match('/src="(.*\.mp4)"/',$vPageContent,$match);

					if(!isset($match[1])){
						throw new Exception('get_video_urls Error'.$play_url['url']);
						exit;
					}
					$play_url['videoUrl'] = $match[1];
					$lessonInfo['playUrls'][$k] = $play_url;
				}
				$lessons[$key] = $lessonInfo;
			}
			// var_dump($this->_playUrls);die;
			file_put_contents($filename,json_encode($lessons));
			return $lessons;
		}
	}

	/**
	 * 根据视频地址保存视频到本地
	 */
	public function save_video(){
		is_dir($this->_course) || mkdir($this->_course);
		$lessons = $this->get_video_urls();
		foreach($lessons as $less){
			// 创建目录
			$lessTitle = $this->_course."\\".preg_replace("/[\/\\\]/", '', $less['title']);
			$lessTitle = iconv('utf-8','gbk',$lessTitle);
			if(!is_dir($lessTitle)){
				mkdir($lessTitle);
			}
			echo $lessTitle."start------->\r\n";
			foreach($less['playUrls'] as $video){
				$videoTitle = preg_replace("/[\/\\\]/", '', iconv('utf-8','gbk',$video['title']));
				$filename = $lessTitle.'\\'.$videoTitle.".mp4";
				if(!file_exists($filename)){
					$curl = curl_init($video['videoUrl']);
					curl_setopt($curl,CURLOPT_RETURNTRANSFER,1);
					$v = curl_exec($curl);
					curl_close($curl);
					// $tp = @fopen($filename, 'a');
					// fwrite($tp, $v);
					// fclose($tp);
					file_put_contents($filename, $v);
					echo $videoTitle.".mp4 completed!\r\n";
				}
				// exit;
			}
			echo $lessTitle."end-------->\r\n";
		}
	}
}
@ini_set('memory_limit','-1');
set_time_limit(0);

// $sourceUrl = 'http://www.jikexueyuan.com/course/css3/';
$sourceUrl = 'http://www.jikexueyuan.com/course/docker/';
$cookie = 'stat_uuid=1448859067012569706370; _gat=1; stat_fromWebUrl=; stat_ssid=1449841956499; uname=1332020711; uid=1197132; code=O6FSPK; authcode=e1a0MQrxsCeL6DdE%2Bfl3WZOWHSlj7DB4rmRxcuhV2hrYNYYVr2JpEkf3jhk8oMldba8Z48HYzrweRFS%2BLvim7G7WAZh5OwzqZVihcgRxj%2FYykPLvNd5ta443gUc5GQ; level_id=3; is_expire=0; domain=3439112340; QINGCLOUDELB=7e36c8b37b8339126ed93010ae808701d562b81daa2a899c46d3a1e304c7eb2b|Vl03W|Vl024; Hm_lvt_f3c68d41bda15331608595c98e9c3915=1447135462,1448693711,1448859066,1448949472; Hm_lpvt_f3c68d41bda15331608595c98e9c3915=1448949590; _ga=GA1.2.1894411244.1448859066; connect.sid=s%3AqHxUWcKiyHsniX5w3qHjZN1tDy90HQnj.Lnt1b0Rr%2B4QpCZF62IYcgekBLu2MUa1awIIsE%2Blq3nU; MECHAT_LVTime=1448949590806; MECHAT_CKID=cookieVal=006600144238547779243850; undefined=; stat_isNew=0';
//http://www.jikexueyuan.com/course/2232.html
try{
	$d = new JVideoDown($sourceUrl,$cookie);
	$d->save_video();
}catch(Exception $e){
	echo $e->getMessage();
}
?>
