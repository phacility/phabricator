#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

$original_command = getenv('SSH_ORIGINAL_COMMAND');
$original_argv = id(new PhutilShellLexer())->splitArguments($original_command);
$argv = array_merge($argv, $original_argv);

$args = new PhutilArgumentParser($argv);
$args->setTagline('receive SSH requests');
$args->setSynopsis(<<<EOSYNOPSIS
**ssh-exec** --phabricator-ssh-user __user__ __commmand__ [__options__]
    Receive SSH requests.

EOSYNOPSIS
);

// NOTE: Do NOT parse standard arguments. Arguments are coming from a remote
// client over SSH, and they should not be able to execute "--xprofile",
// "--recon", etc.

$args->parsePartial(
  array(
    array(
      'name'  => 'phabricator-ssh-user',
      'param' => 'username',
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

  if ($user->getIsDisabled()) {
    throw new Exception("You have been exiled.");
  }

  $workflows = array(
    new ConduitSSHWorkflow(),
  );

  // This duplicates logic in parseWorkflows(), but allows us to raise more
  // concise/relevant exceptions when the client is a remote SSH.
  $remain = $args->getUnconsumedArgumentVector();
  if (empty($remain)) {
    throw new Exception("No interactive logins.");
  } else {
    $command = head($remain);
    $workflow_names = mpull($workflows, 'getName', 'getName');
    if (empty($workflow_names[$command])) {
      throw new Exception("Invalid command.");
    }
  }

  $workflow = $args->parseWorkflows($workflows);
  $workflow->setUser($user);

  $sock_stdin = fopen('php://stdin', 'r');
  if (!$sock_stdin) {
    throw new Exception("Unable to open stdin.");
  }

  $sock_stdout = fopen('php://stdout', 'w');
  if (!$sock_stdout) {
    throw new Exception("Unable to open stdout.");
  }

  $socket_channel = new PhutilSocketChannel(
    $sock_stdin,
    $sock_stdout);
  $metrics_channel = new PhutilMetricsChannel($socket_channel);
  $workflow->setIOChannel($metrics_channel);

  $err = $workflow->execute($args);

  $metrics_channel->flush();
} catch (Exception $ex) {
  echo "phabricator-ssh-exec: ".$ex->getMessage()."\n";
  exit(1);
}
