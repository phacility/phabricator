<?php

final class PhabricatorDaemonManagementDebugWorkflow
  extends PhabricatorDaemonManagementWorkflow {

  public function shouldParsePartial() {
    return true;
  }

  public function didConstruct() {
    $this
      ->setName('debug')
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

    if (!$argv) {
      throw new PhutilArgumentUsageException(
        pht('You must specify which daemon to debug.'));
    }

    $daemon_class = array_shift($argv);
    return $this->launchDaemon($daemon_class, $argv, $is_debug = true);
  }

}
