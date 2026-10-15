<?php
/** Read-only compatibility checks. Run with wp eval-file after loading Transposh. */
if (!defined('ABSPATH') || !class_exists('transposh_translate')) {
    throw new RuntimeException('Load WordPress and Transposh first.');
}
$checks = array();
$class = new ReflectionClass('transposh_translate');
$protect = $class->getMethod('protectTermsInText');
$restore = $class->getMethod('restoreProtectedTerms');
$parse = $class->getMethod('parseOllamaResponse');
foreach (array($protect, $restore, $parse) as $method) { $method->setAccessible(true); }
list($text, $map) = $protect->invoke(null, 'Light Image Resizer & ObviousIdea', array('Light Image Resizer', 'ObviousIdea'));
$checks['protected_terms_roundtrip'] = $restore->invoke(null, $text, $map) === 'Light Image Resizer & ObviousIdea';
$checks['protected_terms_batch'] = $restore->invoke(null, array($text, 'plain'), $map) === array('Light Image Resizer & ObviousIdea', 'plain');
$checks['protected_terms_failure'] = $restore->invoke(null, false, $map) === false;
foreach (array(
    'custom' => array('translation' => 'Bonjour'),
    'native_generate' => array('response' => 'Bonjour'),
    'native_chat' => array('message' => array('content' => 'Bonjour')),
    'openai_chat' => array('choices' => array(array('message' => array('content' => 'Bonjour')))),
    'openai_completion' => array('choices' => array(array('text' => 'Bonjour')))
) as $kind => $payload) {
    $checks['ollama_' . $kind] = $parse->invoke(null, wp_json_encode($payload)) === 'Bonjour';
}
$checks['ollama_invalid_json'] = $parse->invoke(null, 'not JSON') === false;
$checks['ollama_error_response'] = $parse->invoke(null, '{"error":"unavailable"}') === false;
$checks['ollama_registered'] = transposh_consts::is_supported_engine('fr', 'o') && transposh_consts::get_engine_lang_code('de', 'o') === 'de';
if (defined('WP_CLI') && WP_CLI) {
    WP_CLI::line(wp_json_encode($checks));
    if (in_array(false, $checks, true)) { WP_CLI::error('Translation compatibility regression failed.'); }
    WP_CLI::success(count($checks) . ' translation assertions passed.');
}
return $checks;
