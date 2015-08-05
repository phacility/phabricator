#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

$table = new PhabricatorUser();
$any_user = queryfx_one(
  $table->establishConnection('r'),
  'SELECT * FROM %T LIMIT 1',
  $table->getTableName());
$is_first_user = (!$any_user);

if ($is_first_user) {
  echo pht(
      "WARNING\n\n".
      "You're about to create the first account on this install. Normally, ".
      "you should use the web interface to create the first account, not ".
      "this script.\n\n".
      "If you use the web interface, it will drop you into a nice UI workflow ".
      "which gives you more help setting up your install. If you create an ".
      "account with this script instead, you will skip the setup help and you ".
      "will not be able to access it later.");
  if (!phutil_console_confirm(pht('Skip easy setup and create account?'))) {
    echo pht('Cancelled.')."\n";
    exit(1);
  }
}

echo pht(
  'Enter a username to create a new account or edit an existing account.');

$username = phutil_console_prompt(pht('Enter a username:'));
if (!strlen($username)) {
  echo pht('Cancelled.')."\n";
  exit(1);
}

if (!PhabricatorUser::validateUsername($username)) {
  $valid = PhabricatorUser::describeValidUsername();
  echo pht("The username '%s' is invalid. %s", $username, $valid)."\n";
  exit(1);
}


$user = id(new PhabricatorUser())->loadOneWhere(
  'username = %s',
  $username);

if (!$user) {
  $original = new PhabricatorUser();

  echo pht("There is no existing user account '%s'.", $username)."\n";
  $ok = phutil_console_confirm(
    pht("Do you want to create a new '%s' account?", $username),
    $default_no = false);
  if (!$ok) {
    echo pht('Cancelled.')."\n";
    exit(1);
  }
  $user = new PhabricatorUser();
  $user->setUsername($username);

  $is_new = true;
} else {
  $original = clone $user;

  echo pht("There is an existing user account '%s'.", $username)."\n";
  $ok = phutil_console_confirm(
    pht("Do you want to edit the existing '%s' account?", $username),
    $default_no = false);
  if (!$ok) {
    echo pht('Cancelled.')."\n";
    exit(1);
  }

  $is_new = false;
}

$user_realname = $user->getRealName();
if (strlen($user_realname)) {
  $realname_prompt = ' ['.$user_realname.']:';
} else {
  $realname_prompt = ':';
}
$realname = nonempty(
  phutil_console_prompt(pht('Enter user real name').$realname_prompt),
  $user_realname);
$user->setRealName($realname);

// When creating a new user we prompt for an email address; when editing an
// existing user we just skip this because it would be quite involved to provide
// a reasonable CLI interface for editing multiple addresses and managing email
// verification and primary addresses.

$create_email = null;
if ($is_new) {
  do {
    $email = phutil_console_prompt(pht('Enter user email address:'));
    $duplicate = id(new PhabricatorUserEmail())->loadOneWhere(
      'address = %s',
      $email);
    if ($duplicate) {
      echo pht(
        "ERROR: There is already a user with that email address. ".
        "Each user must have a unique email address.\n");
    } else {
      break;
    }
  } while (true);

  $create_email = $email;
}

$changed_pass = false;
// This disables local echo, so the user's password is not shown as they type
// it.
phutil_passthru('stty -echo');
$password = phutil_console_prompt(
  pht('Enter a password for this user [blank to leave unchanged]:'));
phutil_passthru('stty echo');
if (strlen($password)) {
  $changed_pass = $password;
}

$is_system_agent = $user->getIsSystemAgent();
$set_system_agent = phutil_console_confirm(
  pht('Is this user a bot?'),
  $default_no = !$is_system_agent);

$verify_email = null;
$set_verified = false;
// Allow administrators to verify primary email addresses at this time in edit
// scenarios. (Create will work just fine from here as we auto-verify email
// on create.)
if (!$is_new) {
  $verify_email = $user->loadPrimaryEmail();
  if (!$verify_email->getIsVerified()) {
    $set_verified = phutil_console_confirm(
      pht('Should the primary email address be verified?'),
      $default_no = true);
  } else {
    // Already verified so let's not make a fuss.
    $verify_email = null;
  }
}

$is_admin = $user->getIsAdmin();
$set_admin = phutil_console_confirm(
  pht('Should this user be an administrator?'),
  $default_no = !$is_admin);

echo "\n\n".pht('ACCOUNT SUMMARY')."\n\n";
$tpl = "%12s   %-30s   %-30s\n";
printf($tpl, null, pht('OLD VALUE'), pht('NEW VALUE'));
printf($tpl, pht('Username'), $original->getUsername(), $user->getUsername());
printf($tpl, pht('Real Name'), $original->getRealName(), $user->getRealName());
if ($is_new) {
  printf($tpl, pht('Email'), '', $create_email);
}
printf($tpl, pht('Password'), null,
  ($changed_pass !== false)
    ? pht('Updated')
    : pht('Unchanged'));

printf(
  $tpl,
  pht('Bot'),
  $original->getIsSystemAgent() ? 'Y' : 'N',
  $set_system_agent ? 'Y' : 'N');

if ($verify_email) {
  printf(
    $tpl,
    pht('Verify Email'),
    $verify_email->getIsVerified() ? 'Y' : 'N',
    $set_verified ? 'Y' : 'N');
}

printf(
  $tpl,
  pht('Admin'),
  $original->getIsAdmin() ? 'Y' : 'N',
  $set_admin ? 'Y' : 'N');

echo "\n";

if (!phutil_console_confirm(pht('Save these changes?'), $default_no = false)) {
  echo pht('Cancelled.')."\n";
  exit(1);
}

$user->openTransaction();

  $editor = new PhabricatorUserEditor();

  // TODO: This is wrong, but we have a chicken-and-egg problem when you use
  // this script to create the first user.
  $editor->setActor($user);

  if ($is_new) {
    $email = id(new PhabricatorUserEmail())
      ->setAddress($create_email)
      ->setIsVerified(1);

    // Unconditionally approve new accounts created from the CLI.
    $user->setIsApproved(1);

    $editor->createNewUser($user, $email);
  } else {
    if ($verify_email) {
      $user->setIsEmailVerified(1);
      $verify_email->setIsVerified($set_verified ? 1 : 0);
    }
    $editor->updateUser($user, $verify_email);
  }

  $editor->makeAdminUser($user, $set_admin);
  $editor->makeSystemAgentUser($user, $set_system_agent);

  if ($changed_pass !== false) {
    $envelope = new PhutilOpaqueEnvelope($changed_pass);
    $editor->changePassword($user, $envelope);
  }

$user->saveTransaction();

echo pht('Saved changes.')."\n";
