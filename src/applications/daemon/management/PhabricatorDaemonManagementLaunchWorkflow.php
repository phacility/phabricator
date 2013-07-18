<?php

final class PhabricatorDaemonManagementLaunchWorkflow
  extends PhabricatorDaemonManagementWorkflow {

  public function shouldParsePartial() {
    return true;
  }

  public function didConstruct() {
    $this
      ->setName('launch')
      ->setSynopsis(pht('Show a list of available daemons.'))
      ->setArguments(
        array(
          array(
            'name' => 'argv',
            'wildcard' => true,
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $argv = $args->getArg('argv');

    $daemon_count = 1;
    if ($argv) {
      if (is_numeric(head($argv))) {
        $daemon_count = array_shift($argv);
      }

      if ($daemon_count < 1) {
        throw new PhutilArgumentUsageException(
          pht('You must launch at least one daemon.'));
      }
    }

    if (!$argv) {
      throw new PhutilArgumentUsageException(
        pht('You must specify which daemon to launch.'));
    }

    $daemon_class = array_shift($argv);

    $this->willLaunchDaemons();

    for ($ii = 0; $ii < $daemon_count; $ii++) {
      $this->launchDaemon($daemon_class, $argv, $is_debug = false);
    }

    return 0;
  }

}
