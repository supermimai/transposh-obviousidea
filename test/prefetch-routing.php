<?php
/** Read-only rendering fixture. Its dictionary contains no live translation data. */
class transposh_prefetch_options_fixture {
    public $enable_url_translate = false;
    public $enable_default_translate = false;
    public $enable_autotranslate = false;
    public $parser_dont_break_puncts = false;
    public $parser_dont_break_numbers = false;
    public $parser_dont_break_entities = false;
    public $widget_remove_logo = true;
    function is_default_language($lang) { return $lang === 'en'; }
    function is_active_language($lang) { return in_array($lang, array('en','fr','de'), true); }
}
class transposh_prefetch_dictionary_fixture {
    public $keys = array();
    function prefetch_translations($phrases, $lang) { $this->keys = array_keys($phrases); }
    function fetch_translation($phrase, $lang) {
        $map = array('fr'=>array('Hello'=>'Bonjour','Example'=>'Exemple'),'de'=>array('Hello'=>'Hallo','Example'=>'Beispiel'));
        return isset($map[$lang][$phrase]) ? array(0,$map[$lang][$phrase]) : null;
    }
}
class transposh_prefetch_fixture extends transposh_plugin {
    function is_special_page($url) { return false; }
}
function transposh_test_prefetch_routing($candidate_class = 'transposh_prefetch_fixture') {
    $checks = array(); $observations = array();
    $html = '<html><head><title>Example</title></head><body><p>Hello</p>';
    for ($i=0;$i<20;$i++) $html .= '<a href="'.home_url('/windows-software/item-'.$i.'/').'">Hello</a>';
    $html .= '</body></html>';
    $hook = $GLOBALS['wp_filter']['transposh_page_processed'] ?? null;
    unset($GLOBALS['wp_filter']['transposh_page_processed']);
    try {
        foreach (array('en','fr','de') as $lang) foreach (array(false,true) as $urls) {
            $renders = array(); $keys = array();
            foreach (array('installed'=>'transposh_prefetch_fixture','candidate'=>$candidate_class) as $label=>$class) {
                $tp = (new ReflectionClass($class))->newInstanceWithoutConstructor();
                $tp->options = new transposh_prefetch_options_fixture(); $tp->options->enable_url_translate = $urls;
                $tp->database = new transposh_prefetch_dictionary_fixture();
                $tp->home_url = home_url(); $tp->enable_permalinks_rewrite = true;
                $tp->target_language = $lang; $tp->edit_mode = false;
                $renders[$label] = (string)$tp->process_page($html);
                $keys[$label] = $tp->database->keys;
            }
            $name = $lang.($urls ? '_translated_urls' : '_original_urls');
            $checks[$name.'_identical_html'] = $renders['installed'] === $renders['candidate'];
            $checks[$name.'_prefetch_scope'] = $lang === 'en' ? count($keys['candidate'])===0 : ($urls ? $keys['candidate']===$keys['installed'] : count($keys['candidate'])===2);
            $observations[$name] = array('installed_prefetch_keys'=>count($keys['installed']),'candidate_prefetch_keys'=>count($keys['candidate']),'output_sha256'=>hash('sha256',$renders['candidate']));
        }
        return array('checks'=>$checks,'observations'=>$observations);
    } finally {
        unset($GLOBALS['wp_filter']['transposh_page_processed']);
        if ($hook !== null) $GLOBALS['wp_filter']['transposh_page_processed'] = $hook;
    }
}
if (defined('WP_CLI') && WP_CLI) {
    $result = transposh_test_prefetch_routing();
    WP_CLI::line(wp_json_encode($result));
    if (in_array(false,$result['checks'],true)) WP_CLI::error('Prefetch routing regression failed.');
    WP_CLI::success(count($result['checks']).' prefetch routing assertions passed.');
}
