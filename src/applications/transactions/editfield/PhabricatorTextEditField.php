<?php

final class PhabricatorTextEditField
  extends PhabricatorEditField {

  protected function newControl() {
    return new AphrontFormTextControl();
  }

  protected function newConduitParameterType() {
    return new ConduitStringParameterType();
  }

}
