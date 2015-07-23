#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

if ($argc !== 5) {
  echo pht(
    "Usage: %s\n",
    'add_user.php <username> <email> <realname> <admin_user>');
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
    pht(
      'Admin user must be the username of a valid Phabricator account, used '.
      'to send the new user a welcome email.'));
}

$existing_user = id(new PhabricatorUser())->loadOneWhere(
  'username = %s',
  $username);
if ($existing_user) {
  throw new Exception(
    pht(
      "There is already a user with the username '%s'!",
      $username));
}

$existing_email = id(new PhabricatorUserEmail())->loadOneWhere(
  'address = %s',
  $email);
if ($existing_email) {
  throw new Exception(
    pht(
      "There is already a user with the email '%s'!",
      $email));
}

$user = new PhabricatorUser();
$user->setUsername($username);
$user->setRealname($realname);
$user->setIsApproved(1);

$email_object = id(new PhabricatorUserEmail())
  ->setAddress($email)
  ->setIsVerified(1);

id(new PhabricatorUserEditor())
  ->setActor($admin)
  ->createNewUser($user, $email_object);

$user->sendWelcomeEmail($admin);

echo pht(
  "Created user '%s' (realname='%s', email='%s').\n",
  $username,
  $realname,
  $email);
