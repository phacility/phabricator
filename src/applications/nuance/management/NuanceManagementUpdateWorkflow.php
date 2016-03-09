<?php

final class NuanceManagementUpdateWorkflow
  extends NuanceManagementWorkflow {

  protected function didConstruct() {
    $this
      ->setName('update')
      ->setExamples('**update** --item __item__ [__options__]')
      ->setSynopsis(pht('Update or route an item.'))
      ->setArguments(
        array(
          array(
            'name' => 'item',
            'param' => 'item',
            'help' => pht('Choose which item to route.'),
          ),
        ));
  }

  public function execute(PhutilArgumentParser $args) {
    $item = $this->loadItem($args, 'item');

    PhabricatorWorker::setRunAllTasksInProcess(true);
    $item->scheduleUpdate();

    return 0;
  }

}
