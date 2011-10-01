#!/usr/bin/env php
<?php

/*
 * Copyright 2011 Facebook, Inc.
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

