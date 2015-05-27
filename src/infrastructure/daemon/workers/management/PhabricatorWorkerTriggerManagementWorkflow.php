<?php

abstract class PhabricatorWorkerTriggerManagementWorkflow
  extends PhabricatorManagementWorkflow {

  protected function getTriggerSelectionArguments() {
    return array(
      array(
        'name' => 'id',
        'param' => 'id',
        'repeat' => true,
        'help' => pht('Select one or more triggers by ID.'),
      ),
    );
  }

  protected function loadTriggers(PhutilArgumentParser $args) {
    $ids = $args->getArg('id');
    if (!$ids) {
      throw new PhutilArgumentUsageException(
        pht('Use %s to select triggers by ID.', '--id'));
    }

    $triggers = id(new PhabricatorWorkerTriggerQuery())
      ->setViewer($this->getViewer())
      ->withIDs($ids)
      ->needEvents(true)
      ->execute();
    $triggers = mpull($triggers, null, 'getID');

    foreach ($ids as $id) {
      if (empty($triggers[$id])) {
        throw new PhutilArgumentUsageException(
          pht('No trigger exists with id "%s"!', $id));
      }
    }

    return $triggers;
  }

  protected function describeTrigger(PhabricatorWorkerTrigger $trigger) {
    return pht('Trigger %d', $trigger->getID());
  }

}
