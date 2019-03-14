<?php

final class PhabricatorProjectColumnTriggerTransaction
  extends PhabricatorProjectColumnTransactionType {

  const TRANSACTIONTYPE = 'trigger';

  public function generateOldValue($object) {
    return $object->getTriggerPHID();
  }

  public function applyInternalEffects($object, $value) {
    $object->setTriggerPHID($value);
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    if (!$old) {
      return pht(
        '%s set the column trigger to %s.',
        $this->renderAuthor(),
        $this->renderNewHandle());
    } else if (!$new) {
      return pht(
        '%s removed the trigger for this column (was %s).',
        $this->renderAuthor(),
        $this->renderOldHandle());
    } else {
      return pht(
        '%s changed the trigger for this column from %s to %s.',
        $this->renderAuthor(),
        $this->renderOldHandle(),
        $this->renderNewHandle());
    }
  }

  public function validateTransactions($object, array $xactions) {
    $actor = $this->getActor();
    $errors = array();

    foreach ($xactions as $xaction) {
      $trigger_phid = $xaction->getNewValue();

      // You can always remove a trigger.
      if (!$trigger_phid) {
        continue;
      }

      // You can't put a trigger on a column that can't have triggers, like
      // a backlog column or a proxy column.
      if (!$object->canHaveTrigger()) {
        $errors[] = $this->newInvalidError(
          pht('This column can not have a trigger.'),
          $xaction);
        continue;
      }

      $trigger = id(new PhabricatorProjectTriggerQuery())
        ->setViewer($actor)
        ->withPHIDs(array($trigger_phid))
        ->execute();
      if (!$trigger) {
        $errors[] = $this->newInvalidError(
          pht(
            'Trigger "%s" is not a valid trigger, or you do not have '.
            'permission to view it.',
            $trigger_phid),
          $xaction);
        continue;
      }
    }

    return $errors;
  }

}
