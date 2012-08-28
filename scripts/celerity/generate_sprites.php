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
$sheet->setCSSHeader(<<<EOCSS
/**
 * @provides autosprite-css
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

$app_template_full = id(new PhutilSprite())
  ->setSourceFile($srcroot.'/application_normal_2x.png')
  ->setSourceSize(60, 60);

$app_template_mini = id(new PhutilSprite())
  ->setSourceFile($srcroot.'/menu_normal_1x.png')
  ->setSourceSize(30, 30);

$app_source_map = array(
  '-full' => array($app_template_full, 2),
  ''      => array($app_template_mini, 1),
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
);

$xadj = -1;
foreach ($app_map as $icon => $coords) {
  list($x, $y) = $coords;
  foreach ($app_source_map as $suffix => $spec) {
    list($template, $scale) = $spec;
    $sheet->addSprite(
      id(clone $template)
        ->setSourcePosition(($xadj + glx($x)) * $scale, gly($y) * $scale)
        ->setTargetCSS('.app-'.$icon.$suffix));
  }
}

$action_template = id(new PhutilSprite())
  ->setSourcePosition(0, 0)
  ->setSourceSize(16, 16);

$action_map = array(
  'file'        => 'icon/page_white_text.png',
  'fork'        => 'icon/arrow_branch.png',
  'edit'        => 'icon/page_white_edit.png',
  'flag-0'      => 'icon/flag-0.png',
  'flag-1'      => 'icon/flag-1.png',
  'flag-2'      => 'icon/flag-2.png',
  'flag-3'      => 'icon/flag-3.png',
  'flag-4'      => 'icon/flag-4.png',
  'flag-5'      => 'icon/flag-5.png',
  'flag-6'      => 'icon/flag-6.png',
  'flag-7'      => 'icon/flag-7.png',
  'flag-ghost'  => 'icon/flag-ghost.png',
);

foreach ($action_map as $icon => $source) {
  $sheet->addSprite(
    id(clone $action_template)
      ->setSourceFile($srcroot.$source)
      ->setTargetCSS('.action-'.$icon));
}

$sheet->generateImage($webroot.'/image/autosprite.png');
$sheet->generateCSS($webroot.'/css/autosprite.css');

echo "Done.\n";
