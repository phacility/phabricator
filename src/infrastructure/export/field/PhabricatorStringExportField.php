<?php

final class PhabricatorStringExportField
  extends PhabricatorExportField {

  public function getNaturalValue($value) {
    if ($value === null) {
      return $value;
    }

    if (!strlen($value)) {
      return null;
    }

    return (string)$value;
  }

}
