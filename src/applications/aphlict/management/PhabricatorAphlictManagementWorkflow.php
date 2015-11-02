<?php

abstract class PhabricatorAphlictManagementWorkflow
  extends PhabricatorManagementWorkflow {

  private $debug = false;
  private $clientHost;
  private $clientPort;

  protected function didConstruct() {
    $this
      ->setArguments(
        array(
          array(
            'name'  => 'client-host',
            'param' => 'hostname',
            'help'  => pht('Hostname to bind to for the client server.'),
          ),
          array(
            'name'  => 'client-port',
            'param' => 'port',
            'help'  => pht('Port to bind to for the client server.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $this->clientHost = $args->getArg('client-host');
    $this->clientPort = $args->getArg('client-port');
    return 0;
  }

  final public function getPIDPath() {
    $path = PhabricatorEnv::getEnvConfig('notification.pidfile');

    try {
      $dir = dirname($path);
      if (!Filesystem::pathExists($dir)) {
        Filesystem::createDirectory($dir, 0755, true);
      }
    } catch (FilesystemException $ex) {
      throw new Exception(
        pht(
          "Failed to create '%s'. You should manually create this directory.",
          $dir));
    }

    return $path;
  }

  final public function getLogPath() {
    $path = PhabricatorEnv::getEnvConfig('notification.log');

    try {
      $dir = dirname($path);
      if (!Filesystem::pathExists($dir)) {
        Filesystem::createDirectory($dir, 0755, true);
      }
    } catch (FilesystemException $ex) {
      throw new Exception(
        pht(
          "Failed to create '%s'. You should manually create this directory.",
          $dir));
    }

    return $path;
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

  final protected function setDebug($debug) {
    $this->debug = $debug;
    return $this;
  }

  public static function requireExtensions() {
    self::mustHaveExtension('pcntl');
    self::mustHaveExtension('posix');
  }

  private static function mustHaveExtension($ext) {
    if (!extension_loaded($ext)) {
      echo pht(
        "ERROR: The PHP extension '%s' is not installed. You must ".
        "install it to run Aphlict on this machine.",
        $ext)."\n";
      exit(1);
    }

    $extension = new ReflectionExtension($ext);
    foreach ($extension->getFunctions() as $function) {
      $function = $function->name;
      if (!function_exists($function)) {
        echo pht(
          'ERROR: The PHP function %s is disabled. You must '.
          'enable it to run Aphlict on this machine.',
          $function.'()')."\n";
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
          'running. Use `%s` to restart it.',
          'aphlict restart'));
    }

    if (posix_getuid() == 0) {
      throw new PhutilArgumentUsageException(
        pht('The notification server should not be run as root.'));
    }

    // Make sure we can write to the PID file.
    if (!$this->debug) {
      Filesystem::writeFile($this->getPIDPath(), '');
    }

    // First, start the server in configuration test mode with --test. This
    // will let us error explicitly if there are missing modules, before we
    // fork and lose access to the console.
    $test_argv = $this->getServerArgv();
    $test_argv[] = '--test=true';

    execx(
      '%s %s %Ls',
      $this->getNodeBinary(),
      $this->getAphlictScriptPath(),
      $test_argv);
  }

  private function getServerArgv() {
    $ssl_key = PhabricatorEnv::getEnvConfig('notification.ssl-key');
    $ssl_cert = PhabricatorEnv::getEnvConfig('notification.ssl-cert');

    $server_uri = PhabricatorEnv::getEnvConfig('notification.server-uri');
    $server_uri = new PhutilURI($server_uri);

    $client_uri = PhabricatorEnv::getEnvConfig('notification.client-uri');
    $client_uri = new PhutilURI($client_uri);

    $log = $this->getLogPath();

    $server_argv = array();
    $server_argv[] = '--client-port='.coalesce(
      $this->clientPort,
      $client_uri->getPort());
    $server_argv[] = '--admin-port='.$server_uri->getPort();
    $server_argv[] = '--admin-host='.$server_uri->getDomain();

    if ($ssl_key) {
      $server_argv[] = '--ssl-key='.$ssl_key;
    }

    if ($ssl_cert) {
      $server_argv[] = '--ssl-cert='.$ssl_cert;
    }

    $server_argv[] = '--log='.$log;

    if ($this->clientHost) {
      $server_argv[] = '--client-host='.$this->clientHost;
    }

    return $server_argv;
  }

  private function getAphlictScriptPath() {
    $root = dirname(phutil_get_library_root('phabricator'));
    return $root.'/support/aphlict/server/aphlict_server.js';
  }

  final protected function launch() {
    $console = PhutilConsole::getConsole();

    if ($this->debug) {
      $console->writeOut(
        "%s\n",
        pht('Starting Aphlict server in foreground...'));
    } else {
      Filesystem::writeFile($this->getPIDPath(), getmypid());
    }

    $command = csprintf(
      '%s %s %Ls',
      $this->getNodeBinary(),
      $this->getAphlictScriptPath(),
      $this->getServerArgv());

    if (!$this->debug) {
      declare(ticks = 1);
      pcntl_signal(SIGINT, array($this, 'cleanup'));
      pcntl_signal(SIGTERM, array($this, 'cleanup'));
    }
    register_shutdown_function(array($this, 'cleanup'));

    if ($this->debug) {
      $console->writeOut(
        "%s\n\n    $ %s\n\n",
        pht('Launching server:'),
        $command);

      $err = phutil_passthru('%C', $command);
      $console->writeOut(">>> %s\n", pht('Server exited!'));
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
      throw new Exception(
        pht(
          'Failed to %s!',
          'fork()'));
    } else if ($pid) {
      $console->writeErr("%s\n", pht('Aphlict Server started.'));
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
      $console->writeErr("%s\n", pht('Aphlict is not running.'));
      return 0;
    }

    $console->writeErr("%s\n", pht('Stopping Aphlict Server (%s)...', $pid));
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
      $console->writeErr("%s\n", pht('Sending %s a SIGKILL.', $pid));
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
        'No `%s` or `%s` binary was found in %s. You must install '.
        'Node.js to start the Aphlict server.',
        'nodejs',
        'node',
        '$PATH'));
  }

}
