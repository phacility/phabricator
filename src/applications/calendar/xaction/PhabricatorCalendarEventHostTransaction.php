<?php

final class PhabricatorCalendarEventHostTransaction
  extends PhabricatorCalendarEventTransactionType {

  const TRANSACTIONTYPE = 'calendar.host';

  public function generateOldValue($object) {
    return $object->getHostPHID();
  }

  public function applyInternalEffects($object, $value) {
    $object->setHostPHID($value);
  }

  public function getTitle() {
    return pht(
      '%s changed the host of this event from %s to %s.',
      $this->renderAuthor(),
      $this->renderOldHandle(),
      $this->renderNewHandle());
  }

  public function getTitleForFeed() {
    return pht(
      '%s changed the host of %s from %s to %s.',
      $this->renderAuthor(),
      $this->renderObject(),
      $this->renderOldHandle(),
      $this->renderNewHandle());
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    foreach ($xactions as $xaction) {
      $host_phid = $xaction->getNewValue();
      if (!$host_phid) {
        $errors[] = $this->newRequiredError(
          pht('Event host is required.'));
        continue;
      }

      $user = id(new PhabricatorPeopleQuery())
        ->setViewer($this->getActor())
        ->withPHIDs(array($host_phid))
        ->executeOne();
      if (!$user) {
        $errors[] = $this->newInvalidError(
          pht(
            'Host PHID "%s" is not a valid user PHID.',
            $host_phid));
      }
    }

    return $errors;
  }

}
