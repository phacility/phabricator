<?php

final class NuanceItemPropertyTransaction
  extends NuanceItemTransactionType {

  const TRANSACTIONTYPE = 'nuance.item.property';

  public function generateOldValue($object) {
    $property_key = NuanceItemTransaction::PROPERTY_KEY;
    $key = $this->getMetadataValue($property_key);
    return $object->getNuanceProperty($key);
  }

  public function applyInternalEffects($object, $value) {
    $property_key = NuanceItemTransaction::PROPERTY_KEY;
    $key = $this->getMetadataValue($property_key);

    $object->setNuanceProperty($key, $value);
  }

  public function getTitle() {
    return pht(
      '%s set a property on this item.',
      $this->renderAuthor());
  }

}
