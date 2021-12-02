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
        pht('Use "--id" or "--active" to select builds.'));
    } if ($ids && $active) {
      throw new PhutilArgumentUsageException(
        pht('Use one of "--id" or "--active" to select builds, but not both.'));
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

    $count = count($builds);
    if (!$count) {
      $this->logSkip(
        pht('SKIP'),
        pht('No builds to restart.'));
      return 0;
    }

    $prompt = pht('Restart %s build(s)?', new PhutilNumber($count));
    if (!phutil_console_confirm($prompt)) {
      throw new ArcanistUserAbortException();
    }

    $message = new HarbormasterBuildMessageRestartTransaction();

    foreach ($builds as $build) {
      $this->logInfo(
        pht('RESTARTING'),
        pht('Build %d: %s', $build->getID(), $build->getName()));

      try {
        $message->assertCanSendMessage($viewer, $build);
      } catch (HarbormasterMessageException $ex) {
        $this->logWarn(
          pht('INVALID'),
          $ex->newDisplayString());
      }

      $build->sendMessage(
        $viewer,
        $message->getHarbormasterBuildMessageType());

      $this->logOkay(
        pht('QUEUED'),
        pht('Sent a restart message to build.'));
    }

    return 0;
  }

}
