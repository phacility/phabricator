<?php

class PhabricatorUpdateUpliftCommentAction
  extends PhabricatorEditEngineCommentAction {

  public function getPHUIXControlType() {
    return 'remarkup';
  }

  public function getPHUIXControlSpecification() {
    $value = $this->getValue();

    if (empty($value)) {
      $value = $this->getInitialValue();
    }

    if (empty($value)) {
      $value = null;
    }

    return array(
      'value' => pht($value),
    );
  }

}
