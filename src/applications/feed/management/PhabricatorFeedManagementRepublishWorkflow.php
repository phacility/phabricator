<?php

final class PhabricatorFeedManagementRepublishWorkflow
  extends PhabricatorFeedManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('republish')
      ->setExamples('**republish** __story_key__')
      ->setSynopsis(
        pht(
          'Republish a feed event to all consumers.'))
      ->setArguments(
        array(
          array(
            'name' => 'key',
            'wildcard' => true,
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $console = PhutilConsole::getConsole();
    $viewer = $this->getViewer();

    $key = $args->getArg('key');
    if (count($key) < 1) {
      throw new PhutilArgumentUsageException(
        pht('Specify a story key to republish.'));
    } else if (count($key) > 1) {
      throw new PhutilArgumentUsageException(
        pht('Specify exactly one story key to republish.'));
    }
    $key = head($key);

    $story = id(new PhabricatorFeedQuery())
      ->setViewer($viewer)
      ->withChronologicalKeys(array($key))
      ->executeOne();

    if (!$story) {
      throw new PhutilArgumentUsageException(
        pht('No story exists with key "%s"!', $key));
    }

    $console->writeOut("%s\n", pht('Republishing story...'));

    PhabricatorWorker::setRunAllTasksInProcess(true);

    PhabricatorWorker::scheduleTask(
      'FeedPublisherWorker',
      array(
        'key' => $key,
      ));

    $console->writeOut("%s\n", pht('Done.'));

    return 0;
  }

}
