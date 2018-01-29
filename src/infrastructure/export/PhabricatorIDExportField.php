<?php

final class PhabricatorIDExportField
  extends PhabricatorExportField {

  public function getNaturalValue($value) {
    return (int)$value;
  }

  public function getCharacterWidth() {
    return 12;
  }

}
