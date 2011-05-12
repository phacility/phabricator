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

phutil_require_module('phutil', 'console');

echo "Enter a username to create a new account or edit an existing account.";

$username = phutil_console_prompt("Enter a username:");
if (!strlen($username)) {
  echo "Cancelled.\n";
  exit(1);
}

$user = id(new PhabricatorUser())->loadOneWhere(
  'username = %s',
  $username);

if (!$user) {
  echo "There is no existing user account '{$username}'.\n";
  $ok = phutil_console_confirm(
    "Do you want to create a new '{$username}' account?",
    $default_no = false);
  if (!$ok) {
    echo "Cancelled.\n";
    exit(1);
  }
  $user = new PhabricatorUser();
  $user->setUsername($username);
} else {
  echo "There is an existing user account '{$username}'.\n";
  $ok = phutil_console_confirm(
    "Do you want to edit the existing '{$username}' account?",
    $default_no = false);
  if (!$ok) {
    echo "Cancelled.\n";
    exit(1);
  }
}

$original = clone $user;

$user_realname = $user->getRealName();
if (strlen($user_realname)) {
  $realname_prompt = ' ['.$user_realname.']';
} else {
  $realname_prompt = '';
}
$realname = nonempty(
  phutil_console_prompt("Enter user real name{$realname_prompt}:"),
  $user_realname);
$user->setRealName($realname);

$user_email = $user->getEmail();
if (strlen($user_email)) {
  $email_prompt = ' ['.$user_email.']';
} else {
  $email_prompt = '';
}
$email = nonempty(
  phutil_console_prompt("Enter user email address{$email_prompt}:"),
  $user_email);
$user->setEmail($email);

$changed_pass = false;
$password = phutil_console_prompt(
  "Enter a password for this user [blank to leave unchanged]:");
if (strlen($password)) {
  $user->setPassword($password);
  $changed_pass = true;
}

$is_admin = $user->getIsAdmin();
$set_admin = phutil_console_confirm(
  'Should this user be an administrator?',
  $default_no = !$is_admin);
$user->setIsAdmin($set_admin);

echo "\n\nACCOUNT SUMMARY\n\n";
$tpl = "%12s   %-30s   %-30s\n";
printf($tpl, null, 'OLD VALUE', 'NEW VALUE');
printf($tpl, 'Username', $original->getUsername(), $user->getUsername());
printf($tpl, 'Real Name', $original->getRealName(), $user->getRealName());
printf($tpl, 'Email', $original->getEmail(), $user->getEmail());
printf($tpl, 'Password', null, $changed_pass ? 'Updated' : 'Unchanged');

printf(
  $tpl,
  'Admin',
  $original->getIsAdmin() ? 'Y' : 'N',
  $user->getIsAdmin() ? 'Y' : 'N');

echo "\n";

if (!phutil_console_confirm("Save these changes?", $default_no = false)) {
  echo "Cancelled.\n";
  exit(1);
}

$user->save();

echo "Saved changes.\n";
