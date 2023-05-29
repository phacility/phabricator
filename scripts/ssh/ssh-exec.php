#!/usr/bin/env php
<?php

$ssh_start_time = microtime(true);

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/init/init-script.php';

$error_log = id(new PhutilErrorLog())
  ->setLogName(pht('SSH Error Log'))
  ->setLogPath(PhabricatorEnv::getEnvConfig('log.ssh-error.path'))
  ->activateLog();

$ssh_log = PhabricatorSSHLog::getLog();

$request_identifier = Filesystem::readRandomCharacters(12);
$ssh_log->setData(
  array(
    'Q' => $request_identifier,
  ));

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
  } else if ($user_name !== null && strlen($user_name)) {
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

    id(new PhabricatorAuthSessionEngine())
      ->willServeRequestForUser($user);
  } else if ($device_name !== null && strlen($device_name)) {
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
          'This request originates from outside of the cluster address range. '.
          'Requests signed with a trusted device key must originate from '.
          'trusted hosts.'));
    }

    $device = id(new AlmanacDeviceQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withNames(array($device_name))
      ->executeOne();
    if (!$device) {
      throw new Exception(
        pht(
          'Invalid device name ("%s"). There is no device with this name.',
          $device_name));
    }

    if ($device->isDisabled()) {
      throw new Exception(
        pht(
          'This request has authenticated as a device ("%s"), but this '.
          'device is disabled.',
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
    // If we're authenticating as a device, the first argument may be a
    // "@username" argument to act as a particular user.
    $first_argument = head($original_argv);
    if (preg_match('/^@/', $first_argument)) {
      $act_as_name = array_shift($original_argv);
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
    } else {
      $user = PhabricatorUser::getOmnipotentUser();
    }
  }

  if ($user->isOmnipotent()) {
    $user_name = 'device/'.$device->getName();
  } else {
    $user_name = $user->getUsername();
  }

  $ssh_log->setData(
    array(
      'u' => $user_name,
      'P' => $user->getPHID(),
    ));

  if (!$device) {
    if (!$user->canEstablishSSHSessions()) {
      throw new Exception(
        pht(
          'Your account ("%s") does not have permission to establish SSH '.
          'sessions. Visit the web interface for more information.',
          $user_name));
    }
  }

  $workflows = id(new PhutilClassMapQuery())
    ->setAncestorClass('PhabricatorSSHWorkflow')
    ->setUniqueMethod('getName')
    ->execute();

  $command_list = array_keys($workflows);
  $command_list = implode(', ', $command_list);

  $error_lines = array();
  $error_lines[] = pht(
    'Welcome to %s.',
    PlatformSymbols::getPlatformServerName());
  $error_lines[] = pht(
    'You are logged in as %s.',
    $user_name);

  if (!$original_argv) {
    $error_lines[] = pht(
      'You have not specified a command to run. This means you are requesting '.
      'an interactive shell, but this server does not provide interactive '.
      'shells over SSH.');
    $error_lines[] = pht(
      '(Usually, you should run a command like "git clone" or "hg push" '.
      'instead of connecting directly with SSH.)');
    $error_lines[] = pht(
      'Supported commands are: %s.',
      $command_list);

    $error_lines = implode("\n\n", $error_lines);
    throw new PhutilArgumentUsageException($error_lines);
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
    $error_lines[] = pht(
      'You have specified the command "%s", but that command is not '.
      'supported by this server. As received by this server, your entire '.
      'argument list was:',
      $command);

    $error_lines[] = csprintf('  $ ssh ... -- %Ls', $parseable_argv);

    $error_lines[] = pht(
      'Supported commands are: %s.',
      $command_list);

    $error_lines = implode("\n\n", $error_lines);
    throw new PhutilArgumentUsageException($error_lines);
  }

  $workflow = $parsed_args->parseWorkflows($workflows);
  $workflow->setSSHUser($user);
  $workflow->setOriginalArguments($original_argv);
  $workflow->setIsClusterRequest($is_cluster_request);
  $workflow->setRequestIdentifier($request_identifier);

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
    'T' => phutil_microseconds_since($ssh_start_time),
  ));

exit($err);
