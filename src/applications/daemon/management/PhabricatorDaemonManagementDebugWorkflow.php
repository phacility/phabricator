<?php

final class PhabricatorDaemonManagementDebugWorkflow
  extends PhabricatorDaemonManagementWorkflow {

  public function shouldParsePartial() {
    return true;
  }

  protected function didConstruct() {
    $this
      ->setName('debug')
      ->setExamples('**debug** __daemon__')
      ->setSynopsis(
        pht(
          'Start __daemon__ in the foreground and print large volumes of '.
          'diagnostic information to the console.'))
      ->setArguments(
        array(
          array(
            'name' => 'argv',
            'wildcard' => true,
          ),
          array(
            'name' => 'as-current-user',
            'help' => pht(
              'Run the daemon as the current user '.
              'instead of the configured %s',
              'phd.user'),
          ),
          array(
            'name' => 'autoscale',
            'help' => pht('Put the daemon in an autoscale group.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $argv = $args->getArg('argv');
    $run_as_current_user = $args->getArg('as-current-user');

    if (!$argv) {
      throw new PhutilArgumentUsageException(
        pht('You must specify which daemon to debug.'));
    }

    $config = array();

    $config['class'] = array_shift($argv);
    $config['argv'] = $argv;

    if ($args->getArg('autoscale')) {
      $config['autoscale'] = array(
        'group' => 'debug',
      );
    }

    return $this->launchDaemons(
      array(
        $config,
      ),
      $is_debug = true,
      $run_as_current_user);
  }

}
