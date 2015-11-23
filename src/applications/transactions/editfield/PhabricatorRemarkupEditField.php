<?php

final class PhabricatorRemarkupEditField
  extends PhabricatorEditField {

  protected function newControl() {
    return new PhabricatorRemarkupControl();
  }

}
