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
            'help' => 'Run the daemon as the current user '.
              'instead of the configured phd.user',
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

    $daemon_class = array_shift($argv);
    return $this->launchDaemon(
      $daemon_class,
      $argv,
      $is_debug = true,
      $run_as_current_user);
  }

}
