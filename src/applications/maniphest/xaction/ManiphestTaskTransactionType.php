<?php

abstract class ManiphestTaskTransactionType
  extends PhabricatorModularTransactionType {

  protected function updateStatus($object, $new_value) {
    $old_value = $object->getStatus();
    $object->setStatus($new_value);

    // If this status change closes or opens the task, update the closed
    // date and actor PHID.
    $old_closed = ManiphestTaskStatus::isClosedStatus($old_value);
    $new_closed = ManiphestTaskStatus::isClosedStatus($new_value);

    $is_close = ($new_closed && !$old_closed);
    $is_open = (!$new_closed && $old_closed);

    if ($is_close) {
      $object
        ->setClosedEpoch(PhabricatorTime::getNow())
        ->setCloserPHID($this->getActingAsPHID());
    } else if ($is_open) {
      $object
        ->setClosedEpoch(null)
        ->setCloserPHID(null);
    }
  }

}
