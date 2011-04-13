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
require_once $root.'/scripts/__init_env__.php';

if ($argc < 4) {
  echo "usage: create_user.php <user_name> <real_name> <email> [--agent]\n";
  die(1);
}

$username = $argv[1];
$realname = $argv[2];
$email    = $argv[3];
$user = id(new PhabricatorUser())->loadOneWhere(
  'userName = %s',
  $username);
if ($user) {
  echo "User already exists!\n";
  die(1);
}

$user = new PhabricatorUser();
$user->setUserName($username);
$user->setRealName($realname);
$user->setEmail($email);
if (isset($argv[4]) && $argv[4] == '--agent') {
  $user->setIsSystemAgent(true);
}
$user->save();

echo "Created user.\n";
