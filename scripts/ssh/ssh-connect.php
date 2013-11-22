#!/usr/bin/env php
<?php

// This is a wrapper script for Git, Mercurial, and Subversion. It primarily
// serves to inject "-o StrictHostKeyChecking=no" into the SSH arguments.

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

$target_name = getenv('PHABRICATOR_SSH_TARGET');
if (!$target_name) {
  throw new Exception(pht("No 'PHABRICATOR_SSH_TARGET' in environment!"));
}

$viewer = PhabricatorUser::getOmnipotentUser();

$repository = id(new PhabricatorRepositoryQuery())
  ->setViewer($viewer)
  ->withCallsigns(array($target_name))
  ->executeOne();
if (!$repository) {
  throw new Exception(pht('No repository with callsign "%s"!', $target_name));
}

$pattern = array();
$arguments = array();

$pattern[] = 'ssh';

$pattern[] = '-o';
$pattern[] = 'StrictHostKeyChecking=no';

$credential_phid = $repository->getCredentialPHID();
if ($credential_phid) {
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
