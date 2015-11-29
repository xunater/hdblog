<?php
class JVideoDown{
	private $_cookie;
	private $_sourceUrl;
	private $_lessons;
	private $_playUrls;
	private $_videoUrls;
	private $_course;
	private static $_dataList;
	/**
	 * 
	 */
	public function __construct($sourceUrl='',$lessUrl='',$cookie){
		try {
			if(isset($lessUrl) && $lessUrl != ''){
				$lessId = substr($lessUrl, strripos($lessUrl,'/')+1,strripos($lessUrl,'.')-strripos($lessUrl,'/')-1);
				$this->_lessons = (array)$lessId;
			}else{
				//http://www.jikexueyuan.com/course/javascript/?pageNum=3
				preg_match('/course\/(.+)\//',$sourceUrl,$course);
				if(!$course[1]){
					throw new Exception("get course faild");
				}
				self::$_dataList['course'] = $course[1];
				$this->_sourceUrl = $sourceUrl;
				$this->_lessons = $this->get_lessons();
			}
			
			$this->_cookie = $cookie;
			$this->_playUrls = $this->get_play_urls();
			$this->_videoUrls = $this->get_video_urls();
			self::$_dataList['lessons'] = $this->_lessons;
		} catch (Exception $e) {
			echo $e->getMessage();
		}

	}
	/**
	 * 获取源课程列表页，课程的url地址
	 * array(
	 * 		'course'=>'js',
	 *		'lesson'=>array(
	 *			array(
	 *				'title'=>'js语法详解',
	 *				'lessUrl'=>'http://www.jikexueyuan.com/course/178.html',
	 *				'playUrls'=>array(
	 *					array(
	 *						'title'=>'Javascript语法-运算符(1)'
	 *						'url'=>'http://www.jikexueyuan.com/course/178_1.html?ss=1'
	 *					),
	 *					array(
	 *						'title'=>'Javascript语法-运算符(2)'
	 *						'url'=>'http://www.jikexueyuan.com/course/178_2.html?ss=1'
	 *					)
	 *				)
	 *			),
	 *			array(
	 *				'title'=>'js基础语法'
	 *				'lessUrl'=>'http://www.jikexueyuan.com/course/65.html',
	 *			)
	 * 		)
	 * );
	 */
	public function get_lessons(){
		if(file_exists('lessonUrls.php') && filesize('lessonUrls.php')>0){
			return json_decode(file_get_contents('lessonUrls.php'),true);
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
			file_put_contents('lessonUrls.php',json_encode($lessons));
			return $lessons;
		}
	}
	/**
	 * 根据课程列表页获取视频播放页url
	 */
	public function get_play_urls(){
		if(file_exists('playUrls.php') && filesize('playUrls.php')>0){
			return json_decode(file_get_contents('playUrls.php'),true);
		}else{
			if(!$this->_lessons){
				throw new Exception("lesson_urls not exists!", 1);
			}		
			foreach($this->_lessons as $k => $lesson){
				$tmpText = file_get_contents($lesson['url']);
				// $pattern = '/http:\/\/www\.jikexueyuan\.com\/course\/'.$lesson['id'].'_(\d+)\.html/';
				$pattern = '/<a href="([^"]*)" jktag="[^"]*">(.*)<\/a>\s*<p class="f_r">/';
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
				
				$this->_lessons[$k] = $lesson;
			}
			file_put_contents('playUrls.php',json_encode($this->_lessons));
			return $this->_lessons;
		}
	}

