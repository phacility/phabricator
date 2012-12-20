#!/usr/bin/env php
<?php

// This is a launcher for the 'aphlict' Node.js notification server that
// provides real-time notifications for Phabricator. It handles reading
// configuration from the Phabricator config, daemonizing the server,
// restarting the server if it crashes, and some basic sanity checks.


$root = dirname(dirname(dirname(dirname(__FILE__))));
require_once $root.'/scripts/__init_script__.php';


// >>> Options and Arguments ---------------------------------------------------

$args = new PhutilArgumentParser($argv);
$args->setTagline('manage Aphlict notification server');
$args->setSynopsis(<<<EOHELP
**aphlict** [__options__]
  Start (or restart) the Aphlict server.
EOHELP
);
$args->parseStandardArguments();
$args->parse(array(
  array(
    'name' => 'foreground',
    'help' => 'Run in the foreground instead of daemonizing.',
  ),
));

if (posix_getuid() != 0) {
  throw new Exception(
    "You must run this script as root; the Aphlict server needs to bind to ".
    "privileged ports.");
}

list($err) = exec_manual('node -v');
if ($err) {
  throw new Exception(
    '`node` is not in $PATH. You must install Node.js to run the Aphlict '.
    'server.');
}

$server_uri = PhabricatorEnv::getEnvConfig('notification.server-uri');
$server_uri = new PhutilURI($server_uri);

$client_uri = PhabricatorEnv::getEnvConfig('notification.client-uri');
$client_uri = new PhutilURI($client_uri);

$user = PhabricatorEnv::getEnvConfig('notification.user');
$log  = PhabricatorEnv::getEnvConfig('notification.log');

$g_pidfile = PhabricatorEnv::getEnvConfig('notification.pidfile');
$g_future  = null;

$foreground = $args->getArg('foreground');

// Build the argument list for the server itself.
$server_argv = array();
$server_argv[] = csprintf('--port=%s', $client_uri->getPort());
$server_argv[] = csprintf('--admin=%s', $server_uri->getPort());
$server_argv[] = csprintf('--host=%s', $server_uri->getDomain());

if ($user) {
  $server_argv[] = csprintf('--user=%s', $user);
}

if ($log) {
  $server_argv[] = csprintf('--log=%s', $log);
}


// >>> Foreground / Background -------------------------------------------------

// If we start in the foreground, we use phutil_passthru() below to show any
// output from the server to the console, but this means *this* process won't
// receive signals until the child exits. If we write our pid to the pidfile
// and then another process starts, it will try to SIGTERM us but we won't
// receive the signal. Since the effect is the same and this is simpler, just
// ignore the pidfile if launched in `--foreground` mode; this is a debugging
// mode anyway.
if ($foreground) {
  echo "Starting server in foreground, ignoring pidfile...\n";
  $g_pidfile = null;
} else {
  $pid = pcntl_fork();
  if ($pid < 0) {
    throw new Exception("Failed to fork()!");
  } else if ($pid) {
    exit(0);
  }
  // When we fork, the child process will inherit its parent's set of open
  // file descriptors. If the parent process of bin/aphlict is waiting for
  // bin/aphlict's file descriptors to close, it will be stuck waiting on
  // the daemonized process. (This happens if e.g. bin/aphlict is started
  // in another script using passthru().)
  fclose(STDOUT);
  fclose(STDERR);
}


// >>> Signals / Cleanup -------------------------------------------------------

function cleanup($sig = '?') {
  global $g_pidfile;
  if ($g_pidfile) {
    Filesystem::remove($g_pidfile);
    $g_pidfile = null;
  }

  global $g_future;
  if ($g_future) {
    $g_future->resolveKill();
    $g_future = null;
  }

  exit(1);
}

if (!$foreground) {
  declare(ticks = 1);
  pcntl_signal(SIGTERM, 'cleanup');
}

register_shutdown_function('cleanup');


// >>> pidfile -----------------------------------------------------------------

if ($g_pidfile) {
  if (Filesystem::pathExists($g_pidfile)) {
    $old_pid = (int)Filesystem::readFile($g_pidfile);
    posix_kill($old_pid, SIGTERM);
    sleep(1);
    Filesystem::remove($g_pidfile);
  }
  Filesystem::writeFile($g_pidfile, getmypid());
}


// >>> run ---------------------------------------------------------------------

$command = csprintf(
  'node %s %C',
  dirname(__FILE__).'/aphlict_server.js',
  implode(' ', $server_argv));

if ($foreground) {
  echo "Launching server:\n\n";
  echo "    $ ".$command."\n\n";

  $err = phutil_passthru('%C', $command);
  echo ">>> Server exited!\n";
  exit($err);
} else {
  while (true) {
    $g_future = new ExecFuture('exec %C', $command);
    $g_future->resolve();

    // If the server exited, wait a couple of seconds and restart it.
    unset($g_future);
    sleep(2);
  }
}
