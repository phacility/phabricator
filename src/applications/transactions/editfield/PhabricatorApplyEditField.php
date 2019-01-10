<?php

final class PhabricatorApplyEditField
  extends PhabricatorEditField {

  private $actionDescription;
  private $actionConflictKey;
  private $actionSubmitButtonText;
  private $options;

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

  public function setActionConflictKey($action_conflict_key) {
    $this->actionConflictKey = $action_conflict_key;
    return $this;
  }

  public function getActionConflictKey() {
    return $this->actionConflictKey;
  }

  public function setActionSubmitButtonText($text) {
    $this->actionSubmitButtonText = $text;
    return $this;
  }

  public function getActionSubmitButtonText() {
    return $this->actionSubmitButtonText;
  }

  public function setOptions(array $options) {
    $this->options = $options;
    return $this;
  }

  public function getOptions() {
    return $this->options;
  }

  protected function newHTTPParameterType() {
    if ($this->getOptions()) {
      return new AphrontPHIDListHTTPParameterType();
    } else {
      return new AphrontBoolHTTPParameterType();
    }
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
    $options = $this->getOptions();
    if ($options) {
      $action = id(new PhabricatorEditEngineCheckboxesCommentAction())
        ->setOptions($options);
    } else {
      $action = id(new PhabricatorEditEngineStaticCommentAction())
        ->setDescription($this->getActionDescription());
    }

    return $action
      ->setConflictKey($this->getActionConflictKey())
      ->setSubmitButtonText($this->getActionSubmitButtonText());
  }

}
