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

  public static function loadID($id) {
    $inlines = id(new PhabricatorAuditTransactionComment())->loadAllWhere(
      'id = %d',
      $id);
    if (!$inlines) {
      return null;
    }

    return head(self::buildProxies($inlines));
  }

  public static function loadPHID($phid) {
    $inlines = id(new PhabricatorAuditTransactionComment())->loadAllWhere(
      'phid = %s',
      $phid);
    if (!$inlines) {
      return null;
    }
    return head(self::buildProxies($inlines));
  }

  public static function loadDraftComments(
    PhabricatorUser $viewer,
    $commit_phid,
    $raw = false) {

    $inlines = id(new DiffusionDiffInlineCommentQuery())
      ->setViewer($viewer)
      ->withAuthorPHIDs(array($viewer->getPHID()))
      ->withCommitPHIDs(array($commit_phid))
      ->withHasTransaction(false)
      ->withHasPath(true)
      ->withIsDeleted(false)
      ->needReplyToComments(true)
      ->execute();

    if ($raw) {
      return $inlines;
    }

    return self::buildProxies($inlines);
  }

  public static function loadPublishedComments(
    PhabricatorUser $viewer,
    $commit_phid) {

    $inlines = id(new DiffusionDiffInlineCommentQuery())
      ->setViewer($viewer)
      ->withCommitPHIDs(array($commit_phid))
      ->withHasTransaction(true)
      ->withHasPath(true)
      ->execute();

    return self::buildProxies($inlines);
  }

  public static function loadDraftAndPublishedComments(
    PhabricatorUser $viewer,
    $commit_phid,
    $path_id = null) {

    if ($path_id === null) {
      $inlines = id(new PhabricatorAuditTransactionComment())->loadAllWhere(
        'commitPHID = %s AND (transactionPHID IS NOT NULL OR authorPHID = %s)
          AND pathID IS NOT NULL',
        $commit_phid,
        $viewer->getPHID());
    } else {
      $inlines = id(new PhabricatorAuditTransactionComment())->loadAllWhere(
        'commitPHID = %s AND pathID = %d AND
          ((authorPHID = %s AND isDeleted = 0) OR transactionPHID IS NOT NULL)',
        $commit_phid,
        $path_id,
        $viewer->getPHID());
    }

    return self::buildProxies($inlines);
  }

  private static function buildProxies(array $inlines) {
    $results = array();
    foreach ($inlines as $key => $inline) {
      $results[$key] = self::newFromModernComment(
        $inline);
    }
    return $results;
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
