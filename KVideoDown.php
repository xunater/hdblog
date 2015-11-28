<?php
class KVideoDown{
	private $_cookie;
	private $_sourceUrl;
	private $_lessonUrls;
	private $_playUrls;
	private $_videoUrls;
	public function __construct($sourceUrl,$cookie){
		$this->_sourceUrl = $sourceUrl;
		$this->_cookie = $cookie;
		$this->_lessonUrls = $this->get_lesson_urls();
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
		preg_match_all('/<a href="(.*lesson-(\d+)\.html)" class="piece-img"/',$sourceContent,$matches);
		return $matches[1];
	}
	/**
	 * 根据课程列表页获取视频播放页url
	 */
	public function get_play_urls(){
		// echo '<pre>';
		$play_urls = array();
		foreach($this->_lessonUrls as $less_url){
			// echo $less_url;
			$tmpText = file_get_contents($less_url);
			preg_match_all('/"sendvideo\((\d+),/',$tmpText,$matches);
			$vids = $matches[1];
			array_walk($vids,function (&$item){$item = 'http://www.kuaixuewang.com/dev/video-'.$item.'.html';});
			// var_dump($vids);
			preg_match('/<title>(.*) - 快学网<\/title>/',$tmpText,$lesson);
			$tmpArr = array('lesson'=>substr($less_url,-14,9),'lesson_title'=>$lesson[1],'play_urls'=>$vids);
			$play_urls[] = $tmpArr;
		}
		// var_dump($play_urls);
		// echo '</pre>';
		return $play_urls;
	}

	// get_url('http://www.kuaixuewang.com/dev/lesson-19.html');
	// http://www.kuaixuewang.com/dev/video-158.html
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
				curl_setopt($s,CURLOPT_HTTPHEADER,array('Host:www.kuaixuewang.com'));
				curl_setopt($s,CURLOPT_HEADER,'Mozilla/5.0 (iPhone; CPU iPhone OS 8_0 like Mac OS X) AppleWebKit/600.1.3 (KHTML, like Gecko) Version/8.0 Mobile/12A4345d Safari/600.1.4'); 
				curl_setopt($s,CURLOPT_RETURNTRANSFER,1); 
				curl_setopt($s,CURLOPT_COOKIE,$this->_cookie); 
				$vPageContent = curl_exec($s);
				curl_close($s);
				// return $vPageContent;
				$match = array();
				preg_match('/src="(.*\.mp4)"/',$vPageContent,$match);
				preg_match('/<title>(.*) - 快学网<\/title>/',$vPageContent,$match2);
				$videoArr['videoInfo'][] = array('url'=>$match[1],'title'=>substr($play_url,-14,9),'title_zh'=>$match2[1]);
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
$sourceUrl = 'http://www.kuaixuewang.com/dev/list-4-2.html';
$cookie = 'cover=1; PHPSESSID=j8jm3ngt31i88hm047uva9eb16; hdcmsid=fjoql115a3smds88n78qnnm0k5';
//
$d = new KVideoDown($sourceUrl,$cookie);
@ini_set('memory_limit','-1');
set_time_limit(0);
$d->save_video();
// $urls = $d->get_play_urls();
// var_dump($urls);
/*
$c = get_video('http://www.kuaixuewang.com/dev/video-158.html');
// $c = get_video('http://www.baidu.com');
// phpinfo();
// save_video('http://kuaixuewang.oss-cn-hangzhou.aliyuncs.com/%E9%A9%AC%E9%9C%87%E5%AE%87-php%E6%A1%86%E6%9E%B6%2F%E6%A1%86%E6%9E%B6%E7%AC%AC%E4%BA%8C%E9%98%B6%E6%AE%B5%2F2.mp4');
var_dump($c);
*/
/*
set_time_limit(0);
$lesson_url = 'http://www.kuaixuewang.com/dev/lesson-19.html';
$play_urls = get_url($lesson_url);
foreach($play_urls as $url){
	$v_url = get_video($url);
	save_video($v_url);
}
*/
