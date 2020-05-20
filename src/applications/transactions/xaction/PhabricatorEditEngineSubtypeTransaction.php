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
    $errors = array();

    if (!$xactions) {
      return $errors;
    }

    $engine = $object->getEngine();

    if (!$engine->supportsSubtypes()) {
      foreach ($xactions as $xaction) {
        $errors[] = $this->newInvalidError(
          pht(
            'Edit engine (of class "%s") does not support subtypes, so '.
            'subtype transactions can not be applied to it.',
            get_class($engine)),
          $xaction);
      }
      return $errors;
    }

    $map = $engine
      ->setViewer($this->getActor())
      ->newSubtypeMap();

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
