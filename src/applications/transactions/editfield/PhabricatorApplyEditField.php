<?php

final class PhabricatorApplyEditField
  extends PhabricatorEditField {

  private $actionDescription;

  protected function newControl() {
    return null;
  }

  public function setActionDescription($action_description) {
    $this->actionDescription = $action_description;
    return $this;
  }

  public function getActionDescription() {
    return $this->actionDescription;
  }

  protected function newHTTPParameterType() {
    return new AphrontBoolHTTPParameterType();
  }

  protected function newConduitParameterType() {
    return new ConduitBoolParameterType();
  }

  public function shouldGenerateTransactionsFromSubmit() {
    // This type of edit field just applies a prebuilt action, like "Accept
    // Revision", and can not be submitted as part of an "Edit Object" form.
    return false;
  }

  protected function newCommentAction() {
    return id(new PhabricatorEditEngineStaticCommentAction())
      ->setDescription($this->getActionDescription());
  }

}
