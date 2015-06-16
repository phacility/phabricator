<?php

final class PhortuneCurrencySerializer extends PhabricatorLiskSerializer {

  public function willReadValue($value) {
    return PhortuneCurrency::newFromString($value);
  }

  public function willWriteValue($value) {
    if (!($value instanceof PhortuneCurrency)) {
      throw new Exception(
        pht(
          'Trying to save object with a currency column, but the column '.
          'value is not a %s object.',
          'PhortuneCurrency'));
    }

    return $value->serializeForStorage();
  }

}
