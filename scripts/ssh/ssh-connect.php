#!/usr/bin/env php
<?php

// This is a wrapper script for Git, Mercurial, and Subversion. It primarily
// serves to inject "-o StrictHostKeyChecking=no" into the SSH arguments.

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

// Contrary to the documentation, Git may pass a "-p" flag. If it does, respect
// it and move it before the "--" argument.
$args = new PhutilArgumentParser($argv);
$args->parsePartial(
  array(
    array(
      'name' => 'port',
      'short' => 'p',
      'param' => pht('port'),
      'help' => pht('Port number to connect to.'),
    ),
  ));
$unconsumed_argv = $args->getUnconsumedArgumentVector();

$pattern = array();
$arguments = array();

$pattern[] = 'ssh';

$pattern[] = '-o';
$pattern[] = 'StrictHostKeyChecking=no';

// This prevents "known host" failures, and covers for issues where HOME is set
// to something unusual.
$pattern[] = '-o';
$pattern[] = 'UserKnownHostsFile=/dev/null';

$credential_phid = getenv('PHABRICATOR_CREDENTIAL');
if ($credential_phid) {
  $viewer = PhabricatorUser::getOmnipotentUser();
  $key = PassphraseSSHKey::loadFromPHID($credential_phid, $viewer);

  $pattern[] = '-l %P';
  $arguments[] = $key->getUsernameEnvelope();
  $pattern[] = '-i %P';
  $arguments[] = $key->getKeyfileEnvelope();
}

$port = $args->getArg('port');
if ($port) {
  $pattern[] = '-p %d';
  $arguments[] = $port;
}

$pattern[] = '--';

$passthru_args = $unconsumed_argv;
foreach ($passthru_args as $passthru_arg) {
  $pattern[] = '%s';
  $arguments[] = $passthru_arg;
}

$pattern = implode(' ', $pattern);
array_unshift($arguments, $pattern);

$err = newv('PhutilExecPassthru', $arguments)
  ->execute();

exit($err);
