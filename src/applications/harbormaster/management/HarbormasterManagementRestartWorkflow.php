<?php

final class HarbormasterManagementRestartWorkflow
  extends HarbormasterManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('restart')
      ->setExamples(
        "**restart** --active\n".
        '**restart** --id id')
      ->setSynopsis(pht('Restart Harbormaster builds.'))
      ->setArguments(
        array(
          array(
            'name' => 'id',
            'param' => 'id',
            'repeat' => true,
            'help' => pht('Select one or more builds by ID.'),
          ),
          array(
            'name' => 'active',
            'help' => pht('Select all active builds.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $viewer = $this->getViewer();
    $ids = $args->getArg('id');
    $active = $args->getArg('active');

    if (!$ids && !$active) {
      throw new PhutilArgumentUsageException(
        pht('Use --id or --active to select builds.'));
    } if ($ids && $active) {
      throw new PhutilArgumentUsageException(
        pht('Use one of --id or --active to select builds, but not both.'));
    }

    $query = id(new HarbormasterBuildQuery())
      ->setViewer($viewer);
    if ($ids) {
      $query->withIDs($ids);
    } else {
      $query->withBuildStatuses(
        HarbormasterBuildStatus::getActiveStatusConstants());
    }
    $builds = $query->execute();

    $console = PhutilConsole::getConsole();
    $count = count($builds);
    if (!$count) {
      $console->writeOut("%s\n", pht('No builds to restart.'));
      return 0;
    }
    $prompt = pht('Restart %s build(s)?', new PhutilNumber($count));
    if (!phutil_console_confirm($prompt)) {
      $console->writeOut("%s\n", pht('Cancelled.'));
      return 1;
    }

    $app_phid = id(new PhabricatorHarbormasterApplication())->getPHID();
    $editor = id(new HarbormasterBuildTransactionEditor())
      ->setActor($viewer)
      ->setActingAsPHID($app_phid)
      ->setContentSource($this->newContentSource());
    foreach ($builds as $build) {
      $console->writeOut(
        "<bg:blue> %s </bg> %s\n",
        pht('RESTARTING'),
        pht('Build %d: %s', $build->getID(), $build->getName()));
      if (!$build->canRestartBuild()) {
        $console->writeOut(
          "<bg:yellow> %s </bg> %s\n",
          pht('INVALID'),
          pht('Cannot be restarted.'));
        continue;
      }
      $xactions = array();
      $xactions[] = id(new HarbormasterBuildTransaction())
        ->setTransactionType(HarbormasterBuildTransaction::TYPE_COMMAND)
        ->setNewValue(HarbormasterBuildCommand::COMMAND_RESTART);
      try {
        $editor->applyTransactions($build, $xactions);
      } catch (Exception $e) {
        $message = phutil_console_wrap($e->getMessage(), 2);
        $console->writeOut(
          "<bg:red> %s </bg>\n%s\n",
          pht('FAILED'),
          $message);
        continue;
      }
      $console->writeOut("<bg:green> %s </bg>\n", pht('SUCCESS'));
    }

    return 0;
  }

}
