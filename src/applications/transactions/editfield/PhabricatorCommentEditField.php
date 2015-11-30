<?php

final class PhabricatorCommentEditField
  extends PhabricatorEditField {

  protected function newControl() {
    return new PhabricatorRemarkupControl();
  }

  protected function newEditType() {
    return new PhabricatorCommentEditType();
  }

}
