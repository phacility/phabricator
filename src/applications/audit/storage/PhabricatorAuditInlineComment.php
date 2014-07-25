<?php

final class PhabricatorAuditInlineComment
  extends PhabricatorAuditDAO
  implements PhabricatorInlineCommentInterface {

  protected $commitPHID;
  protected $pathID;
  protected $auditCommentID;

  protected $authorPHID;
  protected $isNewFile;
  protected $lineNumber;
  protected $lineLength;
  protected $content;
  protected $cache;

  private $syntheticAuthor;

  public static function loadDraftComments(
    PhabricatorUser $viewer,
    $commit_phid) {

    return id(new PhabricatorAuditInlineComment())->loadAllWhere(
      'authorPHID = %s AND commitPHID = %s AND auditCommentID IS NULL',
      $viewer->getPHID(),
      $commit_phid);
  }

  public static function loadPublishedComments(
    PhabricatorUser $viewer,
    $commit_phid) {

    return id(new PhabricatorAuditInlineComment())->loadAllWhere(
      'commitPHID = %s AND auditCommentID IS NOT NULL',
      $commit_phid);
  }

  public static function loadDraftAndPublishedComments(
    PhabricatorUser $viewer,
    $commit_phid,
    $path_id = null) {

    if ($path_id === null) {
      return id(new PhabricatorAuditInlineComment())->loadAllWhere(
        'commitPHID = %s AND (auditCommentID IS NOT NULL OR authorPHID = %s)',
        $commit_phid,
        $viewer->getPHID());
    } else {
      return id(new PhabricatorAuditInlineComment())->loadAllWhere(
        'commitPHID = %s AND pathID = %d AND
          (authorPHID = %s OR auditCommentID IS NOT NULL)',
        $commit_phid,
        $path_id,
        $viewer->getPHID());
    }
  }

  public function setSyntheticAuthor($synthetic_author) {
    $this->syntheticAuthor = $synthetic_author;
    return $this;
  }

  public function getSyntheticAuthor() {
    return $this->syntheticAuthor;
  }

  public function isCompatible(PhabricatorInlineCommentInterface $comment) {
    return
      ($this->getAuthorPHID() === $comment->getAuthorPHID()) &&
      ($this->getSyntheticAuthor() === $comment->getSyntheticAuthor()) &&
      ($this->getContent() === $comment->getContent());
  }

  public function setContent($content) {
    $this->setCache(null);
    $this->writeField('content', $content);
    return $this;
  }

  public function getContent() {
    return $this->readField('content');
  }

  public function isDraft() {
    return !$this->getAuditCommentID();
  }

  public function setChangesetID($id) {
    return $this->setPathID($id);
  }

  public function getChangesetID() {
    return $this->getPathID();
  }

  // NOTE: We need to provide implementations so we conform to the shared
  // interface; these are all trivial and just explicit versions of the Lisk
  // defaults.

  public function setIsNewFile($is_new) {
    $this->writeField('isNewFile', $is_new);
    return $this;
  }

  public function getIsNewFile() {
    return $this->readField('isNewFile');
  }

  public function setLineNumber($number) {
    $this->writeField('lineNumber', $number);
    return $this;
  }

  public function getLineNumber() {
    return $this->readField('lineNumber');
  }

  public function setLineLength($length) {
    $this->writeField('lineLength', $length);
    return $this;
  }

  public function getLineLength() {
    return $this->readField('lineLength');
  }

  public function setCache($cache) {
    $this->writeField('cache', $cache);
    return $this;
  }

  public function getCache() {
    return $this->readField('cache');
  }

  public function setAuthorPHID($phid) {
    $this->writeField('authorPHID', $phid);
    return $this;
  }

  public function getAuthorPHID() {
    return $this->readField('authorPHID');
  }

/* -(  PhabricatorMarkupInterface Implementation  )-------------------------- */


  public function getMarkupFieldKey($field) {
    return 'AI:'.$this->getID();
  }

  public function newMarkupEngine($field) {
    return PhabricatorMarkupEngine::newDifferentialMarkupEngine();
  }

  public function getMarkupText($field) {
    return $this->getContent();
  }

  public function didMarkupText($field, $output, PhutilMarkupEngine $engine) {
    return $output;
  }

  public function shouldUseMarkupCache($field) {
    // Only cache submitted comments.
    return ($this->getID() && $this->getAuditCommentID());
  }

}
