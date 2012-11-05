#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

if (empty($argv[1])) {
  echo "usage: test_connection.php <repository_callsign>\n";
  exit(1);
}

echo phutil_console_wrap(
  phutil_console_format(
    'This script will test that you have configured valid credentials for '.
    'access to a repository, so the Phabricator daemons can pull from it. '.
    'You should run this as the **same user you will run the daemons as**, '.
    'from the **same machine they will run from**. Doing this will help '.
    'detect various problems with your configuration, such as SSH issues.'));

list($whoami) = execx('whoami');
$whoami = trim($whoami);

$ok = phutil_console_confirm("Do you want to continue as '{$whoami}'?");
if (!$ok) {
  die(1);
}

$callsign = $argv[1];
echo "Loading '{$callsign}' repository...\n";
$repository = id(new PhabricatorRepository())->loadOneWhere(
  'callsign = %s',
  $argv[1]);
if (!$repository) {
  throw new Exception("No such repository exists!");
}

$vcs = $repository->getVersionControlSystem();

PhutilServiceProfiler::installEchoListener();

echo phutil_console_format(
  "\n".
  "**NOTE:** If you are prompted for an SSH password in the next step, the\n".
  "daemon won't work because it doesn't have the password and can't respond\n".
  "to an interactive prompt. Instead of typing the password, it will hang\n".
  "forever when prompted. There are several ways to resolve this:\n\n".
  "  - Run the daemon inside an ssh-agent session where you have unlocked\n".
  "    the key (most secure, but most complicated).\n".
  "  - Generate a new, passwordless certificate for the daemon to use\n".
  "    (usually quite easy).\n".
  "  - Remove the passphrase from the key with `ssh-keygen -p`\n".
  "    (easy, but questionable).");

phutil_console_confirm('Did you read all that?', $default_no = false);

echo "Trying to connect to the remote...\n";
switch ($vcs) {
  case PhabricatorRepositoryType::REPOSITORY_TYPE_SVN:
    $err = $repository->passthruRemoteCommand(
      '--limit 1 log %s',
      $repository->getRemoteURI());
    break;
  case PhabricatorRepositoryType::REPOSITORY_TYPE_GIT:
    // Do an ls-remote on a nonexistent ref, which we expect to just return
    // nothing.
    $err = $repository->passthruRemoteCommand(
      'ls-remote %s %s',
      $repository->getRemoteURI(),
      'just-testing');
    break;
  case PhabricatorRepositoryType::REPOSITORY_TYPE_MERCURIAL:
    // TODO: 'hg id' doesn't support --insecure so we can't tell it not to
    // spew. If 'hg id' eventually supports --insecure, consider using it.
    echo "(It is safe to ignore any 'certificate with fingerprint ... not ".
         "verified' warnings, although you may want to configure Mercurial ".
         "to recognize the server's fingerprint/certificate.)\n";
    $err = $repository->passthruRemoteCommand(
      'id --rev tip %s',
      $repository->getRemoteURI());
    break;
  default:
    throw new Exception("Unsupported repository type.");
}

if ($err) {
  echo phutil_console_format(
    "<bg:red>** FAIL **</bg> Connection failed. The credentials for this ".
    "repository appear to be incorrectly configured.\n");
  exit(1);
} else {
  echo phutil_console_format(
    "<bg:green>** OKAY **</bg> Connection successful. The credentials for ".
    "this repository appear to be correctly configured.\n");
}

