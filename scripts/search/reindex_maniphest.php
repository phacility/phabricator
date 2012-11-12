#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

ini_set('memory_limit', -1);
$tasks = id(new ManiphestTask())->loadAll();
echo "Updating relationships for ".count($tasks)." tasks";
foreach ($tasks as $task) {
  ManiphestTaskProject::updateTaskProjects($task);
  ManiphestTaskSubscriber::updateTaskSubscribers($task);
  echo '.';
}
echo "\nDone.\n";

