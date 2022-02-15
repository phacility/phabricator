<?php

class PhabricatorUpdateUpliftCommentAction
  extends PhabricatorEditEngineCommentAction {

  public function getPHUIXControlType() {
    return 'form';
  }

  public function getPHUIXControlSpecification() {
    $value = $this->getValue();

    if (empty($value) || $value == null) {
      $value = $this->getInitialValue();
    }

    return array(
        'questions' => $value,
    );

  }

}
