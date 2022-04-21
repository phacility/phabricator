<?php

class PhabricatorUpdateUpliftCommentAction
  extends PhabricatorEditEngineCommentAction {

  public function getPHUIXControlType() {
    return 'form';
  }

  public function getPHUIXControlSpecification() {
    $value = $this->getValue();
    $initial = false;

    if (empty($value) || $value == null) {
      $value = $this->getInitialValue();
      $initial = true;
    }

    return array(
        'initial' => $initial,
        'questions' => $value,
    );

  }

}
