<?php

abstract class PhabricatorInlineComment
  extends Phobject
  implements
    PhabricatorMarkupInterface {

  const MARKUP_FIELD_BODY = 'markup:body';

  const STATE_UNDONE = 'undone';
  const STATE_DRAFT = 'draft';
  const STATE_UNDRAFT = 'undraft';
  const STATE_DONE = 'done';

  private $storageObject;
  private $syntheticAuthor;
  private $isGhost;
  private $versionedDrafts = array();

  public function __clone() {
    $this->storageObject = clone $this->storageObject;
  }

  final public static function loadAndAttachVersionedDrafts(
    PhabricatorUser $viewer,
    array $inlines) {

    $viewer_phid = $viewer->getPHID();
    if (!$viewer_phid) {
      return;
    }

    $inlines = mpull($inlines, null, 'getPHID');

    $load = array();
    foreach ($inlines as $key => $inline) {
      if (!$inline->getIsEditing()) {
        continue;
      }

      if ($inline->getAuthorPHID() !== $viewer_phid) {
        continue;
      }

      $load[$key] = $inline;
    }

    if (!$load) {
      return;
    }

    $drafts = PhabricatorVersionedDraft::loadDrafts(
      array_keys($load),
      $viewer_phid);

    $drafts = mpull($drafts, null, 'getObjectPHID');
    foreach ($inlines as $inline) {
      $draft = idx($drafts, $inline->getPHID());
      $inline->attachVersionedDraftForViewer($viewer, $draft);
    }
  }

  public function setSyntheticAuthor($synthetic_author) {
    $this->syntheticAuthor = $synthetic_author;
    return $this;
  }

  public function getSyntheticAuthor() {
    return $this->syntheticAuthor;
  }

  public function setStorageObject($storage_object) {
    $this->storageObject = $storage_object;
    return $this;
  }

  public function getStorageObject() {
    if (!$this->storageObject) {
      $this->storageObject = $this->newStorageObject();
    }

    return $this->storageObject;
  }

  abstract protected function newStorageObject();
  abstract public function getControllerURI();

  abstract public function setChangesetID($id);
  abstract public function getChangesetID();

  abstract public function supportsHiding();
  abstract public function isHidden();

  public function isDraft() {
    return !$this->getTransactionPHID();
  }

  public function getTransactionPHID() {
    return $this->getStorageObject()->getTransactionPHID();
  }

  public function isCompatible(PhabricatorInlineComment $comment) {
    return
      ($this->getAuthorPHID() === $comment->getAuthorPHID()) &&
      ($this->getSyntheticAuthor() === $comment->getSyntheticAuthor()) &&
      ($this->getContent() === $comment->getContent());
  }

  public function setIsGhost($is_ghost) {
    $this->isGhost = $is_ghost;
    return $this;
  }

  public function getIsGhost() {
    return $this->isGhost;
  }

  public function setContent($content) {
    $this->getStorageObject()->setContent($content);
    return $this;
  }

  public function getContent() {
    return $this->getStorageObject()->getContent();
  }

  public function getID() {
    return $this->getStorageObject()->getID();
  }

  public function getPHID() {
    return $this->getStorageObject()->getPHID();
  }

  public function setIsNewFile($is_new) {
    $this->getStorageObject()->setIsNewFile($is_new);
    return $this;
  }

  public function getIsNewFile() {
    return $this->getStorageObject()->getIsNewFile();
  }

  public function setFixedState($state) {
    $this->getStorageObject()->setFixedState($state);
    return $this;
  }

  public function setHasReplies($has_replies) {
    $this->getStorageObject()->setHasReplies($has_replies);
    return $this;
  }

  public function getHasReplies() {
    return $this->getStorageObject()->getHasReplies();
  }

  public function getFixedState() {
    return $this->getStorageObject()->getFixedState();
  }

  public function setLineNumber($number) {
    $this->getStorageObject()->setLineNumber($number);
    return $this;
  }

  public function getLineNumber() {
    return $this->getStorageObject()->getLineNumber();
  }

  public function setLineLength($length) {
    $this->getStorageObject()->setLineLength($length);
    return $this;
  }

  public function getLineLength() {
    return $this->getStorageObject()->getLineLength();
  }

  public function setAuthorPHID($phid) {
    $this->getStorageObject()->setAuthorPHID($phid);
    return $this;
  }

  public function getAuthorPHID() {
    return $this->getStorageObject()->getAuthorPHID();
  }

  public function setReplyToCommentPHID($phid) {
    $this->getStorageObject()->setReplyToCommentPHID($phid);
    return $this;
  }

  public function getReplyToCommentPHID() {
    return $this->getStorageObject()->getReplyToCommentPHID();
  }

  public function setIsDeleted($is_deleted) {
    $this->getStorageObject()->setIsDeleted($is_deleted);
    return $this;
  }

  public function getIsDeleted() {
    return $this->getStorageObject()->getIsDeleted();
  }

  public function setIsEditing($is_editing) {
    $this->getStorageObject()->setAttribute('editing', (bool)$is_editing);
    return $this;
  }

  public function getIsEditing() {
    return (bool)$this->getStorageObject()->getAttribute('editing', false);
  }

  public function setDocumentEngineKey($engine_key) {
    $this->getStorageObject()->setAttribute('documentEngineKey', $engine_key);
    return $this;
  }

  public function getDocumentEngineKey() {
    return $this->getStorageObject()->getAttribute('documentEngineKey');
  }

  public function setStartOffset($offset) {
    $this->getStorageObject()->setAttribute('startOffset', $offset);
    return $this;
  }

  public function getStartOffset() {
    return $this->getStorageObject()->getAttribute('startOffset');
  }

  public function setEndOffset($offset) {
    $this->getStorageObject()->setAttribute('endOffset', $offset);
    return $this;
  }

  public function getEndOffset() {
    return $this->getStorageObject()->getAttribute('endOffset');
  }

  public function getDateModified() {
    return $this->getStorageObject()->getDateModified();
  }

  public function getDateCreated() {
    return $this->getStorageObject()->getDateCreated();
  }

  public function openTransaction() {
    $this->getStorageObject()->openTransaction();
  }

  public function saveTransaction() {
    $this->getStorageObject()->saveTransaction();
  }

  public function save() {
    $this->getTransactionCommentForSave()->save();
    return $this;
  }

  public function delete() {
    $this->getStorageObject()->delete();
    return $this;
  }

  public function makeEphemeral() {
    $this->getStorageObject()->makeEphemeral();
    return $this;
  }

  public function attachVersionedDraftForViewer(
    PhabricatorUser $viewer,
    PhabricatorVersionedDraft $draft = null) {

    $key = $viewer->getCacheFragment();
    $this->versionedDrafts[$key] = $draft;

    return $this;
  }

  public function hasVersionedDraftForViewer(PhabricatorUser $viewer) {
    $key = $viewer->getCacheFragment();
    return array_key_exists($key, $this->versionedDrafts);
  }

  public function getVersionedDraftForViewer(PhabricatorUser $viewer) {
    $key = $viewer->getCacheFragment();
    if (!array_key_exists($key, $this->versionedDrafts)) {
      throw new Exception(
        pht(
          'Versioned draft is not attached for user with fragment "%s".',
          $key));
    }

    return $this->versionedDrafts[$key];
  }

  public function isVoidComment(PhabricatorUser $viewer) {
    return !strlen($this->getContentForEdit($viewer));
  }

  public function getContentForEdit(PhabricatorUser $viewer) {
    $content = $this->getContent();

    if (!$this->hasVersionedDraftForViewer($viewer)) {
      return $content;
    }

    $versioned_draft = $this->getVersionedDraftForViewer($viewer);
    if (!$versioned_draft) {
      return $content;
    }

    $draft_text = $versioned_draft->getProperty('inline.text');
    if ($draft_text === null) {
      return $content;
    }

    return $draft_text;
  }


/* -(  PhabricatorMarkupInterface Implementation  )-------------------------- */


  public function getMarkupFieldKey($field) {
    $content = $this->getMarkupText($field);
    return PhabricatorMarkupEngine::digestRemarkupContent($this, $content);
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
    return !$this->isDraft();
  }

}
