#!/usr/bin/env php
<?php

require_once dirname(dirname(__FILE__)).'/__init_script__.php';

$args = new PhutilArgumentParser($argv);
$args->setTagline(pht('regenerate Emoji data sheets'));
$args->setSynopsis(<<<EOHELP
**emoji**
    Rebuild Emoji data sheets.

EOHELP
);
$args->parseStandardArguments();
$args->parse(
  array(
    array(
      'name'  => 'force',
      'help'  => pht('Force regeneration even if sources have not changed.'),
    ),
  ));

$root = dirname(phutil_get_library_root('phabricator'));
// move this to an argument?
$path = $root.'/emoji_strategy.json';
$export_path = $root.'/resources/emoji/manifest.json';

if (Filesystem::pathExists($path)) {
  $json = Filesystem::readFile($path);

  $emojis = phutil_json_decode($json);
  $data = array();
  foreach ($emojis as $shortname => $emoji) {
    $unicode = $emoji['unicode'];
    $codes = explode('-', $unicode);
    $hex = '';
    foreach ($codes as $code) {
      $hex .= phutil_utf8_encode_codepoint(hexdec($code));
    }
    $data[$shortname] = $hex;
  }

  ksort($data);
  $json = new PhutilJSON();
  $data = $json->encodeFormatted($data);
  Filesystem::writeFile($export_path, $data);
  echo pht('Done.')."\n";
} else {
  echo pht('Path %s not exist.', $path)."\n";
}
