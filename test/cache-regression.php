<?php
/** Run with wp eval-file test/cache-regression.php. Requires the plugin and FR/DE enabled. */
if (!defined('ABSPATH') || !class_exists('transposh_database')) { throw new RuntimeException('Load WordPress and Transposh first.'); }
function transposh_test_dictionary_tests() {
    $reflection=new ReflectionClass('transposh_database');$db=$reflection->newInstanceWithoutConstructor();
    $client=new class {
     public $values=array();public $keys=array();public $flushes=0;public $ttl=null;public $gets=0;
     function set($key,$value,$ttl){$this->values[$key]=$value;$this->keys[]=$key;$this->ttl=$ttl;return true;}
     function get($key){$this->gets++;return $this->values[$key]??false;}
     function delete($key){unset($this->values[$key]);return true;}
     function flush(){$this->flushes++;return false;}
    };
    foreach(array('memcached'=>$client,'memcache_working'=>true,'transposh'=>$GLOBALS['my_transposh_plugin'],'dictionary_namespace'=>'isolated-mock-a','dictionary_epoch'=>'mock-epoch','translations'=>array('fr'=>array('test phrase'=>array(0,'old'),'Shared phrase'=>array(0,'FR CANARY'),'Missing phrase'=>null),'de'=>array('Shared phrase'=>array(0,'DE CANARY')))) as $name=>$value){$p=$reflection->getProperty($name);$p->setAccessible(true);$p->setValue($db,$value);}
    $q0=$GLOBALS['wpdb']->num_queries;$g0=$client->gets;$missing=$db->fetch_translation('Missing phrase','fr');$negative=$missing===null&&$q0===$GLOBALS['wpdb']->num_queries&&$g0===$client->gets;
    $db->cache_delete('Missing phrase','fr');$p=$reflection->getProperty('translations');$p->setAccessible(true);$tmp=$p->getValue($db);$negative_deleted=!array_key_exists('Missing phrase',$tmp['fr']);
    $fr=$db->fetch_translation('Shared phrase','fr');$de=$db->fetch_translation('Shared phrase','de');$prefetch_languages=$fr[1]==='FR CANARY'&&$de[1]==='DE CANARY';
    $db->cache_store('test phrase','fr',array(0,'CANARY'),3600);$roundtrip=$db->cache_fetch('test phrase','fr')===array('0','CANARY');
    $db->cache_store('test_phrase','fr',array(0,'UNDERSCORE'),3600);
    $distinct=$db->cache_fetch('test phrase','fr')!==$db->cache_fetch('test_phrase','fr');
    $p=$reflection->getProperty('dictionary_namespace');$p->setAccessible(true);$p->setValue($db,'isolated-mock-b');$other_site=$db->cache_fetch('test phrase','fr')===false;$p->setValue($db,'isolated-mock-a');
    $p=$reflection->getProperty('dictionary_epoch');$p->setAccessible(true);$p->setValue($db,'next-mock-epoch');$epoch=$db->cache_fetch('test phrase','fr')===false;$p->setValue($db,'mock-epoch');
    $db->cache_delete('test phrase','fr');$deleted=$db->cache_fetch('test phrase','fr')===false;
    $p=$reflection->getProperty('translations');$p->setAccessible(true);$local=$p->getValue($db);
    $db->cache_store('ttl','fr',array(0,'TTL'),0);
    return array('roundtrip'=>$roundtrip,'spaces_distinct_from_underscores'=>$distinct,'site_isolation'=>$other_site,'epoch_isolation'=>$epoch,'delete'=>$deleted,'prefetch_invalidated'=>!isset($local['fr']['test phrase']),'prefetch_languages_separate'=>$prefetch_languages,'request_negative_memoized'=>$negative,'negative_memo_invalidated'=>$negative_deleted,'bounded_ttl'=>$client->ttl===86400,'no_backend_flush'=>$client->flushes===0,'namespaced_keys'=>count(array_filter($client->keys,static function($key){return strpos($key,'transposh:v2:')===0;}))===count($client->keys));
   }

