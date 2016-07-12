<?php

final class PhabricatorEditEnginePointsCommentAction
  extends PhabricatorEditEngineCommentAction {

  public function getPHUIXControlType() {
    return 'points';
  }

  public function getPHUIXControlSpecification() {
    return array(
      'value' => $this->getValue(),
    );
  }

}
