#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

echo "Examining users.\n";
foreach (new LiskMigrationIterator(new PhabricatorUser()) as $user) {
  $file = id(new PhabricatorFile())
    ->loadOneWhere('phid = %s', $user->getProfileImagePHID());

  if (!$file) {
    echo 'No pic for user ', $user->getUserName(), "\n";
    continue;
  }

  $data = $file->loadFileData();
  $img  = imagecreatefromstring($data);
  $sx   = imagesx($img);
  $sy   = imagesy($img);

  if ($sx != 50 || $sy != 50) {
    echo 'Found one! User ', $user->getUserName(), "\n";
    $xformer = new PhabricatorImageTransformer();

    // Resize OAuth image to a reasonable size
    $small_xformed = $xformer->executeProfileTransform(
      $file,
      $width = 50,
      $min_height = 50,
      $max_height = 50);

    $user->setProfileImagePHID($small_xformed->getPHID());
    $user->save();
    break;
  } else {
    echo '.';
  }
}
echo "\n";
echo "Done.\n";
