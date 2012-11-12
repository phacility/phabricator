#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

$users = id(new PhabricatorUser())->loadAll();
echo "Indexing ".count($users)." users";
foreach ($users as $user) {
  PhabricatorSearchUserIndexer::indexUser($user);
  echo '.';
}
echo "\n";
echo "Done.\n";

