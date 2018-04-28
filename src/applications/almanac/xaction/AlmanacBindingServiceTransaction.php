<?php

final class AlmanacBindingServiceTransaction
  extends AlmanacBindingTransactionType {

  const TRANSACTIONTYPE = 'almanac:binding:service';

  public function generateOldValue($object) {
    return $object->getServicePHID();
  }

  public function applyInternalEffects($object, $value) {
    $object->setServicePHID($value);
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    $service_phid = $object->getServicePHID();
    if ($this->isEmptyTextTransaction($service_phid, $xactions)) {
      $errors[] = $this->newRequiredError(
        pht('Bindings must have a service.'));
    }

    foreach ($xactions as $xaction) {
      if (!$this->isNewObject()) {
        $errors[] = $this->newInvalidError(
          pht(
            'The service for a binding can not be changed once it has '.
            'been created.'),
          $xaction);
        continue;
      }

      $service_phid = $xaction->getNewValue();
      $services = id(new AlmanacServiceQuery())
        ->setViewer($this->getActor())
        ->withPHIDs(array($service_phid))
        ->execute();
      if (!$services) {
        $errors[] = $this->newInvalidError(
          pht('You can not bind a nonexistent or restricted service.'),
          $xaction);
        continue;
      }

      $service = head($services);
      $can_edit = PhabricatorPolicyFilter::hasCapability(
        $this->getActor(),
        $service,
        PhabricatorPolicyCapability::CAN_EDIT);
      if (!$can_edit) {
        $errors[] = $this->newInvalidError(
          pht(
            'You can not bind a service which you do not have permission '.
            'to edit.'));
        continue;
      }
    }

    return $errors;
  }

}
