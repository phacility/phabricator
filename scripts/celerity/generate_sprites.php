#!/usr/bin/env php
<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

require_once dirname(dirname(__FILE__)).'/__init_script__.php';

$args = new PhutilArgumentParser($argv);
$args->setTagline('regenerate CSS sprite sheets');
$args->setSynopsis(<<<EOHELP
**sprites**
    Rebuild CSS sprite sheets.

EOHELP
);
$args->parseStandardArguments();
$args->parse(
  array(
    array(
      'name'  => 'source',
      'param' => 'directory',
      'help'  => 'Directory with sprite sources.',
    )
  ));

$srcroot = $args->getArg('source');
if (!$srcroot) {
  throw new Exception(
    "You must specify a source directory with '--source'.");
}

$webroot = dirname(phutil_get_library_root('phabricator')).'/webroot/rsrc';
$webroot = Filesystem::readablePath($webroot);

function glx($x) {
  return (60 + (48 * $x));
}

function gly($y) {
  return (110 + (48 * $y));
}

$sheet = new PhutilSpriteSheet();
$at = '@';
$sheet->setCSSHeader(<<<EOCSS
/**
 * @provides autosprite-css
 * {$at}generated
 */

.autosprite {
  background-image: url(/rsrc/image/autosprite.png);
  background-repeat: no-repeat;
}
EOCSS
);

$menu_normal_template = id(new PhutilSprite())
  ->setSourceFile($srcroot.'/menu_normal_1x.png')
  ->setSourceSize(26, 26);

$menu_hover_template = id(new PhutilSprite())
  ->setSourceFile($srcroot.'/menu_hover_1x.png')
  ->setSourceSize(26, 26);

$menu_selected_template = id(new PhutilSprite())
  ->setSourceFile($srcroot.'/menu_selected_1x.png')
  ->setSourceSize(26, 26);

$menu_map = array(
  ''          => $menu_normal_template,
  '-selected' => $menu_selected_template,
  ':hover'    => $menu_hover_template,
);

$icon_map = array(
  'help'          => array(4, 19),
  'settings'      => array(0, 28),
  'logout'        => array(3, 6),
  'notifications' => array(5, 20),
  'task'          => array(1, 15),
);

foreach ($icon_map as $icon => $coords) {
  list($x, $y) = $coords;
  foreach ($menu_map as $suffix => $template) {
    $sheet->addSprite(
      id(clone $template)
        ->setSourcePosition(glx($x), gly($y))
        ->setTargetCSS('.main-menu-item-icon-'.$icon.$suffix));
  }
}

$app_template_large = id(new PhutilSprite())
  ->setSourceFile($srcroot.'/application_large_1x.png')
  ->setSourceSize(60, 60);

$app_template_large_hover = id(new PhutilSprite())
  ->setSourceFile($srcroot.'/application_large_hover_1x.png')
  ->setSourceSize(60, 60);

$app_template_small = id(new PhutilSprite())
  ->setSourceFile($srcroot.'/menu_normal_1x.png')
  ->setSourceSize(30, 30);

$app_template_small_hover = id(new PhutilSprite())
  ->setSourceFile($srcroot.'/menu_hover_1x.png')
  ->setSourceSize(30, 30);

$app_template_small_selected = id(new PhutilSprite())
  ->setSourceFile($srcroot.'/menu_selected_1x.png')
  ->setSourceSize(30, 30);

$app_source_map = array(
  '-large'          => array($app_template_large, 2),

  // For the application launch view, we only show hover state on the desktop
  // because it looks glitchy on touch devices. We show the hover state when
  // the surrounding <a> is hovered, not the icon itself.
  '-large /* hover */'    => array(
    $app_template_large_hover,
    2,
    '.device-desktop .phabricator-application-launch-container:hover '),

  ''                => array($app_template_small, 1),

  // Show hover state only for the desktop.
  ':hover'          => array(
    $app_template_small_hover,
    1,
    '.device-desktop  ',
  ),
  '-selected'       => array($app_template_small_selected, 1),
);

$app_map = array(
  'differential'    => array(9, 1),
  'fact'            => array(2, 4),
  'mail'            => array(0, 1),
  'diffusion'       => array(7, 13),
  'slowvote'        => array(1, 4),
  'phriction'       => array(1, 7),
  'maniphest'       => array(3, 24),
  'flags'           => array(6, 26),
  'settings'        => array(9, 11),
  'applications'    => array(0, 34),
  'default'         => array(9, 9),
  'people'          => array(3, 0),
  'ponder'          => array(4, 35),
  'calendar'        => array(5, 4),
  'files'           => array(6, 3),
  'projects'        => array(7, 35),
  'daemons'         => array(7, 6),
  'herald'          => array(1, 5),
  'countdown'       => array(7, 5),
  'conduit'         => array(7, 30),
  'feed'            => array(3, 11),
  'paste'           => array(9, 2),
  'audit'           => array(8, 19),
  'uiexample'       => array(7, 28),
  'phpast'          => array(6, 31),
  'owners'          => array(5, 32),
  'phid'            => array(9, 25),
  'diviner'         => array(1, 35),
  'repositories'    => array(8, 13),
  'phame'           => array(8, 4),
  'macro'           => array(0, 31),
  'releeph'         => array(5, 18),
  'drydock'         => array(5, 25),
);

$xadj = -1;
foreach ($app_map as $icon => $coords) {
  list($x, $y) = $coords;
  foreach ($app_source_map as $suffix => $spec) {
    list($template, $scale) = $spec;
    if (isset($spec[2])) {
      $prefix = $spec[2];
    } else {
      $prefix = '';
    }
    $sheet->addSprite(
      id(clone $template)
        ->setSourcePosition(($xadj + glx($x)) * $scale, gly($y) * $scale)
        ->setTargetCSS($prefix.'.app-'.$icon.$suffix));
  }
}

$action_template = id(new PhutilSprite())
  ->setSourcePosition(0, 0)
  ->setSourceSize(16, 16);

$action_icons = PhabricatorActionView::getAvailableIcons();
foreach ($action_icons as $icon) {
  $action_map[$icon] = 'icon/'.$icon.'.png';
}

foreach ($action_map as $icon => $source) {
  $sheet->addSprite(
    id(clone $action_template)
      ->setSourceFile($srcroot.$source)
      ->setTargetCSS('.action-'.$icon));
}


$remarkup_template = id(new PhutilSprite())
  ->setSourcePosition(0, 0)
  ->setSourceSize(14, 14);

$remarkup_icons = array(
  'b',
  'code',
  'i',
  'image',
  'ol',
  'tag',
  'tt',
  'ul',
  'help',
  'table',
);

foreach ($remarkup_icons as $icon) {
  $sheet->addSprite(
    id(clone $remarkup_template)
      ->setSourceFile($srcroot.'remarkup/text_'.$icon.'.png')
      ->setTargetCSS('.remarkup-assist-'.$icon));
}


$sheet->generateImage($webroot.'/image/autosprite.png');
$sheet->generateCSS($webroot.'/css/autosprite.css');

echo "Done.\n";