	/**
	 * 根据视频播放页url获取视频真实地址
	 */
	public function get_video_urls(){
		if(file_exists('videoUrls.php') && filesize('videoUrls.php')>0){
			return json_decode(file_get_contents('videoUrls.php'),true);
		}else{
			if(!$this->_playUrls){
				throw new Exception("play_urls not exists!");
			}
			// $videoUrls = array();
			foreach ($this->_playUrls as $key => $lessonInfo) {
				// $videoArr = array(
				// 	'lesson'=>$lessonInfo['lesson']
				// );

				foreach($lessonInfo['playUrls'] as $k => $play_url){
					// var_dump($play_url);die;
					$s = curl_init();
					curl_setopt($s,CURLOPT_URL,$play_url['url']); 
					curl_setopt($s,CURLOPT_FOLLOWLOCATION,1);
					curl_setopt($s,CURLOPT_HTTPHEADER,array('Host:www.jikexueyuan.com'));
					curl_setopt($s,CURLOPT_HEADER,'Mozilla/5.0 (iPhone; CPU iPhone OS 8_0 like Mac OS X) AppleWebKit/600.1.3 (KHTML, like Gecko) Version/8.0 Mobile/12A4345d Safari/600.1.4'); 
					curl_setopt($s,CURLOPT_RETURNTRANSFER,1); 
					curl_setopt($s,CURLOPT_COOKIE,$this->_cookie); 
					$vPageContent = curl_exec($s);
					curl_close($s);
					$match = array();
					preg_match('/src="(.*\.mp4)"/',$vPageContent,$match);
					// $title = substr($play_url, strripos($play_url,'/')+1,strripos($play_url,'.')-strripos($play_url,'/')-1);
					if(!isset($match[1])){
						throw new Exception('get_video_urls Error'.$play_url['title']);
						exit;
					}
					$play_url['videoUrl'] = $match[1];
					$lessonInfo['playUrls'][$k] = $play_url;
				}
				$this->_playUrls[$key] = $lessonInfo;
			}
			// var_dump($this->_playUrls);die;
			file_put_contents('videoUrls.php',json_encode($this->_playUrls));
			return $this->_playUrls;
		}
	}

	/**
	 * 根据视频地址保存视频到本地
	 */
	public function save_video(){
		// return;
		// is_dir()
		foreach($this->_videoUrls as $less){
			// 创建目录
			if(!is_dir($less['title'])){
				mkdir(iconv('utf-8','gbk',$less['title']));
			}
			echo $less['title']."start------->\r\n";
			foreach($less['playUrls'] as $video){
				$filename = iconv('utf-8','gbk',$less['title']).'\\'.iconv('utf-8','gbk',$video['title']).".mp4";
				// file_put_contents($filename,$video['title']);
				// echo $video['videoUrl']."\r\n";
				// die;
				if(!file_exists($filename)){
					$curl = curl_init($video['videoUrl']);
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

$sourceUrl = 'http://www.jikexueyuan.com/course/javascript/?pageNum=1';
// $sourceUrl = 'http://www.jikexueyuan.com/course/php/1-0-0/?pageNum=1';
$cookie = 'stat_uuid=1448162304587918784710; bannerswitch=close; uname=1332020711; uid=1197132; code=O6FSPK; authcode=eb35jOLPvK1u5T%2BLh0P8FC25TAMrB7enMFrACbQWY20yfUsjR7CCJbf3zH0GAVw87U4T5sRQ%2B7nyxa4dknUEJoIfsKNF91GbxFqk%2Br16wpo8Cdriy5KtjFBPMSToog; level_id=3; is_expire=0; domain=3439112340; stat_fromWebUrl=; stat_ssid=1449489071346; _ga=GA1.2.424167810.1448162301; Hm_lvt_f3c68d41bda15331608595c98e9c3915=1448162317,1448719042,1448764989,1448807347; Hm_lpvt_f3c68d41bda15331608595c98e9c3915=1448810857; MECHAT_LVTime=1448810857245; QINGCLOUDELB=7e36c8b37b8339126ed93010ae808701d562b81daa2a899c46d3a1e304c7eb2b|VlsZa|VlsYX; MECHAT_CKID=cookieVal=006600144816230305495089; connect.sid=s%3AzDyb0dV_pJpm5qPvby5-Lbu_WjbQhxO9.Gr2wepzIYkUyjwL%2B%2F8dhR0BffCmfZjX9rN1IhL2VRFQ; undefined=; stat_isNew=0';
//http://www.jikexueyuan.com/course/2232.html
$d = new JVideoDown($sourceUrl,'',$cookie);
@ini_set('memory_limit','-1');
set_time_limit(0);
$d->save_video();

?>
