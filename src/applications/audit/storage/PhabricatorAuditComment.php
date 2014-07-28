<?php

final class PhabricatorAuditComment
  implements PhabricatorMarkupInterface {

  const METADATA_ADDED_AUDITORS  = 'added-auditors';
  const METADATA_ADDED_CCS       = 'added-ccs';

  const MARKUP_FIELD_BODY        = 'markup:body';

  private $proxyComment;
  private $proxy;

  public function __construct() {
    $this->proxy = new PhabricatorAuditTransaction();
  }

  public function __clone() {
    $this->proxy = clone $this->proxy;
    if ($this->proxyComment) {
      $this->proxyComment = clone $this->proxyComment;
    }
  }

  public static function newFromModernTransaction(
    PhabricatorAuditTransaction $xaction) {

    $obj = new PhabricatorAuditComment();
    $obj->proxy = $xaction;

    if ($xaction->hasComment()) {
      $obj->proxyComment = $xaction->getComment();
    }

    return $obj;
  }

  public static function loadComments(
    PhabricatorUser $viewer,
    $commit_phid) {

    $xactions = id(new PhabricatorAuditTransactionQuery())
      ->setViewer($viewer)
      ->withObjectPHIDs(array($commit_phid))
      ->needComments(true)
      ->execute();

    $comments = array();
    foreach ($xactions as $xaction) {
      $comments[] = self::newFromModernTransaction($xaction);
    }

    return $comments;
  }

  public function getPHID() {
    return $this->proxy->getPHID();
  }

  public function getActorPHID() {
    return $this->proxy->getAuthorPHID();
  }

  public function setActorPHID($actor_phid) {
    $this->proxy->setAuthorPHID($actor_phid);
    return $this;
  }

  public function setTargetPHID($target_phid) {
    $this->getProxyComment()->setCommitPHID($target_phid);
    $this->proxy->setObjectPHID($target_phid);
    return $this;
  }

  public function getTargetPHID() {
    return $this->proxy->getObjectPHID();
  }

  public function getContent() {
    return $this->getProxyComment()->getContent();
  }

  public function setContent($content) {
    $this->getProxyComment()->setContent($content);
    return $this;
  }

  public function setContentSource($content_source) {
    $this->proxy->setContentSource($content_source);
    $this->proxyComment->setContentSource($content_source);
    return $this;
  }

  public function getContentSource() {
    return $this->proxy->getContentSource();
  }

  private function getProxyComment() {
    if (!$this->proxyComment) {
      $this->proxyComment = new PhabricatorAuditTransactionComment();
    }
    return $this->proxyComment;
  }

  public function setProxyComment(PhabricatorAuditTransactionComment $proxy) {
    if ($this->proxyComment) {
      throw new Exception(pht('You can not overwrite a proxy comment.'));
    }
    $this->proxyComment = $proxy;
    return $this;
  }

  public function setAction($action) {
    switch ($action) {
      case PhabricatorAuditActionConstants::INLINE:
      case PhabricatorAuditActionConstants::ADD_CCS:
      case PhabricatorAuditActionConstants::ADD_AUDITORS:
        $this->proxy->setTransactionType($action);
        break;
      case PhabricatorAuditActionConstants::COMMENT:
        $this->proxy->setTransactionType(PhabricatorTransactions::TYPE_COMMENT);
        break;
      default:
        $this->proxy
          ->setTransactionType(PhabricatorAuditActionConstants::ACTION)
          ->setNewValue($action);
        break;
    }

    return $this;
  }

  public function getAction() {
    $type = $this->proxy->getTransactionType();
    switch ($type) {
      case PhabricatorTransactions::TYPE_COMMENT:
        return PhabricatorAuditActionConstants::COMMENT;
      case PhabricatorAuditActionConstants::INLINE:
      case PhabricatorAuditActionConstants::ADD_CCS:
      case PhabricatorAuditActionConstants::ADD_AUDITORS:
        return $type;
      default:
        return $this->proxy->getNewValue();
    }
  }

  public function setMetadata(array $metadata) {
    if (!$this->proxy->getTransactionType()) {
      throw new Exception(pht('Call setAction() before getMetadata()!'));
    }

    $type = $this->proxy->getTransactionType();
    switch ($type) {
      case PhabricatorAuditActionConstants::ADD_CCS:
        $raw_phids = idx($metadata, self::METADATA_ADDED_CCS, array());
        break;
      case PhabricatorAuditActionConstants::ADD_AUDITORS:
        $raw_phids = idx($metadata, self::METADATA_ADDED_AUDITORS, array());
        break;
      default:
        throw new Exception(pht('No metadata expected!'));
    }

    $this->proxy->setOldValue(array());
    $this->proxy->setNewValue(array_fuse($raw_phids));

    return $this;
  }

  public function getMetadata() {
    if (!$this->proxy->getTransactionType()) {
      throw new Exception(pht('Call setAction() before getMetadata()!'));
    }

    $type = $this->proxy->getTransactionType();
    $new_value = $this->proxy->getNewValue();
    switch ($type) {
      case PhabricatorAuditActionConstants::ADD_CCS:
        return array(
          self::METADATA_ADDED_CCS => array_keys($new_value),
        );
      case PhabricatorAuditActionConstants::ADD_AUDITORS:
        return array(
          self::METADATA_ADDED_AUDITORS => array_keys($new_value),
        );
    }

    return array();
  }

  public function save() {
    $this->proxy->openTransaction();
      $this->proxy
        ->setViewPolicy('public')
        ->setEditPolicy($this->getActorPHID())
        ->save();

      if (strlen($this->getContent())) {
        $this->getProxyComment()
          ->setAuthorPHID($this->getActorPHID())
          ->setViewPolicy('public')
          ->setEditPolicy($this->getActorPHID())
          ->setCommentVersion(1)
          ->setTransactionPHID($this->proxy->getPHID())
          ->save();

        $this->proxy
          ->setCommentVersion(1)
          ->setCommentPHID($this->getProxyComment()->getPHID())
          ->save();
      }
    $this->proxy->saveTransaction();

    return $this;
  }

  public function getDateCreated() {
    return $this->proxy->getDateCreated();
  }

  public function getDateModified() {
    return $this->proxy->getDateModified();
  }


/* -(  PhabricatorMarkupInterface Implementation  )-------------------------- */


  public function getMarkupFieldKey($field) {
    return 'AC:'.$this->getPHID();
  }

  public function newMarkupEngine($field) {
    return PhabricatorMarkupEngine::newDiffusionMarkupEngine();
  }

  public function getMarkupText($field) {
    return $this->getContent();
  }

  public function didMarkupText($field, $output, PhutilMarkupEngine $engine) {
    return $output;
  }

  public function shouldUseMarkupCache($field) {
    return (bool)$this->getPHID();
  }

}
