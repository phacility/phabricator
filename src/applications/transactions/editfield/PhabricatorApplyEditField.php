<?php

final class PhabricatorApplyEditField
  extends PhabricatorEditField {

  private $actionDescription;
  private $actionConflictKey;
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
      return id(new PhabricatorEditEngineCheckboxesCommentAction())
        ->setConflictKey($this->getActionConflictKey())
        ->setOptions($options);
    } else {
      return id(new PhabricatorEditEngineStaticCommentAction())
        ->setConflictKey($this->getActionConflictKey())
        ->setDescription($this->getActionDescription());
    }
  }

}
