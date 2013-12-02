#!/usr/bin/env php
<?php

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

$username = getenv('PHABRICATOR_USER');
if (!$username) {
  throw new Exception(pht('usage: define PHABRICATOR_USER in environment'));
}

$user = id(new PhabricatorPeopleQuery())
  ->setViewer(PhabricatorUser::getOmnipotentUser())
  ->withUsernames(array($username))
  ->executeOne();
if (!$user) {
  throw new Exception(pht('No such user "%s"!', $username));
}

if ($argc < 2) {
  throw new Exception(pht('usage: commit-hook <callsign>'));
}

$repository = id(new PhabricatorRepositoryQuery())
  ->setViewer($user)
  ->withCallsigns(array($argv[1]))
  ->requireCapabilities(
    array(
      // This capability check is redundant, but can't hurt.
      PhabricatorPolicyCapability::CAN_VIEW,
      DiffusionCapabilityPush::CAPABILITY,
    ))
  ->executeOne();

if (!$repository) {
  throw new Exception(pht('No such repository "%s"!', $callsign));
}

if (!$repository->isHosted()) {
  // This should be redundant too, but double check just in case.
  throw new Exception(pht('Repository "%s" is not hosted!', $callsign));
}

$stdin = @file_get_contents('php://stdin');
if ($stdin === false) {
  throw new Exception(pht('Failed to read stdin!'));
}

$engine = id(new DiffusionCommitHookEngine())
  ->setViewer($user)
  ->setRepository($repository)
  ->setStdin($stdin);

$err = $engine->execute();

exit($err);