function transposh_test_prefetch_tests() {
    $actual=$GLOBALS['wpdb'];$tp=$GLOBALS['my_transposh_plugin'];
    $proxy=new class($actual){
     private $actual;public $last_error='';public $num_queries=0;public $batch_queries=0;public $point_queries=0;public $fail=false;
     function __construct($actual){$this->actual=$actual;}
     function __call($name,$args){return $this->actual->$name(...$args);}
     function get_results($query,$type){$this->batch_queries++;$this->num_queries++;if($this->fail){$this->last_error='synthetic SQL failure';return array();}$this->last_error='';$suffix=strpos($query,"lang = 'de'")!==false?'DE':'FR';return array(array('original'=>'Features','translated'=>'UNIT '.$suffix,'source'=>'0'),array('original'=>'A &amp; B 2026','translated'=>'AMP '.$suffix,'source'=>'0'),array('original'=>'A &#039; B 2026','translated'=>'SINGLE '.$suffix,'source'=>'0'),array('original'=>'A &quot; B 2026','translated'=>'DOUBLE '.$suffix,'source'=>'0'),array('original'=>'12','translated'=>'NUMBER '.$suffix,'source'=>'0'));}
     function get_row($query){$this->point_queries++;$this->num_queries++;$this->last_error=$this->fail?"synthetic SQL failure":"";return null;}
    };
    $client=new class {public $values=array();public $chunks=array();public $gets=0;public $fail=false;function get($key){$this->gets++;return $this->values[$key]??false;}function getMulti($keys){$this->chunks[]=count($keys);return $this->fail?false:array_intersect_key($this->values,array_fill_keys($keys,true));}function set($key,$value,$ttl){$this->values[$key]=$value;return true;}function delete($key){unset($this->values[$key]);return true;}};
    $ref=new ReflectionClass('transposh_database');$db=$ref->newInstanceWithoutConstructor();
    foreach(array('transposh'=>$tp,'translation_table'=>'oi_unit_translations','memcache_working'=>true,'memcached'=>$client,'dictionary_namespace'=>'unit-prefetch','dictionary_epoch'=>'unit','translations'=>array()) as $name=>$value){$p=$ref->getProperty($name);$p->setAccessible(true);$p->setValue($db,$value);}
    $p=$ref->getProperty('translations');$p->setAccessible(true);$out=array();
    try{
     $GLOBALS['wpdb']=$proxy;
     $phrases=array('Features','A & B 2026',"A ' B 2026",'A " B 2026','12','Absent unit phrase');
     $db->prefetch_translations(array_fill_keys($phrases,true),'fr');
     foreach(array('Features'=>'UNIT FR','A & B 2026'=>'AMP FR',"A ' B 2026"=>'SINGLE FR','A " B 2026'=>'DOUBLE FR','12'=>'NUMBER FR') as $phrase=>$expected){$value=$db->fetch_translation($phrase,'fr');$out['keyset_'.$expected]=is_array($value)&&$value[1]===$expected;}
     for($i=0;$i<3;$i++){$out['negative_miss']=$db->fetch_translation('Absent unit phrase','fr')===null;}
     $out['one_sql_batch_no_point_queries']=$proxy->batch_queries===1&&$proxy->point_queries===0;
     $db->prefetch_translations($phrases,'de');$value=$db->fetch_translation('Features','de');$out['list_format_and_languages']=$value[1]==='UNIT DE'&&$db->fetch_translation('Features','fr')[1]==='UNIT FR';
     $old=$proxy->batch_queries;$db->prefetch_translations($phrases,'fr');$out['repeated_prefetch_reuses_request_memory']=$old===$proxy->batch_queries;
     $many=array();for($i=0;$i<600;$i++){$phrase='unit-multi-'.$i;$many[]=$phrase;$db->cache_store($phrase,'fr',array(0,'VALUE '.$i),60);}
     $client->chunks=array();$hits=$db->cache_fetch_many($many,'fr');$out['bounded_multi_get']=count($hits)===600&&$client->chunks===array(250,250,100)&&$hits['unit-multi-599'][1]==='VALUE 599';
     $client->fail=true;$out['multi_get_failure_is_miss']=$db->cache_fetch_many(array('Features'),'fr')===array();$client->fail=false;
     $p->setValue($db,array());$client->values=array();$proxy->fail=true;$db->prefetch_translations(array('Features'),'fr');$out['sql_failure_not_memoized']=$p->getValue($db)===array();$proxy->fail=true;$out['point_sql_failure_not_memoized']=$db->fetch_translation('Point failure','fr')===false&&$db->fetch_translation('Point failure','fr')===false&&$p->getValue($db)===array()&&$proxy->point_queries===2;$proxy->fail=false;
    }finally{$GLOBALS['wpdb']=$actual;}
    return $out;
   }

$transposh_cache_test_results = array_merge(transposh_test_dictionary_tests(), transposh_test_prefetch_tests());
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::line(wp_json_encode($transposh_cache_test_results));
    if (in_array(false, $transposh_cache_test_results, true)) { WP_CLI::error('Cache regression failed.'); }
    WP_CLI::success(count($transposh_cache_test_results) . ' cache assertions passed.');
}
return $transposh_cache_test_results;
