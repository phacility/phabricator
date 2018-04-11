<?php

final class AlmanacSetPropertyEditType
  extends PhabricatorEditType {

  public function generateTransactions(
    PhabricatorApplicationTransaction $template,
    array $spec) {

    $value = idx($spec, 'value');
    if (!is_array($value)) {
      throw new Exception(
        pht(
          'Transaction value when setting Almanac properties must be a map '.
          'with property names as keys.'));
    }

    $xactions = array();
    foreach ($value as $property_key => $property_value) {
      $xactions[] = $this->newTransaction($template)
        ->setMetadataValue('almanac.property', $property_key)
        ->setNewValue($property_value);
    }

    return $xactions;
  }

}
