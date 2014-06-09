<?php

abstract class PhabricatorAphlictManagementWorkflow
  extends PhabricatorManagementWorkflow {

  final public function getPIDPath() {
    return PhabricatorEnv::getEnvConfig('notification.pidfile');
  }

  final public function getPID() {
    $pid = null;
    if (Filesystem::pathExists($this->getPIDPath())) {
      $pid = (int)Filesystem::readFile($this->getPIDPath());
    }
    return $pid;
  }

  final public function cleanup($signo = '?') {
    global $g_future;
    if ($g_future) {
      $g_future->resolveKill();
      $g_future = null;
    }

    Filesystem::remove($this->getPIDPath());

    exit(1);
  }

  public static function requireExtensions() {
    self::mustHaveExtension('pcntl');
    self::mustHaveExtension('posix');
  }

  private static function mustHaveExtension($ext) {
    if (!extension_loaded($ext)) {
      echo "ERROR: The PHP extension '{$ext}' is not installed. You must ".
           "install it to run aphlict on this machine.\n";
      exit(1);
    }

    $extension = new ReflectionExtension($ext);
    foreach ($extension->getFunctions() as $function) {
      $function = $function->name;
      if (!function_exists($function)) {
        echo "ERROR: The PHP function {$function}() is disabled. You must ".
             "enable it to run aphlict on this machine.\n";
        exit(1);
      }
    }
  }

  final protected function willLaunch() {
    $console = PhutilConsole::getConsole();

    $pid = $this->getPID();
    if ($pid) {
      throw new PhutilArgumentUsageException(
        pht(
          'Unable to start notifications server because it is already '.
          'running. Use `aphlict restart` to restart it.'));
    }

    if (posix_getuid() != 0) {
      throw new PhutilArgumentUsageException(
        pht(
          'You must run this script as root; the Aphlict server needs to bind '.
          'to privileged ports.'));
    }

    // This will throw if we can't find an appropriate `node`.
    $this->getNodeBinary();
  }

  final protected function launch($debug = false) {
    $console = PhutilConsole::getConsole();

    if ($debug) {
      $console->writeOut(pht("Starting Aphlict server in foreground...\n"));
    } else {
      Filesystem::writeFile($this->getPIDPath(), getmypid());
    }

    $server_uri = PhabricatorEnv::getEnvConfig('notification.server-uri');
    $server_uri = new PhutilURI($server_uri);

    $client_uri = PhabricatorEnv::getEnvConfig('notification.client-uri');
    $client_uri = new PhutilURI($client_uri);

    $user = PhabricatorEnv::getEnvConfig('notification.user');
    $log  = PhabricatorEnv::getEnvConfig('notification.log');

    $server_argv = array();
    $server_argv[] = csprintf('--port=%s', $client_uri->getPort());
    $server_argv[] = csprintf('--admin=%s', $server_uri->getPort());
    $server_argv[] = csprintf('--host=%s', $server_uri->getDomain());

    if ($user) {
      $server_argv[] = csprintf('--user=%s', $user);
    }

    if (!$debug) {
      $server_argv[] = csprintf('--log=%s', $log);
    }

    $command = csprintf(
      '%s %s %C',
      $this->getNodeBinary(),
      dirname(__FILE__).'/../../../../support/aphlict/server/aphlict_server.js',
      implode(' ', $server_argv));

    if (!$debug) {
      declare(ticks = 1);
      pcntl_signal(SIGINT, array($this, 'cleanup'));
      pcntl_signal(SIGTERM, array($this, 'cleanup'));
    }
    register_shutdown_function(array($this, 'cleanup'));

    if ($debug) {
      $console->writeOut("Launching server:\n\n    $ ".$command."\n\n");

      $err = phutil_passthru('%C', $command);
      $console->writeOut(">>> Server exited!\n");
      exit($err);
    } else {
      while (true) {
        global $g_future;
        $g_future = new ExecFuture('exec %C', $command);
        $g_future->resolve();

        // If the server exited, wait a couple of seconds and restart it.
        unset($g_future);
        sleep(2);
      }
    }
  }


/* -(  Commands  )----------------------------------------------------------- */


  final protected function executeStartCommand() {
    $console = PhutilConsole::getConsole();
    $this->willLaunch();

    $pid = pcntl_fork();
    if ($pid < 0) {
      throw new Exception('Failed to fork()!');
    } else if ($pid) {
      $console->writeErr(pht("Aphlict Server started.\n"));
      exit(0);
    }

    // When we fork, the child process will inherit its parent's set of open
    // file descriptors. If the parent process of bin/aphlict is waiting for
    // bin/aphlict's file descriptors to close, it will be stuck waiting on
    // the daemonized process. (This happens if e.g. bin/aphlict is started
    // in another script using passthru().)
    fclose(STDOUT);
    fclose(STDERR);

    $this->launch();
    return 0;
  }


  final protected function executeStopCommand() {
    $console = PhutilConsole::getConsole();

    $pid = $this->getPID();
    if (!$pid) {
      $console->writeErr(pht("Aphlict is not running.\n"));
      return 0;
    }

    $console->writeErr(pht("Stopping Aphlict Server (%s)...\n", $pid));
    posix_kill($pid, SIGINT);

    $start = time();
    do {
      if (!PhabricatorDaemonReference::isProcessRunning($pid)) {
        $console->writeOut(
          "%s\n",
          pht('Aphlict Server (%s) exited normally.', $pid));
        $pid = null;
        break;
      }
      usleep(100000);
    } while (time() < $start + 5);

    if ($pid) {
      $console->writeErr(pht('Sending %s a SIGKILL.', $pid)."\n");
      posix_kill($pid, SIGKILL);
      unset($pid);
    }

    Filesystem::remove($this->getPIDPath());
    return 0;
  }

  private function getNodeBinary() {
    if (Filesystem::binaryExists('nodejs')) {
      return 'nodejs';
    }

    if (Filesystem::binaryExists('node')) {
      return 'node';
    }

    throw new PhutilArgumentUsageException(
      pht(
        'No `nodejs` or `node` binary was found in $PATH. You must install '.
        'Node.js to start the Aphlict server.'));
  }

}
