<?php

abstract class AlmanacTransactionType
  extends PhabricatorModularTransactionType {

  protected function getAlmanacPropertyOldValue($object) {
    $property_key = $this->getMetadataValue('almanac.property');
    $exists = $object->hasAlmanacProperty($property_key);
    $value = $object->getAlmanacPropertyValue($property_key);

    return array(
      'existed' => $exists,
      'value' => $value,
    );
  }

  protected function setAlmanacProperty($object, $value) {
    $property_key = $this->getMetadataValue('almanac.property');

    if ($object->hasAlmanacProperty($property_key)) {
      $property = $object->getAlmanacProperty($property_key);
    } else {
      $property = id(new AlmanacProperty())
        ->setObjectPHID($object->getPHID())
        ->setFieldName($property_key);
    }

    $property
      ->setFieldValue($value)
      ->save();
  }

  protected function deleteAlmanacProperty($object) {
    $property_key = $this->getMetadataValue('almanac.property');
    if ($object->hasAlmanacProperty($property_key)) {
      $property = $object->getAlmanacProperty($property_key);
      $property->delete();
    }
  }

  protected function getAlmanacSetPropertyTitle() {
    $property_key = $this->getMetadataValue('almanac.property');

    return pht(
      '%s updated the property %s.',
      $this->renderAuthor(),
      $this->renderValue($property_key));
  }

  protected function getAlmanacDeletePropertyTitle() {
    $property_key = $this->getMetadataValue('almanac.property');

    return pht(
      '%s removed the property %s.',
      $this->renderAuthor(),
      $this->renderValue($property_key));
  }

  protected function validateAlmanacSetPropertyTransactions(
    $object,
    array $xactions) {
    $errors = array();

    foreach ($xactions as $xaction) {
      $property_key = $xaction->getMetadataValue('almanac.property');

      $message = null;
      try {
        AlmanacNames::validateName($property_key);
      } catch (Exception $ex) {
        $message = $ex->getMessage();
      }

      if ($message !== null) {
        $errors[] = $this->newInvalidError($message, $xaction);
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
        $errors[] = $this->newInvalidError($message, $xaction);
        continue;
      }
    }

    return $errors;
  }

}
