<?php

//登录密码在 index.php 里设置



//=========================================================================
// 以下内容请勿修改
//=========================================================================

require 'index.php';
check_authentication(PASSWORD);

//保存内容
if(isset($_POST['act']) && $_POST['act']=='save'){
    $content = $_POST['content'];
    if(IS_GAE && file_put_contents('gs://'.CLOUD_STORAGE_BUCKET.'/index.html', $content)!=strlen($content)){
        echo "<font color='red'>保存失败，请确认 index 里所设置的 Cloud Storage ( ".CLOUD_STORAGE_BUCKET. ") 是否正确 -- " .date('H:i:s'). "</font><br/>";
    }elseif(!IS_GAE && file_put_contents('index.html', $content)!=strlen($content)){
        echo "<font color='red'>保存失败，请确认 index.html 有写入权限 -- " .date('H:i:s'). "</font><br/>";
    }else{
        echo "<font color='green'>保存成功 -- " .date('H:i:s'). "</font><br/>";
        if(IS_GAE){
            $memcache = new Memcache;
            $memcache->set('index.html', $content);
        }
    }
}

$content = readContent(false);
echo <<<EOF
<form method="post" action="">
<textarea style="width:100%; height:500px;" name="content">{$content}</textarea>
<br/>
<input type="hidden" name="act" value="save" />
<input type="submit" value="保存修改" style="margin:10px 0;padding:5px 10px;" /><br/>
提示：如果不小心修改错了，保存为空的就能恢复原始的内容了！
</form>
EOF;


/**
 * 检查是否通过了密码验证，如果没通过就自动显示验证窗口
 * 如果连续3次登录失败就锁定10分钟，如果登录成功就12小时内不需要重复登录
 * @param string $password 管理密码
 * @param boolean $goto_login 如果没登录，是不是转向登录界面
 */
function check_authentication($password, $goto_login=true) {
    if(!$password){
        exit('请先设置管理密码');
    }

    header("Expires: -1");
    header("Cache-Control: no-cache");
    header("Pragma: no-cache");

    //验证登录状态
    $cookieName='_auth_';
    $auth=isset($_COOKIE[$cookieName]) ? $_COOKIE[$cookieName] : '';
    $ipPart=get_ip(2).'.*.*';
    $authHash=substr(md5($password.date('d').$ipPart), 8, 16);

    if($auth==$authHash){
        return true;
    }else if(!$goto_login){
        return false;
    }

    $from=isset($_POST['from']) ? $_POST['from'] : $_SERVER['REQUEST_URI'];

    $isgae = defined('IS_GAE') && IS_GAE;
    if($isgae){
        $memcache = new Memcache;
    }else{
        $dir=dirname(__FILE__).'/data';
        if(!is_dir($dir)) $dir=dirname(__FILE__).'/temp';
        if(!is_dir($dir)) $dir=dirname(__FILE__);
        $recordFile=$dir.'/~login_record.dat';

        //删除一天前的文件，避免文件变得很大
        if(file_exists($recordFile) && time()-filemtime($recordFile)>86400) {
            unlink($recordFile);
        }
    }

    $records = null;
    if(isset($_POST['submit']) && isset($_POST['password'])){
        if($isgae){
            $records=$memcache->get('authentication');
        }elseif(file_exists($recordFile)){
            $records=unserialize(file_get_contents($recordFile));
        }
        if(!is_array($records)) {
            $records=array();
        }else{
            foreach($records as $k=>$v){
                if(time()-$v['time']>600) unset($records[$k]);
            }
        }
        $record=isset($records[$ipPart])?$records[$ipPart]:array('count'=>0,'time'=>0);
        if($record['count']>=3) {
            exit('spam');
        }

        if($_POST['password']==$password){
            setcookie($cookieName,$authHash,0,'/');
            header('Location: '.$from);
            $record['count']=0;
            $record['time']=time();
            $records[$ipPart]=$record;
            if($isgae){
                $memcache->set('authentication', $records, false, 600);
            }else{
                file_put_contents($recordFile, serialize($records));
            }
            exit;
        }else{
            $record['count']++;
            $record['time']=time();
            $records[$ipPart]=$record;
            if($isgae){
                $memcache->set('authentication', $records, false, 600);
            }else{
                file_put_contents($recordFile, serialize($records));
            }
            //echo '<div>password is wrong!</div>';
        }
    }

    echo '<form method="post" action="" target="_top">
			<input type="password" name="password" value=""/>
			<input type="submit" name="submit" value="login"/>
			<input type="hidden" name="from" value="'.$from.'">
		</form>';
    exit;
}

/**
 * 获取用户IP
 * @param int segment 返回前几个网段（1～4），默认返回完整ip
 * @return string
 */
function get_ip($segment=4){
    $realip = isset($_SERVER) ? $_SERVER["REMOTE_ADDR"] : getenv("REMOTE_ADDR");
    if($segment<=1){
        $arr = explode('.', $realip);
        return $arr[0];
    }elseif($segment<=3){
        $arr = array_slice(explode('.', $realip), 0, $segment);
        return implode('.', $arr);
    }else{
        return $realip;
    }
}

