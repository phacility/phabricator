<?php

final class PhabricatorAuditInlineComment
  extends PhabricatorInlineComment {

  protected function newStorageObject() {
    return new PhabricatorAuditTransactionComment();
  }

  public function getControllerURI() {
    return urisprintf(
      '/diffusion/inline/edit/%s/',
      $this->getCommitPHID());
  }

  public function supportsHiding() {
    return false;
  }

  public function isHidden() {
    return false;
  }

  public function getTransactionCommentForSave() {
    $content_source = PhabricatorContentSource::newForSource(
      PhabricatorOldWorldContentSource::SOURCECONST);

    $this->getStorageObject()
      ->setViewPolicy('public')
      ->setEditPolicy($this->getAuthorPHID())
      ->setContentSource($content_source)
      ->setCommentVersion(1);

    return $this->getStorageObject();
  }

  public static function newFromModernComment(
    PhabricatorAuditTransactionComment $comment) {

    $obj = new PhabricatorAuditInlineComment();
    $obj->setStorageObject($comment);

    return $obj;
  }

  public function setPathID($id) {
    $this->getStorageObject()->setPathID($id);
    return $this;
  }

  public function getPathID() {
    return $this->getStorageObject()->getPathID();
  }

  public function setCommitPHID($commit_phid) {
    $this->getStorageObject()->setCommitPHID($commit_phid);
    return $this;
  }

  public function getCommitPHID() {
    return $this->getStorageObject()->getCommitPHID();
  }

  public function setChangesetID($id) {
    return $this->setPathID($id);
  }

  public function getChangesetID() {
    return $this->getPathID();
  }

}
