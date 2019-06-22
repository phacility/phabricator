<?php

final class PhabricatorEditEngineSubtypeTransaction
  extends PhabricatorEditEngineTransactionType {

  const TRANSACTIONTYPE = 'editengine.config.subtype';

  public function generateOldValue($object) {
    return $object->getSubtype();
  }

  public function applyInternalEffects($object, $value) {
    $object->setSubtype($value);
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    return pht(
      '%s changed the subtype of this form from %s to %s.',
      $this->renderAuthor(),
      $this->renderOldValue(),
      $this->renderNewValue());
  }

  public function validateTransactions($object, array $xactions) {
    $map = $object->getEngine()
      ->setViewer($this->getActor())
      ->newSubtypeMap();

    $errors = array();
    foreach ($xactions as $xaction) {
      $new = $xaction->getNewValue();

      if ($map->isValidSubtype($new)) {
        continue;
      }

      $errors[] = $this->newInvalidError(
        pht('Subtype "%s" is not a valid subtype.', $new),
        $xaction);
    }

    return $errors;
  }

}
