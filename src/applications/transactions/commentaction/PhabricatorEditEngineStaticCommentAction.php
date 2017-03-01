<?php

final class PhabricatorEditEngineStaticCommentAction
  extends PhabricatorEditEngineCommentAction {

  private $description;

  public function setDescription($description) {
    $this->description = $description;
    return $this;
  }

  public function getDescription() {
    return $this->description;
  }

  public function getPHUIXControlType() {
    return 'static';
  }

  public function getPHUIXControlSpecification() {
    return array(
      'value' => $this->getValue(),
      'description' => $this->getDescription(),
    );
  }

}
