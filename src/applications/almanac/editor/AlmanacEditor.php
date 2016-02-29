<?php

abstract class AlmanacEditor
  extends PhabricatorApplicationTransactionEditor {

  public function getEditorApplicationClass() {
    return 'PhabricatorAlmanacApplication';
  }

  protected function supportsSearch() {
    return true;
  }

  public function getTransactionTypes() {
    $types = parent::getTransactionTypes();

    $types[] = AlmanacTransaction::TYPE_PROPERTY_UPDATE;
    $types[] = AlmanacTransaction::TYPE_PROPERTY_REMOVE;

    return $types;
  }

  protected function getCustomTransactionOldValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {
    switch ($xaction->getTransactionType()) {
      case AlmanacTransaction::TYPE_PROPERTY_UPDATE:
      case AlmanacTransaction::TYPE_PROPERTY_REMOVE:
        $property_key = $xaction->getMetadataValue('almanac.property');
        $exists = $object->hasAlmanacProperty($property_key);
        $value = $object->getAlmanacPropertyValue($property_key);
        return array(
          'existed' => $exists,
          'value' => $value,
        );
    }

    return parent::getCustomTransactionOldValue($object, $xaction);
  }

  protected function getCustomTransactionNewValue(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case AlmanacTransaction::TYPE_PROPERTY_UPDATE:
      case AlmanacTransaction::TYPE_PROPERTY_REMOVE:
        return $xaction->getNewValue();
    }

    return parent::getCustomTransactionNewValue($object, $xaction);
  }

  protected function applyCustomInternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case AlmanacTransaction::TYPE_PROPERTY_UPDATE:
      case AlmanacTransaction::TYPE_PROPERTY_REMOVE:
        return;
    }

    return parent::applyCustomInternalTransaction($object, $xaction);
  }

  protected function applyCustomExternalTransaction(
    PhabricatorLiskDAO $object,
    PhabricatorApplicationTransaction $xaction) {

    switch ($xaction->getTransactionType()) {
      case AlmanacTransaction::TYPE_PROPERTY_UPDATE:
        $property_key = $xaction->getMetadataValue('almanac.property');
        if ($object->hasAlmanacProperty($property_key)) {
          $property = $object->getAlmanacProperty($property_key);
        } else {
          $property = id(new AlmanacProperty())
            ->setObjectPHID($object->getPHID())
            ->setFieldName($property_key);
        }
        $property
          ->setFieldValue($xaction->getNewValue())
          ->save();
        return;
      case AlmanacTransaction::TYPE_PROPERTY_REMOVE:
        $property_key = $xaction->getMetadataValue('almanac.property');
        if ($object->hasAlmanacProperty($property_key)) {
          $property = $object->getAlmanacProperty($property_key);
          $property->delete();
        }
        return;
    }

    return parent::applyCustomExternalTransaction($object, $xaction);
  }

  protected function validateTransaction(
    PhabricatorLiskDAO $object,
    $type,
    array $xactions) {

    $errors = parent::validateTransaction($object, $type, $xactions);

    switch ($type) {
      case AlmanacTransaction::TYPE_PROPERTY_UPDATE:
        foreach ($xactions as $xaction) {
          $property_key = $xaction->getMetadataValue('almanac.property');

          $message = null;
          try {
            AlmanacNames::validateName($property_key);
          } catch (Exception $ex) {
            $message = $ex->getMessage();
          }

          if ($message !== null) {
            $error = new PhabricatorApplicationTransactionValidationError(
              $type,
              pht('Invalid'),
              $message,
              $xaction);
            $errors[] = $error;
            continue;
          }

          $new_value = $xaction->getNewValue();
          try {
            phutil_json_encode($new_value);
          } catch (Exception $ex) {
            $message = pht(
              'Almanac property values must be representable in JSON. %s',
              $ex->getMessage());
          }

          if ($message !== null) {
            $error = new PhabricatorApplicationTransactionValidationError(
              $type,
              pht('Invalid'),
              $message,
              $xaction);
            $errors[] = $error;
            continue;
          }
        }
        break;

      case AlmanacTransaction::TYPE_PROPERTY_REMOVE:
        // NOTE: No name validation on removals since it's OK to delete
        // an invalid property that somehow came into existence.
        break;
    }

    return $errors;
  }

}
