#!/usr/bin/env php
<?php

$ssh_start_time = microtime(true);

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

$ssh_log = PhabricatorSSHLog::getLog();

$args = new PhutilArgumentParser($argv);
$args->setTagline(pht('execute SSH requests'));
$args->setSynopsis(<<<EOSYNOPSIS
**ssh-exec** --phabricator-ssh-user __user__ [--ssh-command __commmand__]
**ssh-exec** --phabricator-ssh-device __device__ [--ssh-command __commmand__]
    Execute authenticated SSH requests. This script is normally invoked
    via SSHD, but can be invoked manually for testing.

EOSYNOPSIS
);

$args->parseStandardArguments();
$args->parse(
  array(
    array(
      'name'  => 'phabricator-ssh-user',
      'param' => 'username',
      'help' => pht(
        'If the request authenticated with a user key, the name of the '.
        'user.'),
    ),
    array(
      'name' => 'phabricator-ssh-device',
      'param' => 'name',
      'help' => pht(
        'If the request authenticated with a device key, the name of the '.
        'device.'),
    ),
    array(
      'name' => 'phabricator-ssh-key',
      'param' => 'id',
      'help' => pht(
        'The ID of the SSH key which authenticated this request. This is '.
        'used to allow logs to report when specific keys were used, to make '.
        'it easier to manage credentials.'),
    ),
    array(
      'name' => 'ssh-command',
      'param' => 'command',
      'help' => pht(
        'Provide a command to execute. This makes testing this script '.
        'easier. When running normally, the command is read from the '.
        'environment (%s), which is populated by sshd.',
        'SSH_ORIGINAL_COMMAND'),
    ),
  ));

