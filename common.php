<?php
function norm($t){ return trim(preg_replace('/\s+/',' ',preg_replace("/[^a-z0-9'\s]/u",' ',mb_strtolower($t)))); }
function load_json($path,$default){ $d=json_decode(@file_get_contents($path),true); return is_array($d)?$d:$default; }
function save_json($path,$data){
  $fp=fopen($path,'c+'); if(!$fp) return false;
  flock($fp,LOCK_EX); ftruncate($fp,0);
  fwrite($fp,json_encode($data,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
  fflush($fp); flock($fp,LOCK_UN); fclose($fp); return true;
}
function score_entry($entry,$t,$tokens){
  $s=0;
  foreach(($entry['keywords']??[]) as $k){ $k=norm($k); if($k==='') continue;
    if(strpos($k,' ')!==false){ if(strpos(" $t "," $k ")!==false) $s+=4; continue; }
    foreach($tokens as $w){
      if($w===$k){ $s+=3; break; }
      $len=strlen($k);
      if($len>=5 && abs(strlen($w)-$len)<=2 && levenshtein($w,$k)<=($len>=8?2:1)){ $s+=2; break; }
    }
  }
  return $s;
}
