<?php

final class PhabricatorBoolEditField
  extends PhabricatorEditField {

  protected function newControl() {
    return id(new AphrontFormSelectControl())
      ->setOptions(
        array(
          '0' => pht('False'),
          '1' => pht('True'),
        ));
  }

  protected function newHTTPParameterType() {
    return new AphrontBoolHTTPParameterType();
  }

  protected function newConduitParameterType() {
    return new ConduitBoolParameterType();
  }

}
