<?php

/**
 * @task commitmessage    Integration with Commit Messages
 * @task diff             Integration with Diff Properties
 */
abstract class DifferentialCustomField
  extends PhabricatorCustomField {

  /**
   * TODO: It would be nice to remove this, but a lot of different code is
   * bound together by it. Until everything is modernized, retaining the old
   * field keys is the only reasonable way to update things one piece
   * at a time.
   */
  public function getFieldKeyForConduit() {
    return $this->getFieldKey();
  }

  // TODO: As above.
  public function getModernFieldKey() {
    return $this->getFieldKeyForConduit();
  }

  protected function parseObjectList(
    $value,
    array $types,
    $allow_partial = false,
    array $suffixes = array()) {
    return id(new PhabricatorObjectListQuery())
      ->setViewer($this->getViewer())
      ->setAllowedTypes($types)
      ->setObjectList($value)
      ->setAllowPartialResults($allow_partial)
      ->setSuffixes($suffixes)
      ->execute();
  }

  protected function renderObjectList(
    array $handles,
    array $suffixes = array()) {

    if (!$handles) {
      return null;
    }

    $out = array();
    foreach ($handles as $handle) {
      $phid = $handle->getPHID();

      if ($handle->getPolicyFiltered()) {
        $token = $phid;
      } else if ($handle->isComplete()) {
        $token = $handle->getCommandLineObjectName();
      }

      $suffix = idx($suffixes, $phid);
      $token = $token.$suffix;

      $out[] = $token;
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
  public function getProTips() {
    if ($this->getProxy()) {
      return $this->getProxy()->getProTips();
    }
    return array();
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
