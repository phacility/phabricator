<?php

final class PhabricatorIntExportField
  extends PhabricatorExportField {

  public function getNaturalValue($value) {
    return (int)$value;
  }

}
