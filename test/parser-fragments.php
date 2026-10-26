<?php
/** Read-only parser regressions. Run with wp eval-file test/parser-fragments.php. */
if (!class_exists('tp_parser')) {
    throw new RuntimeException('Load Transposh first.');
}
function transposh_test_parser_fragments($parser_class = 'tp_parser') {
    $render = static function($html, $language = 'fr', $edit = false, $class = null) use ($parser_class) {
        $target_class = $class ?: $parser_class;
        $parser = new $target_class();
        $parser->lang = $language;
        $parser->dir_rtl = $language === 'ar';
        $parser->is_edit_mode = $edit;
        $parser->is_auto_translate = false;
        $parser->allow_ad = false;
        $parser->fetch_translate_func = static function($phrase, $lang) {
            $dictionary = array('fr'=>array('Hello'=>'Bonjour','Example'=>'Exemple'), 'de'=>array('Hello'=>'Hallo'), 'ar'=>array('Hello'=>'مرحبا'));
            return isset($dictionary[$lang][$phrase]) ? array(0, $dictionary[$lang][$phrase]) : null;
        };
        $parser->url_rewrite_func = static function($url) { return $url; };
        $parser->split_url_func = static function($url) { return array(); };
        return (string) $parser->fix_html($html);
    };
    $checks = array();
    foreach (array('fr'=>'Bonjour','de'=>'Hallo','ar'=>'مرحبا') as $lang=>$word) {
        $html = $render('<html><head></head><body><p>Hello</p></body></html>', $lang);
        $checks['empty_head_translation_'.$lang] = strpos($html, $word) !== false;
        $checks['empty_head_stats_'.$lang] = substr_count($html, 'name="translation-stats"') === 1;
        $checks['explicit_language_'.$lang] = strpos($html, 'lang="'.$lang.'"') !== false;
        if ($lang === 'ar') $checks['rtl_direction'] = strpos($html, 'dir="rtl"') !== false;
    }
    $html = $render('<html><head></head><body></body></html>');
    $checks['empty_head_and_body'] = strpos($html, 'translation-stats') !== false;
    $html = $render('<html><head><title>Example</title></head><body></body></html>', 'fr', true);
    $checks['empty_body_editing_hidden_spans'] = strpos($html, 'Exemple') !== false && strpos($html, '<body>') !== false;
    $html = $render('<div><p>Hello</p></div>');
    $checks['headless_fragment_translation'] = strpos($html, 'Bonjour') !== false;
    $checks['headless_fragment_no_injected_document'] = strpos($html, '<head') === false && strpos($html, '<html') === false;
    $html = $render('<html><body><p>Hello</p></body></html>');
    $checks['document_without_head'] = strpos($html, 'Bonjour') !== false;
    $checks['empty_string'] = $render('') === '';
    $normal = '<html><head><meta charset="utf-8"><title>Example</title></head><body><p>Hello</p><script>const value="Hello";</script><style>.x{content:"Hello"}</style></body></html>';
    $html = $render($normal);
    $checks['normal_document_translation'] = strpos($html, 'Bonjour') !== false && strpos($html, 'Exemple') !== false;
    $checks['script_unchanged'] = strpos($html, 'const value="Hello";') !== false;
    $checks['style_unchanged'] = strpos($html, '.x{content:"Hello"}') !== false;
    if ($parser_class !== 'tp_parser') {
        $checks['normal_output_matches_installed_parser'] = $render($normal, 'fr', false, 'tp_parser') === $html;
    }
    return $checks;
}
$transposh_parser_fragment_results = transposh_test_parser_fragments();
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::line(wp_json_encode($transposh_parser_fragment_results));
    if (in_array(false, $transposh_parser_fragment_results, true)) WP_CLI::error('Parser fragment regression failed.');
    WP_CLI::success(count($transposh_parser_fragment_results).' parser assertions passed.');
}
return $transposh_parser_fragment_results;
