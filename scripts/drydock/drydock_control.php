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

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

$args = new PhutilArgumentParser($argv);
$args->parseStandardArguments();
$args->parse(array());

$allocator = new DrydockAllocator();
$allocator->setResourceType('webroot');
$lease = $allocator->allocate();

$lease->waitUntilActive();


$cmd = $lease->getInterface('webroot');
echo "URI: ".$cmd->getURI()."\n";

$lease->release();

die("Done.\n");

$i_file = $lease->getInterface('command');

list($stdout) = $i_file->execx('ls / ; echo -- ; uptime ; echo -- ; uname -n');
echo $stdout;


$lease->release();


// $i_http = $lease->getInterface('httpd');
// echo $i_http->getURI('/index.html')."\n";
