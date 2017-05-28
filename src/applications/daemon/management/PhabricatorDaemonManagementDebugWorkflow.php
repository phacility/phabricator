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
            'name' => 'pool',
            'param' => 'count',
            'help' => pht('Maximum pool size.'),
            'default' => 1,
          ),
          array(
            'name' => 'as-current-user',
            'help' => pht(
              'Run the daemon as the current user '.
              'instead of the configured %s',
              'phd.user'),
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

    $config = array(
      'class' => array_shift($argv),
      'label' => 'debug',
      'pool' => (int)$args->getArg('pool'),
      'argv' => $argv,
    );

    return $this->launchDaemons(
      array(
        $config,
      ),
      $is_debug = true,
      $run_as_current_user);
  }

}
