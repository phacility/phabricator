<?php

final class ReleephRequestorFieldSpecification
  extends ReleephFieldSpecification {

  public function getFieldKey() {
    return 'requestor';
  }

  public function getName() {
    return pht('Requestor');
  }

  public function getRequiredHandlePHIDsForPropertyView() {
    $phids = array();

    $phid = $this->getReleephRequest()->getRequestUserPHID();
    if ($phid) {
      $phids[] = $phid;
    }

    return $phids;
  }

  public function renderPropertyViewValue(array $handles) {
    return $this->renderHandleList($handles);
  }

  public function shouldAppearOnCommitMessage() {
    return true;
  }

  public function shouldAppearOnRevertMessage() {
    return true;
  }

  public function renderLabelForCommitMessage() {
    return pht('Requested By');
  }

  public function renderValueForCommitMessage() {
    $phid = $this->getReleephRequest()->getRequestUserPHID();
    $handle = id(new PhabricatorHandleQuery())
      ->setViewer($this->getUser())
      ->withPHIDs(array($phid))
      ->executeOne();
    return $handle->getName();
  }

}
