#!/usr/bin/env php
<?php

require_once dirname(dirname(__FILE__)).'/__init_script__.php';

$args = new PhutilArgumentParser($argv);
$args->setTagline(pht('regenerate CSS sprite sheets'));
$args->setSynopsis(<<<EOHELP
**sprites**
    Rebuild CSS sprite sheets.

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
$webroot = $root.'/webroot/rsrc';
$webroot = Filesystem::readablePath($webroot);

$generator = new CeleritySpriteGenerator();

$sheets = array(
  'menu' => $generator->buildMenuSheet(),
  'tokens' => $generator->buildTokenSheet(),
  'main-header' => $generator->buildMainHeaderSheet(),
  'login' => $generator->buildLoginSheet(),
  'projects' => $generator->buildProjectsSheet(),
);

list($err) = exec_manual('optipng');
if ($err) {
  $have_optipng = false;
  echo phutil_console_format(
    "<bg:red> %s </bg> %s\n%s\n",
    pht('WARNING'),
    pht('`%s` not found in PATH.', 'optipng'),
    pht('Sprites will not be optimized! Install `%s`!', 'optipng'));
} else {
  $have_optipng = true;
}

foreach ($sheets as $name => $sheet) {

  $sheet->setBasePath($root);

  $manifest_path = $root.'/resources/sprite/manifest/'.$name.'.json';
  if (!$args->getArg('force')) {
    if (Filesystem::pathExists($manifest_path)) {
      $data = Filesystem::readFile($manifest_path);
      $data = phutil_json_decode($data);
      if (!$sheet->needsRegeneration($data)) {
        continue;
      }
    }
  }

  $sheet
    ->generateCSS($webroot."/css/sprite-{$name}.css")
    ->generateManifest($root."/resources/sprite/manifest/{$name}.json");

  foreach ($sheet->getScales() as $scale) {
    if ($scale == 1) {
      $sheet_name = "sprite-{$name}.png";
    } else {
      $sheet_name = "sprite-{$name}-X{$scale}.png";
    }

    $full_path = "{$webroot}/image/{$sheet_name}";
    $sheet->generateImage($full_path, $scale);

    if ($have_optipng) {
      echo pht('Optimizing...')."\n";
      phutil_passthru('optipng -o7 -clobber %s', $full_path);
    }
  }
}

echo pht('Done.')."\n";
