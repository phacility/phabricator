<?php

/**
 * @task commitmessage    Integration with Commit Messages
 * @task diff             Integration with Diff Properties
 */
abstract class DifferentialCustomField
  extends PhabricatorCustomField {

  const ROLE_COMMITMESSAGE      = 'differential:commitmessage';
  const ROLE_COMMITMESSAGEEDIT  = 'differential:commitmessageedit';

  /**
   * TODO: It would be nice to remove this, but a lot of different code is
   * bound together by it. Until everything is modernized, retaining the old
   * field keys is the only reasonable way to update things one piece
   * at a time.
   */
  public function getFieldKeyForConduit() {
    return $this->getFieldKey();
  }

  public function shouldEnableForRole($role) {
    switch ($role) {
      case self::ROLE_COMMITMESSAGE:
        return $this->shouldAppearInCommitMessage();
      case self::ROLE_COMMITMESSAGEEDIT:
        return $this->shouldAppearInCommitMessage() &&
               $this->shouldAllowEditInCommitMessage();
    }

    return parent::shouldEnableForRole($role);
  }

  protected function parseObjectList(
    $value,
    array $types,
    $allow_partial = false) {
    return id(new PhabricatorObjectListQuery())
      ->setViewer($this->getViewer())
      ->setAllowedTypes($types)
      ->setObjectList($value)
      ->setAllowPartialResults($allow_partial)
      ->execute();
  }

  protected function renderObjectList(array $handles) {
    if (!$handles) {
      return null;
    }

    $out = array();
    foreach ($handles as $handle) {
      if ($handle->getPolicyFiltered()) {
        $out[] = $handle->getPHID();
      } else if ($handle->isComplete()) {
        $out[] = $handle->getObjectName();
      }
    }

    return implode(', ', $out);
  }

  public function getWarningsForDetailView() {
    if ($this->getProxy()) {
      return $this->getProxy()->getWarningsForDetailView();
    }
    return array();
  }

  public function getRequiredHandlePHIDsForRevisionHeaderWarnings() {
    return array();
  }

  public function getWarningsForRevisionHeader(array $handles) {
    return array();
  }


/* -(  Integration with Commit Messages  )----------------------------------- */


  /**
   * @task commitmessage
   */
  public function shouldAppearInCommitMessage() {
    if ($this->getProxy()) {
      return $this->getProxy()->shouldAppearInCommitMessage();
    }
    return false;
  }


  /**
   * @task commitmessage
   */
  public function shouldAppearInCommitMessageTemplate() {
    if ($this->getProxy()) {
      return $this->getProxy()->shouldAppearInCommitMessageTemplate();
    }
    return false;
  }


  /**
   * @task commitmessage
   */
  public function shouldAllowEditInCommitMessage() {
    if ($this->getProxy()) {
      return $this->getProxy()->shouldAllowEditInCommitMessage();
    }
    return true;
  }


  /**
   * @task commitmessage
   */
  public function getProTips() {
    if ($this->getProxy()) {
      return $this->getProxy()->getProTips();
    }
    return array();
  }


  /**
   * @task commitmessage
   */
  public function getCommitMessageLabels() {
    if ($this->getProxy()) {
      return $this->getProxy()->getCommitMessageLabels();
    }
    return array($this->renderCommitMessageLabel());
  }


  /**
   * @task commitmessage
   */
  public function parseValueFromCommitMessage($value) {
    if ($this->getProxy()) {
      return $this->getProxy()->parseValueFromCommitMessage($value);
    }
    return $value;
  }


  /**
   * @task commitmessage
   */
  public function readValueFromCommitMessage($value) {
    if ($this->getProxy()) {
      $this->getProxy()->readValueFromCommitMessage($value);
      return $this;
    }
    return $this;
  }


  /**
   * @task commitmessage
   */
  public function shouldOverwriteWhenCommitMessageIsEdited() {
    if ($this->getProxy()) {
      return $this->getProxy()->shouldOverwriteWhenCommitMessageIsEdited();
    }
    return false;
  }


  /**
   * @task commitmessage
   */
  public function getRequiredHandlePHIDsForCommitMessage() {
    if ($this->getProxy()) {
      return $this->getProxy()->getRequiredHandlePHIDsForCommitMessage();
    }
    return array();
  }


  /**
   * @task commitmessage
   */
  public function renderCommitMessageLabel() {
    if ($this->getProxy()) {
      return $this->getProxy()->renderCommitMessageLabel();
    }
    return $this->getFieldName();
  }


  /**
   * @task commitmessage
   */
  public function renderCommitMessageValue(array $handles) {
    if ($this->getProxy()) {
      return $this->getProxy()->renderCommitMessageValue($handles);
    }
    throw new PhabricatorCustomFieldImplementationIncompleteException($this);
  }


  /**
   * @task commitmessage
   */
  public function validateCommitMessageValue($value) {
    if ($this->getProxy()) {
      return $this->getProxy()->validateCommitMessageValue($value);
    }
    return;
  }


/* -(  Integration with Diff Properties  )----------------------------------- */


  /**
   * @task diff
   */
  public function shouldAppearInDiffPropertyView() {
    if ($this->getProxy()) {
      return $this->getProxy()->shouldAppearInDiffPropertyView();
    }
    return false;
  }


  /**
   * @task diff
   */
  public function renderDiffPropertyViewLabel(DifferentialDiff $diff) {
    if ($this->proxy) {
      return $this->proxy->renderDiffPropertyViewLabel($diff);
    }
    return $this->getFieldName();
  }


  /**
   * @task diff
   */
  public function renderDiffPropertyViewValue(DifferentialDiff $diff) {
    if ($this->proxy) {
      return $this->proxy->renderDiffPropertyViewValue($diff);
    }
    throw new PhabricatorCustomFieldImplementationIncompleteException($this);
  }

}
