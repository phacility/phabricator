#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

if ($argc < 2) {
  throw new Exception(pht('usage: commit-hook <callsign>'));
}

$engine = new DiffusionCommitHookEngine();

$repository = id(new PhabricatorRepositoryQuery())
  ->setViewer(PhabricatorUser::getOmnipotentUser())
  ->withCallsigns(array($argv[1]))
  ->executeOne();

if (!$repository) {
  throw new Exception(pht('No such repository "%s"!', $callsign));
}

if (!$repository->isHosted()) {
  // This should be redundant, but double check just in case.
  throw new Exception(pht('Repository "%s" is not hosted!', $callsign));
}

$engine->setRepository($repository);


// Figure out which user is writing the commit.

if ($repository->isGit()) {
  $username = getenv('PHABRICATOR_USER');
  if (!strlen($username)) {
    throw new Exception(pht('usage: PHABRICATOR_USER should be defined!'));
  }
} else if ($repository->isSVN()) {
  // NOTE: In Subversion, the entire environment gets wiped so we can't read
  // PHABRICATOR_USER. Instead, we've set "--tunnel-user" to specify the
  // correct user; read this user out of the commit log.

  if ($argc < 4) {
    throw new Exception(pht('usage: commit-hook <callsign> <repo> <txn>'));
  }

  $svn_repo = $argv[2];
  $svn_txn = $argv[3];
  list($username) = execx('svnlook author -t %s %s', $svn_txn, $svn_repo);
  $username = rtrim($username, "\n");

  $engine->setSubversionTransactionInfo($svn_txn, $svn_repo);
} else {
  throw new Exceptiont(pht('Unknown repository type.'));
}

$user = id(new PhabricatorPeopleQuery())
  ->setViewer(PhabricatorUser::getOmnipotentUser())
  ->withUsernames(array($username))
  ->executeOne();

if (!$user) {
  throw new Exception(pht('No such user "%s"!', $username));
}

$engine->setViewer($user);


// Read stdin for the hook engine.

$stdin = @file_get_contents('php://stdin');
if ($stdin === false) {
  throw new Exception(pht('Failed to read stdin!'));
}

$engine->setStdin($stdin);

$err = $engine->execute();

exit($err);
