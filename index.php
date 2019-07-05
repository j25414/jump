<?php

//=========================================================================
// 请修改以下内容
//=========================================================================

//在google app里所申请的Cloud Storage的名称（在普通PHP空间上，此设置无效）
define('CLOUD_STORAGE_BUCKET', 'phproxypro2.appspot.com');

//管理员密码
define('PASSWORD', '9987');


//=========================================================================
// 以下内容请勿修改
//=========================================================================

define('IS_GAE', isset($_SERVER['APPLICATION_ID']));
header("Content-type: text/html; charset=utf-8");

function readContent($readCache){
    $content = '';
    if(IS_GAE && $readCache){
        $memcache = new Memcache;
        $content = $memcache->get('index.html');
    }
    if(IS_GAE && !$content) $content = file_get_contents('gs://'.CLOUD_STORAGE_BUCKET.'/index.html');
    if(!$content) $content = file_get_contents('index.html');
    if(IS_GAE && $readCache) $memcache->set('index.html', $content);
    return $content;
}

/**
 * 加密字符串
 * @param string $str
 */
function str_encrypt($str)
{
    if(empty($str)) return '';

    $tbl = str_split('0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_-', 1);
    $ky=mt_rand(10,62);
    $cd=0;
    $bt=0;
    $ret=$tbl[$ky];

    $ascs=unpack("C*", $str);
    $i=count($ascs);
    while(true){
        if($bt>=6){
            $ret .= $tbl[$cd&0x3F];
            $cd>>=6;
            $bt-=6;
        }else{
            if($i>=1){
                $b = $ascs[$i--];
                $b^=($ky++%0x3F);
                $cd+=($b<<$bt);
                $bt+=8;
            }else{
                break;
            }
        }
    }

    if($cd>0) $ret .= $tbl[$cd];
    return $ret;
}


if(strpos($_SERVER['REQUEST_URI'],'/admin.php')===false){
    //error_reporting(0);
    $s = readContent(true);
    $s = mb_convert_encoding($s, 'html-entities', 'utf-8');
    $s = str_encrypt($s);
    echo '<script type="text/javascript">var s="'.$s.'",'.
        'doc=document,'.
        'd=function($str){if(!$str)return"";$tbl="0123456789abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ_-";$ky=$tbl.indexOf($str.charAt(0));if($ky===false)return false;$ret="";$cd=0;$bt=0;for($i=1;$i<$str.length;$i++){$x=$tbl.indexOf($str.charAt($i));if($x===false)return false;$cd+=($x<<$bt);$bt+=6;if($bt>8){$b=$cd&0xFF;$bt-=8;$cd>>=8;$b^=(($ky++)%0x3F);$ret=String.fromCharCode($b)+$ret}}if($bt>0&&$cd)$ret=String.fromCharCode($cd^($ky%0x3F))+$ret;return $ret};'.
        'doc.write(d(s));</script>';
}
