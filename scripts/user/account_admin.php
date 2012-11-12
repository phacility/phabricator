#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

echo "Enter a username to create a new account or edit an existing account.";

$username = phutil_console_prompt("Enter a username:");
if (!strlen($username)) {
  echo "Cancelled.\n";
  exit(1);
}

if (!PhabricatorUser::validateUsername($username)) {
  $valid = PhabricatorUser::describeValidUsername();
  echo "The username '{$username}' is invalid. {$valid}\n";
  exit(1);
}


$user = id(new PhabricatorUser())->loadOneWhere(
  'username = %s',
  $username);

if (!$user) {
  $original = new PhabricatorUser();

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

  $is_new = true;
} else {
  $original = clone $user;

  echo "There is an existing user account '{$username}'.\n";
  $ok = phutil_console_confirm(
    "Do you want to edit the existing '{$username}' account?",
    $default_no = false);
  if (!$ok) {
    echo "Cancelled.\n";
    exit(1);
  }

  $is_new = false;
}

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

// When creating a new user we prompt for an email address; when editing an
// existing user we just skip this because it would be quite involved to provide
// a reasonable CLI interface for editing multiple addresses and managing email
// verification and primary addresses.

$create_email = null;
if ($is_new) {
  do {
    $email = phutil_console_prompt("Enter user email address:");
    $duplicate = id(new PhabricatorUserEmail())->loadOneWhere(
      'address = %s',
      $email);
    if ($duplicate) {
      echo "ERROR: There is already a user with that email address. ".
           "Each user must have a unique email address.\n";
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
  "Enter a password for this user [blank to leave unchanged]:");
phutil_passthru('stty echo');
if (strlen($password)) {
  $changed_pass = $password;
}

$is_system_agent = $user->getIsSystemAgent();
$set_system_agent = phutil_console_confirm(
  'Should this user be a system agent?',
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
      'Should the primary email address be verified?',
      $default_no = true
    );
  } else {
    // already verified so let's not make a fuss
    $verify_email = null;
  }
}

$is_admin = $user->getIsAdmin();
$set_admin = phutil_console_confirm(
  'Should this user be an administrator?',
  $default_no = !$is_admin);

echo "\n\nACCOUNT SUMMARY\n\n";
$tpl = "%12s   %-30s   %-30s\n";
printf($tpl, null, 'OLD VALUE', 'NEW VALUE');
printf($tpl, 'Username', $original->getUsername(), $user->getUsername());
printf($tpl, 'Real Name', $original->getRealName(), $user->getRealName());
if ($is_new) {
  printf($tpl, 'Email', '', $create_email);
}
printf($tpl, 'Password', null,
  ($changed_pass !== false)
    ? 'Updated'
    : 'Unchanged');

printf(
  $tpl,
  'System Agent',
  $original->getIsSystemAgent() ? 'Y' : 'N',
  $set_system_agent ? 'Y' : 'N');

if ($verify_email) {
  printf(
    $tpl,
    'Verify Email',
    $verify_email->getIsVerified() ? 'Y' : 'N',
    $set_verified ? 'Y' : 'N');
}

printf(
  $tpl,
  'Admin',
  $original->getIsAdmin() ? 'Y' : 'N',
  $set_admin ? 'Y' : 'N');

echo "\n";

if (!phutil_console_confirm("Save these changes?", $default_no = false)) {
  echo "Cancelled.\n";
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

    $editor->createNewUser($user, $email);
  } else {
    if ($verify_email) {
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

echo "Saved changes.\n";
