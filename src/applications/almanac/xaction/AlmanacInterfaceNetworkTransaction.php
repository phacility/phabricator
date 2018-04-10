<?php

final class AlmanacInterfaceNetworkTransaction
  extends AlmanacInterfaceTransactionType {

  const TRANSACTIONTYPE = 'almanac:interface:network';

  public function generateOldValue($object) {
    return $object->getNetworkPHID();
  }

  public function applyInternalEffects($object, $value) {
    $object->setNetworkPHID($value);
  }

  public function getTitle() {
    return pht(
      '%s changed the network for this interface from %s to %s.',
      $this->renderAuthor(),
      $this->renderOldHandle(),
      $this->renderNewHandle());
  }

  public function validateTransactions($object, array $xactions) {
    $errors = array();

    $network_phid = $object->getNetworkPHID();
    if ($this->isEmptyTextTransaction($network_phid, $xactions)) {
      $errors[] = $this->newRequiredError(
        pht('Interfaces must have a network.'));
    }

    foreach ($xactions as $xaction) {
      $network_phid = $xaction->getNewValue();

      $networks = id(new AlmanacNetworkQuery())
        ->setViewer($this->getActor())
        ->withPHIDs(array($network_phid))
        ->execute();
      if (!$networks) {
        $errors[] = $this->newInvalidError(
          pht(
            'You can not put an interface on a nonexistent or restricted '.
            'network.'),
          $xaction);
        continue;
      }
    }

    return $errors;
  }

}
