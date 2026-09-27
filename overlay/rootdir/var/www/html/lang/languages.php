<?php
//
// Interface languages shown in the language menu, in menu order.
//
// To add a language:
//   1. Add an entry below: the key is the language tag used for the cookie,
//      the <html lang> attribute and the file name.
//   2. Copy lang/ko.php to lang/<tag>.php and translate the values. Keys
//      are the English text in the code; missing entries fall back to English.
//      Keep %s and %1$s placeholders; numbered ones may change order.
//   3. Add a flag as images/flags/<flag>.svg (branding/src/flags.py).
//   4. Run tools/i18n-check.py to find missing or broken entries.
//
// New interface text: wrap it in t('...') in PHP or BT.t('...') in
// JavaScript, then add the translation to every lang/<tag>.php.
//
return array(
	'en'    => array('name' => 'English',   'english' => 'English',              'flag' => 'us'),
	'ko'    => array('name' => '한국어',     'english' => 'Korean',               'flag' => 'kr'),
	'ja'    => array('name' => '日本語',     'english' => 'Japanese',             'flag' => 'jp'),
	'zh-CN' => array('name' => '简体中文',   'english' => 'Chinese (Simplified)', 'flag' => 'cn'),
	'es'    => array('name' => 'Español',   'english' => 'Spanish',              'flag' => 'es'),
	'de'    => array('name' => 'Deutsch',   'english' => 'German',               'flag' => 'de'),
	'fr'    => array('name' => 'Français',  'english' => 'French',               'flag' => 'fr'),
	'pt-BR' => array('name' => 'Português', 'english' => 'Portuguese (Brazil)',  'flag' => 'br'),
);
