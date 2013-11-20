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

$repository = id(new PhabricatorRepositoryQuery())
  ->setViewer(PhabricatorUser::getOmnipotentUser())
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

$login = $repository->getSSHLogin();
if (strlen($login)) {
  $pattern[] = '-l';
  $pattern[] = '%P';
  $arguments[] = new PhutilOpaqueEnvelope($login);
}

$ssh_identity = null;

$key = $repository->getDetail('ssh-key');
$keyfile = $repository->getDetail('ssh-keyfile');
if ($keyfile) {
  $ssh_identity = $keyfile;
} else if ($key) {
  $tmpfile = new TempFile('phabricator-repository-ssh-key');
  chmod($tmpfile, 0600);
  Filesystem::writeFile($tmpfile, $key);
  $ssh_identity = (string)$tmpfile;
}

if ($ssh_identity) {
  $pattern[] = '-i';
  $pattern[] = '%P';
  $arguments[] = new PhutilOpaqueEnvelope($keyfile);
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
