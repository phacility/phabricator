<?php

final class PhabricatorDaemonManagementLaunchWorkflow
  extends PhabricatorDaemonManagementWorkflow {

  public function shouldParsePartial() {
    return true;
  }

  protected function didConstruct() {
    $this
      ->setName('launch')
      ->setExamples('**launch** [n] __daemon__ [options]')
      ->setSynopsis(pht(
        'Start a specific __daemon__, or __n__ copies of a specific '.
        '__daemon__.'))
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

    $daemon = array();
    $daemon['class'] = array_shift($argv);
    $daemon['argv'] = $argv;

    $daemons = array_fill(0, $daemon_count, $daemon);

    $this->launchDaemons($daemons, $is_debug = false);

    return 0;
  }

}
