#!/usr/bin/env php
<?php

// This is a wrapper script for Git, Mercurial, and Subversion. It primarily
// serves to inject "-o StrictHostKeyChecking=no" into the SSH arguments.

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

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

$pattern[] = '--';

$passthru_args = array_slice($argv, 1);
foreach ($passthru_args as $passthru_arg) {
  $pattern[] = '%s';
  $arguments[] = $passthru_arg;
}

$pattern = implode(' ', $pattern);
array_unshift($arguments, $pattern);

$err = newv('PhutilExecPassthru', $arguments)
  ->execute();

exit($err);
