#!/usr/bin/env php
<?php

$ssh_start_time = microtime(true);

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

$ssh_log = PhabricatorSSHLog::getLog();

// First, figure out the authenticated user.
$args = new PhutilArgumentParser($argv);
$args->setTagline('receive SSH requests');
$args->setSynopsis(<<<EOSYNOPSIS
**ssh-exec** --phabricator-ssh-user __user__ [--ssh-command __commmand__]
    Receive SSH requests.
EOSYNOPSIS
);

$args->parse(
  array(
    array(
      'name'  => 'phabricator-ssh-user',
      'param' => 'username',
    ),
    array(
      'name' => 'ssh-command',
      'param' => 'command',
    ),
  ));

try {
  $user_name = $args->getArg('phabricator-ssh-user');
  if (!strlen($user_name)) {
    throw new Exception("No username.");
  }

  $user = id(new PhabricatorUser())->loadOneWhere(
    'userName = %s',
    $user_name);
  if (!$user) {
    throw new Exception("Invalid username.");
  }

  $ssh_log->setData(
    array(
      'u' => $user->getUsername(),
      'P' => $user->getPHID(),
    ));

  if (!$user->isUserActivated()) {
    throw new Exception(pht("Your account is not activated."));
  }

  if ($args->getArg('ssh-command')) {
    $original_command = $args->getArg('ssh-command');
  } else {
    $original_command = getenv('SSH_ORIGINAL_COMMAND');
  }

  $workflows = id(new PhutilSymbolLoader())
    ->setAncestorClass('PhabricatorSSHWorkflow')
    ->loadObjects();

  $workflow_names = mpull($workflows, 'getName', 'getName');

  // Now, rebuild the original command.
  $original_argv = id(new PhutilShellLexer())
    ->splitArguments($original_command);
  if (!$original_argv) {
    throw new Exception(
      pht(
        "Welcome to Phabricator.\n\n".
        "You are logged in as %s.\n\n".
        "You haven't specified a command to run. This means you're requesting ".
        "an interactive shell, but Phabricator does not provide an ".
        "interactive shell over SSH.\n\n".
        "Usually, you should run a command like `git clone` or `hg push` ".
        "rather than connecting directly with SSH.\n\n".
        "Supported commands are: %s.",
        $user->getUsername(),
        implode(', ', $workflow_names)));
  }

  $ssh_log->setData(
    array(
      'C' => $original_argv[0],
      'U' => phutil_utf8_shorten(
        implode(' ', array_slice($original_argv, 1)),
        128),
    ));

  $command = head($original_argv);
  array_unshift($original_argv, 'phabricator-ssh-exec');

  $original_args = new PhutilArgumentParser($original_argv);

  if (empty($workflow_names[$command])) {
    throw new Exception("Invalid command.");
  }

  $workflow = $original_args->parseWorkflows($workflows);
  $workflow->setUser($user);

  $sock_stdin = fopen('php://stdin', 'r');
  if (!$sock_stdin) {
    throw new Exception("Unable to open stdin.");
  }

  $sock_stdout = fopen('php://stdout', 'w');
  if (!$sock_stdout) {
    throw new Exception("Unable to open stdout.");
  }

  $sock_stderr = fopen('php://stderr', 'w');
  if (!$sock_stderr) {
    throw new Exception("Unable to open stderr.");
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
    $err = $workflow->execute($original_args);

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
