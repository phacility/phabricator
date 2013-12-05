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

if ($repository->isGit() || $repository->isHg()) {
  $username = getenv(DiffusionCommitHookEngine::ENV_USER);
  if (!strlen($username)) {
    throw new Exception(
      pht('usage: %s should be defined!', DiffusionCommitHookEngine::ENV_USER));
  }

  // TODO: If this is a Mercurial repository, the hook we're responding to
  // is available in $argv[2]. It's unclear if we actually need this, or if
  // we can block all actions we care about with just pretxnchangegroup.

} else if ($repository->isSVN()) {
  // NOTE: In Subversion, the entire environment gets wiped so we can't read
  // DiffusionCommitHookEngine::ENV_USER. Instead, we've set "--tunnel-user" to
  // specify the correct user; read this user out of the commit log.

  if ($argc < 4) {
    throw new Exception(pht('usage: commit-hook <callsign> <repo> <txn>'));
  }

  $svn_repo = $argv[2];
  $svn_txn = $argv[3];
  list($username) = execx('svnlook author -t %s %s', $svn_txn, $svn_repo);
  $username = rtrim($username, "\n");

  $engine->setSubversionTransactionInfo($svn_txn, $svn_repo);
} else {
  throw new Exception(pht('Unknown repository type.'));
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

if ($repository->isHg()) {
  // Mercurial leaves stdin open, so we can't just read it until EOF.
  $stdin = '';
} else {
  // Git and Subversion write data into stdin and then close it. Read the
  // data.
  $stdin = @file_get_contents('php://stdin');
  if ($stdin === false) {
    throw new Exception(pht('Failed to read stdin!'));
  }
}

$engine->setStdin($stdin);

$remote_address = getenv(DiffusionCommitHookEngine::ENV_REMOTE_ADDRESS);
if (strlen($remote_address)) {
  $engine->setRemoteAddress($remote_address);
}

$remote_protocol = getenv(DiffusionCommitHookEngine::ENV_REMOTE_PROTOCOL);
if (strlen($remote_protocol)) {
  $engine->setRemoteProtocol($remote_protocol);
}

try {
  $err = $engine->execute();
} catch (DiffusionCommitHookRejectException $ex) {
  $console = PhutilConsole::getConsole();

  if (PhabricatorEnv::getEnvConfig('phabricator.serious-business')) {
    $preamble = pht('*** PUSH REJECTED BY COMMIT HOOK ***');
  } else {
    $preamble = pht(<<<EOTXT
+---------------------------------------------------------------+
|      * * * PUSH REJECTED BY EVIL DRAGON BUREAUCRATS * * *     |
+---------------------------------------------------------------+
            \
             \                    ^    /^
              \                  / \  // \
               \   |\___/|      /   \//  .\
                \  /V  V  \__  /    //  | \ \           *----*
                  /     /  \/_/    //   |  \  \          \   |
                  @___@`    \/_   //    |   \   \         \/\ \
                 0/0/|       \/_ //     |    \    \         \  \
             0/0/0/0/|        \///      |     \     \       |  |
          0/0/0/0/0/_|_ /   (  //       |      \     _\     |  /
       0/0/0/0/0/0/`/,_ _ _/  ) ; -.    |    _ _\.-~       /   /
                   ,-}        _      *-.|.-~-.           .~    ~
  \     \__/        `/\      /                 ~-. _ .-~      /
   \____(Oo)           *.   }            {                   /
   (    (--)          .----~-.\        \-`                 .~
   //__\\\\  \ DENIED!  ///.----..<        \             _ -~
  //    \\\\               ///-._ _ _ _ _ _ _{^ - - - - ~

EOTXT
);
  }

  $console->writeErr("%s\n\n", $preamble);
  $console->writeErr("%s\n\n", $ex->getMessage());
  $err = 1;
}

exit($err);
