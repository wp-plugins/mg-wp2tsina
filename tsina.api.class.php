<?php

// 新浪微博操作类

class twSina {
    var $agent = null;
    var $islogined = false;
    
    var $appkey = 4240729813;
    var $appsec = '372fa31b2b69e08124b2b92445cf59b9';
    
    var $last_result = null; // 最后一次返回的结果
    
    function __construct()
    {
        $this->agent = new Snoopy;
        $this->agent->curl_path = '/usr/bin/curl';
    }
    
    function user($user, $pass)
    {
        $this->agent->user = $user;
        $this->agent->pass = $pass;
    }
    
    function post($msg, $pic=null)
    {
		$formvars = array(
		    'source' => $this->appkey,
		    'status' => $msg,
		);
		
		$pic = trim($pic);
		
		if (empty($pic)) {
		    $this->agent->submit('http://api.t.sina.com.cn/statuses/update.json', $formvars);
		}else {
		    // 保存远程图片到本地
            if (preg_match('/^https?:\/\//', $pic)) {
                $tmpfname = tempnam("/tmp", "twpic_");
                $f = fopen($tmpfname, "w");
                fwrite($f, file_get_contents($pic));
                fclose($f);
                
                $pic = $tmpfname;
            }elseif (preg_match('/^file:\/\//', $pic)) {
                $pic = str_replace('file://', '', $pic);
            }
            
            if (!empty($pic) && file_exists($pic)) {
                $this->agent->_submit_type = "multipart/form-data";
                $this->agent->submit('http://api.t.sina.com.cn/statuses/upload.json', $formvars, array('pic'=>$pic));
            }else {
                $this->agent->submit('http://api.t.sina.com.cn/statuses/update.json', $formvars);
            }
            
            // 删除临时文件
            if (!empty($tmpfname)) {
            	@unlink($tmpfname);
            }
		}
		
        $data = json_decode($this->agent->results, true);
        if ($data) $this->last_result = $data;
        return intval($data['id']) > 0 ? intval($data['id']) : false;
    }
    
    function del($id)
    {
        $this->agent->submit('http://api.t.sina.com.cn/statuses/destroy/'.$id.'.json?source='.$this->appkey);
        $data = json_decode($this->agent->results, true);
        return intval($data['id']) > 0;
    }
    
    function alive($msg=null)
    {
        if (is_string($msg)) {
        	$id = $this->post($msg);
        }elseif (is_array($msg)) {
            $msg = $msg[array_rand($msg)];
        	$id = $this->post($msg);
        }else {
        	$id = $this->post('[#DIID]');
        }
        
        sleep(5);
        
        if ($id > 0) {
            $this->del($id);
            return true;
        }else {
            return false;
        }
    }
    
    /**
     * 获得两者的关系（$myid不指定则说明是当前用于）
     *
     * 返回的结果举例说明：
     *
     * array(2) {
     *     ["source"]=>
     *     array(5) {
     *         ["id"]=>
     *         int(1723723610)
     *         ["screen_name"]=>
     *         string(9) "爱买人"
     *         ["following"]=>
     *         bool(true)
     *         ["followed_by"]=>
     *         bool(false)
     *         ["notifications_enabled"]=>
     *         bool(false)
     *     }
     *     ["target"]=>
     *         array(5) {
     *         ["id"]=>
     *         int(1763927591)
     *         ["screen_name"]=>
     *         string(4) "byMG"
     *         ["following"]=>
     *         bool(false)
     *         ["followed_by"]=>
     *         bool(true)
     *         ["notifications_enabled"]=>
     *         bool(false)
     *     }
     * }
     * 此时，说明“爱买人“加了“byMG”为关注，但是byMG没有加“爱买人”。
     *
     */
    function relations($myid=0, $hisid=0)
    {
        if ($myid + $hisid == 0) {
            return false;
        }
        
        $url = 'http://api.t.sina.com.cn/friendships/show.json?source='.$this->appkey;
        if ($myid > 0) $url .= '&source_id='.$myid;
        if ($hisid > 0) $url .= '&target_id='.$hisid;
        
        $this->agent->fetch($url);
        $data = json_decode($this->agent->results, true);
        
        return $data;
    }
    
    function friendto($hisid=0)
    {
        if ($hisid == 0) {
            return false;
        }
        
        $relations = $this->relations(0, $hisid);
        if ($relations['source']['following']) {
            return false;
        }
        
        $formvars = array('user_id' => $hisid);
        $url = 'http://api.t.sina.com.cn/friendships/create.json?source='.$this->appkey;
        $this->agent->submit($url, $formvars);
        $data = json_decode($this->agent->results, true);
        
        return !empty($data['created_at']);
    }
    
    // 以中文2个字符、英文一个字符的方式计算文本长度
    function msg_length($text)
    {
        return strlen(preg_replace("/[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}/", '##', $text));
    }
    
    // 以中文占1个计数、英文半个计数的计算方式截断文本
    // 也就是所，如果$len=2，那么可以是两个汉字，或者1个汉字2个英文
    function substr($text, $len=0)
    {
        if ($len === 0) return $text;
        
        preg_match_all("/(?:[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2})|./", $text, $matches);
        
        $return = '';
        while($len > 0) {
            $str = array_shift($matches[0]);
            $return .= $str;
            if (preg_match("/[\xE1-\xEC\xEE\xEF][\x80-\xBF]{2}/", $str)) {
                $len -= 1; // 中文消耗一个
            }else {
                $len -= 0.5; // 英文消耗半个字
            }
        }
        return $return;
    }
    
    /**
     * 清理文本中的非utf-8的无效字符
     *
     * @param string $text
     * @return string
     */
    function __utf8_trim($text) {
    
        $len = strlen($text);
        
        for ($i=strlen($text)-1; $i>=0; $i-=1){
        $hex .= ' '.ord($text[$i]);
        $ch = ord($text[$i]);
        if (($ch & 128)==0) return(substr($text,0,$i));
        if (($ch & 192)==192) return(substr($text,0,$i));
        }
        return($text.$hex);
    }
}
?>
