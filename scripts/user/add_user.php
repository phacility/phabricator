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

phutil_require_module('phutil', 'console');
phutil_require_module('phutil', 'future/exec');

if ($argc !== 5) {
  echo "usage: add_user.php <username> <email> <realname> <admin_user>\n";
  exit(1);
}

$username = $argv[1];
$email = $argv[2];
$realname = $argv[3];
$admin = $argv[4];

$admin = id(new PhabricatorUser())->loadOneWhere(
  'username = %s',
  $argv[4]);
if (!$admin) {
  throw new Exception(
    "Admin user must be the username of a valid Phabricator account, used ".
    "to send the new user a welcome email.");
}

$existing_user = id(new PhabricatorUser())->loadOneWhere(
  'username = %s',
  $username);
if ($existing_user) {
  throw new Exception(
    "There is already a user with the username '{$username}'!");
}

$existing_user = id(new PhabricatorUser())->loadOneWhere(
  'email = %s',
  $email);
if ($existing_user) {
  throw new Exception(
    "There is already a user with the email '{$email}'!");
}

$user = new PhabricatorUser();
$user->setUsername($username);
$user->setEmail($email);
$user->setRealname($realname);
$user->save();

$user->sendWelcomeEmail($admin);

echo "Created user '{$username}' (realname='{$realname}', email='{$email}').\n";
