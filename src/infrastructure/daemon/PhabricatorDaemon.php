<?php

abstract class PhabricatorDaemon extends PhutilDaemon {

  protected function willRun() {
    parent::willRun();

    $phabricator = phutil_get_library_root('phabricator');
    $root = dirname($phabricator);
    require_once $root.'/scripts/__init_script__.php';
  }

  protected function willSleep($duration) {
    LiskDAO::closeInactiveConnections(60);
    return;
  }

  public function getViewer() {
    return PhabricatorUser::getOmnipotentUser();
  }


  /**
   * Format a command so it executes as the daemon user, if a daemon user is
   * defined. This wraps the provided command in `sudo -u ...`, roughly.
   *
   * @param   PhutilCommandString Command to execute.
   * @return  PhutilCommandString `sudo` version of the command.
   */
  public static function sudoCommandAsDaemonUser($command) {
    $user = PhabricatorEnv::getEnvConfig('phd.user');
    if (!$user) {
      // No daemon user is set, so just run this as ourselves.
      return $command;
    }

    // We may reach this method while already running as the daemon user: for
    // example, active and passive synchronization of clustered repositories
    // run the same commands through the same code, but as different users.

    // By default, `sudo` won't let you sudo to yourself, so we can get into
    // trouble if we're already running as the daemon user unless the host has
    // been configured to let the daemon user run commands as itself.

    // Since this is silly and more complicated than doing this check, don't
    // use `sudo` if we're already running as the correct user.
    if (function_exists('posix_getuid')) {
      $uid = posix_getuid();
      $info = posix_getpwuid($uid);
      if ($info && $info['name'] == $user) {
        return $command;
      }
    }

    // Get the absolute path so we're safe against the caller wiping out
    // PATH.
    $sudo = Filesystem::resolveBinary('sudo');
    if (!$sudo) {
      throw new Exception(pht("Unable to find 'sudo'!"));
    }

    // Flags here are:
    //
    //   -E: Preserve the environment.
    //   -n: Non-interactive. Exit with an error instead of prompting.
    //   -u: Which user to sudo to.

    return csprintf('%s -E -n -u %s -- %C', $sudo, $user, $command);
  }

}
