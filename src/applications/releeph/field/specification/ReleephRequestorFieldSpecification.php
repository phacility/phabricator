<?php

final class ReleephRequestorFieldSpecification
  extends ReleephFieldSpecification {

  public function getFieldKey() {
    return 'requestor';
  }

  public function getName() {
    return 'Requestor';
  }

  public function renderValueForHeaderView() {
    $phid = $this->getReleephRequest()->getRequestUserPHID();
    $handle = id(new PhabricatorHandleQuery())
      ->setViewer($this->getUser())
      ->withPHIDs(array($phid))
      ->executeOne();
    return $handle->renderLink();
  }

  public function shouldAppearOnCommitMessage() {
    return true;
  }

  public function shouldAppearOnRevertMessage() {
    return true;
  }

  public function renderLabelForCommitMessage() {
    return "Requested By";
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