try {
  $remote_address = null;
  $ssh_client = getenv('SSH_CLIENT');
  if ($ssh_client) {
    // This has the format "<ip> <remote-port> <local-port>". Grab the IP.
    $remote_address = head(explode(' ', $ssh_client));
    $ssh_log->setData(
      array(
        'r' => $remote_address,
      ));
  }

  $key_id = $args->getArg('phabricator-ssh-key');
  if ($key_id) {
    $ssh_log->setData(
      array(
        'k' => $key_id,
      ));
  }

  $user_name = $args->getArg('phabricator-ssh-user');
  $device_name = $args->getArg('phabricator-ssh-device');

  $user = null;
  $device = null;
  $is_cluster_request = false;

  if ($user_name && $device_name) {
    throw new Exception(
      pht(
        'The %s and %s flags are mutually exclusive. You can not '.
        'authenticate as both a user ("%s") and a device ("%s"). '.
        'Specify one or the other, but not both.',
        '--phabricator-ssh-user',
        '--phabricator-ssh-device',
        $user_name,
        $device_name));
  } else if (strlen($user_name)) {
    $user = id(new PhabricatorPeopleQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withUsernames(array($user_name))
      ->executeOne();
    if (!$user) {
      throw new Exception(
        pht(
          'Invalid username ("%s"). There is no user with this username.',
          $user_name));
    }
  } else if (strlen($device_name)) {
    if (!$remote_address) {
      throw new Exception(
        pht(
          'Unable to identify remote address from the %s environment '.
          'variable. Device authentication is accepted only from trusted '.
          'sources.',
          'SSH_CLIENT'));
    }

    if (!PhabricatorEnv::isClusterAddress($remote_address)) {
      throw new Exception(
        pht(
          'This request originates from outside of the Phabricator cluster '.
          'address range. Requests signed with a trusted device key must '.
          'originate from trusted hosts.'));
    }

    $device = id(new AlmanacDeviceQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withNames(array($device_name))
      ->executeOne();
    if (!$device) {
      throw new Exception(
        pht(
          'Invalid device name ("%s"). There is no device with this name.',
          $device->getName()));
    }

    // We're authenticated as a device, but we're going to read the user out of
    // the command below.
    $is_cluster_request = true;
  } else {
    throw new Exception(
      pht(
        'This script must be invoked with either the %s or %s flag.',
        '--phabricator-ssh-user',
        '--phabricator-ssh-device'));
  }

  if ($args->getArg('ssh-command')) {
    $original_command = $args->getArg('ssh-command');
  } else {
    $original_command = getenv('SSH_ORIGINAL_COMMAND');
  }

  $original_argv = id(new PhutilShellLexer())
    ->splitArguments($original_command);

  if ($device) {
    $act_as_name = array_shift($original_argv);
    if (!preg_match('/^@/', $act_as_name)) {
      throw new Exception(
        pht(
          'Commands executed by devices must identify an acting user in the '.
          'first command argument. This request was not constructed '.
          'properly.'));
    }

    $act_as_name = substr($act_as_name, 1);
    $user = id(new PhabricatorPeopleQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withUsernames(array($act_as_name))
      ->executeOne();
    if (!$user) {
      throw new Exception(
        pht(
          'Device request identifies an acting user with an invalid '.
          'username ("%s"). There is no user with this username.',
          $act_as_name));
    }
  }

  $ssh_log->setData(
    array(
      'u' => $user->getUsername(),
      'P' => $user->getPHID(),
    ));

  if (!$user->canEstablishSSHSessions()) {
    throw new Exception(
      pht(
        'Your account ("%s") does not have permission to establish SSH '.
        'sessions. Visit the web interface for more information.',
        $user->getUsername()));
  }

  $workflows = id(new PhutilClassMapQuery())
    ->setAncestorClass('PhabricatorSSHWorkflow')
    ->setUniqueMethod('getName')
    ->execute();

  if (!$original_argv) {
    throw new Exception(
      pht(
        "Welcome to Phabricator.\n\n".
        "You are logged in as %s.\n\n".
        "You haven't specified a command to run. This means you're requesting ".
        "an interactive shell, but Phabricator does not provide an ".
        "interactive shell over SSH.\n\n".
        "Usually, you should run a command like `%s` or `%s` ".
        "rather than connecting directly with SSH.\n\n".
        "Supported commands are: %s.",
        $user->getUsername(),
        'git clone',
        'hg push',
        implode(', ', array_keys($workflows))));
  }

  $log_argv = implode(' ', $original_argv);
  $log_argv = id(new PhutilUTF8StringTruncator())
    ->setMaximumCodepoints(128)
    ->truncateString($log_argv);

  $ssh_log->setData(
    array(
      'C' => $original_argv[0],
      'U' => $log_argv,
    ));

  $command = head($original_argv);

  $parseable_argv = $original_argv;
  array_unshift($parseable_argv, 'phabricator-ssh-exec');

  $parsed_args = new PhutilArgumentParser($parseable_argv);

  if (empty($workflows[$command])) {
    throw new Exception(pht('Invalid command.'));
  }

  $workflow = $parsed_args->parseWorkflows($workflows);
  $workflow->setUser($user);
  $workflow->setOriginalArguments($original_argv);
  $workflow->setIsClusterRequest($is_cluster_request);

  $sock_stdin = fopen('php://stdin', 'r');
  if (!$sock_stdin) {
    throw new Exception(pht('Unable to open stdin.'));
  }

  $sock_stdout = fopen('php://stdout', 'w');
  if (!$sock_stdout) {
    throw new Exception(pht('Unable to open stdout.'));
  }

  $sock_stderr = fopen('php://stderr', 'w');
  if (!$sock_stderr) {
    throw new Exception(pht('Unable to open stderr.'));
  }

  $socket_channel = new PhutilSocketChannel(
    $sock_stdin,
    $sock_stdout);
  $error_channel = new PhutilSocketChannel(null, $sock_stderr);
  $metrics_channel = new PhutilMetricsChannel($socket_channel);
  $workflow->setIOChannel($metrics_channel);
  $workflow->setErrorChannel($error_channel);

  $rethrow = null;
  try {
    $err = $workflow->execute($parsed_args);

    $metrics_channel->flush();
    $error_channel->flush();
  } catch (Exception $ex) {
    $rethrow = $ex;
  }

  // Always write this if we got as far as building a metrics channel.
  $ssh_log->setData(
    array(
      'i' => $metrics_channel->getBytesRead(),
      'o' => $metrics_channel->getBytesWritten(),
    ));

  if ($rethrow) {
    throw $rethrow;
  }
} catch (Exception $ex) {
  fwrite(STDERR, "phabricator-ssh-exec: ".$ex->getMessage()."\n");
  $err = 1;
}

$ssh_log->setData(
  array(
    'c' => $err,
    'T' => (int)(1000000 * (microtime(true) - $ssh_start_time)),
  ));

exit($err);
