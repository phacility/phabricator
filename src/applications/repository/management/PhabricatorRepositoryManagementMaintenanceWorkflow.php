<?php

final class PhabricatorRepositoryManagementMaintenanceWorkflow
  extends PhabricatorRepositoryManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('maintenance')
      ->setExamples(
        "**maintenance** --start __message__ __repository__ ...\n".
        "**maintenance** --stop __repository__")
      ->setSynopsis(
        pht('Set or clear read-only mode for repository maintenance.'))
      ->setArguments(
        array(
          array(
            'name' => 'start',
            'param' => 'message',
            'help' => pht(
              'Put repositories into maintenance mode.'),
          ),
          array(
            'name' => 'stop',
            'help' => pht(
              'Take repositories out of maintenance mode, returning them '.
              'to normal serice.'),
          ),
          array(
            'name' => 'repositories',
            'wildcard' => true,
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $viewer = $this->getViewer();

    $repositories = $this->loadRepositories($args, 'repositories');
    if (!$repositories) {
      throw new PhutilArgumentUsageException(
        pht('Specify one or more repositories to act on.'));
    }

    $message = $args->getArg('start');
    $is_start = (bool)strlen($message);
    $is_stop = $args->getArg('stop');

    if (!$is_start && !$is_stop) {
      throw new PhutilArgumentUsageException(
        pht(
          'Use "--start <message>" to put repositories into maintenance '.
          'mode, or "--stop" to take them out of maintenance mode.'));
    }

    if ($is_start && $is_stop) {
      throw new PhutilArgumentUsageException(
        pht(
          'Specify either "--start" or "--stop", but not both.'));
    }

    $content_source = $this->newContentSource();
    $diffusion_phid = id(new PhabricatorDiffusionApplication())->getPHID();

    if ($is_start) {
      $new_value = $message;
    } else {
      $new_value = null;
    }

    foreach ($repositories as $repository) {
      $xactions = array();

      $xactions[] = $repository->getApplicationTransactionTemplate()
        ->setTransactionType(
          PhabricatorRepositoryMaintenanceTransaction::TRANSACTIONTYPE)
        ->setNewValue($new_value);

      $repository->getApplicationTransactionEditor()
        ->setActor($viewer)
        ->setActingAsPHID($diffusion_phid)
        ->setContentSource($content_source)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->applyTransactions($repository, $xactions);

      if ($is_start) {
        echo tsprintf(
          "%s\n",
          pht(
            'Put repository "%s" into maintenance mode.',
            $repository->getDisplayName()));
      } else {
        echo tsprintf(
          "%s\n",
          pht(
            'Took repository "%s" out of maintenance mode.',
            $repository->getDisplayName()));
      }
    }

    return 0;
  }

}
