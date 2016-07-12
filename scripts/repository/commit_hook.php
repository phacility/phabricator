#!/usr/bin/env php
<?php

// NOTE: This script will sometimes emit a warning like this on startup:
//
//   No entry for terminal type "unknown";
//   using dumb terminal settings.
//
// This can be fixed by adding "TERM=dumb" to the shebang line, but doing so
// causes some systems to hang mysteriously. See T7119.

// Commit hooks execute in an unusual context where the environment may be
// unavailable, particularly in SVN. The first parameter to this script is
// either a bare repository identifier ("X"), or a repository identifier
// followed by an instance identifier ("X:instance"). If we have an instance
// identifier, unpack it into the environment before we start up. This allows
// subclasses of PhabricatorConfigSiteSource to read it and build an instance
// environment.

if ($argc > 1) {
  $context = $argv[1];
  $context = explode(':', $context, 2);
  $argv[1] = $context[0];

  if (count($context) > 1) {
    $_ENV['PHABRICATOR_INSTANCE'] = $context[1];
    putenv('PHABRICATOR_INSTANCE='.$context[1]);
  }
}

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

if ($argc < 2) {
  throw new Exception(pht('usage: commit-hook <repository>'));
}

$engine = new DiffusionCommitHookEngine();

$repository = id(new PhabricatorRepositoryQuery())
  ->setViewer(PhabricatorUser::getOmnipotentUser())
  ->withIdentifiers(array($argv[1]))
  ->needProjectPHIDs(true)
  ->executeOne();

if (!$repository) {
  throw new Exception(pht('No such repository "%s"!', $argv[1]));
}

if (!$repository->isHosted()) {
  // This should be redundant, but double check just in case.
  throw new Exception(pht('Repository "%s" is not hosted!', $argv[1]));
}

$engine->setRepository($repository);

$args = new PhutilArgumentParser($argv);
$args->parsePartial(
  array(
    array(
      'name' => 'hook-mode',
      'param' => 'mode',
      'help' => pht('Hook execution mode.'),
    ),
  ));

$argv = array_merge(
  array($argv[0]),
  $args->getUnconsumedArgumentVector());

// Figure out which user is writing the commit.
$hook_mode = $args->getArg('hook-mode');
if ($hook_mode !== null) {
  $known_modes = array(
    'svn-revprop' => true,
  );

  if (empty($known_modes[$hook_mode])) {
    throw new Exception(
      pht(
        'Invalid Hook Mode: This hook was invoked in "%s" mode, but this '.
        'is not a recognized hook mode. Valid modes are: %s.',
        $hook_mode,
        implode(', ', array_keys($known_modes))));
  }
}

$is_svnrevprop = ($hook_mode == 'svn-revprop');

if ($is_svnrevprop) {
  // For now, we let these through if the repository allows dangerous changes
  // and prevent them if it doesn't. See T11208 for discussion.

  $revprop_key = $argv[5];

  if ($repository->shouldAllowDangerousChanges()) {
    $err = 0;
  } else {
    $err = 1;

    $console = PhutilConsole::getConsole();
    $console->writeErr(
      pht(
        "DANGEROUS CHANGE: Dangerous change protection is enabled for this ".
        "repository, so you can not change revision properties (you are ".
        "attempting to edit \"%s\").\n".
        "Edit the repository configuration before making dangerous changes.",
        $revprop_key));
  }

  exit($err);
} else if ($repository->isGit() || $repository->isHg()) {
  $username = getenv(DiffusionCommitHookEngine::ENV_USER);
  if (!strlen($username)) {
    throw new Exception(
      pht(
        'No Direct Pushes: You are pushing directly to a repository hosted '.
        'by Phabricator. This will not work. See "No Direct Pushes" in the '.
        'documentation for more information.'));
  }

  if ($repository->isHg()) {
    // We respond to several different hooks in Mercurial.
    $engine->setMercurialHook($argv[2]);
  }

} else if ($repository->isSVN()) {
  // NOTE: In Subversion, the entire environment gets wiped so we can't read
  // DiffusionCommitHookEngine::ENV_USER. Instead, we've set "--tunnel-user" to
  // specify the correct user; read this user out of the commit log.

  if ($argc < 4) {
    throw new Exception(pht('usage: commit-hook <repository> <repo> <txn>'));
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
$engine->setOriginalArgv(array_slice($argv, 2));

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
