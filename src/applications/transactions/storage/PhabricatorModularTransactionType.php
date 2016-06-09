<?php

abstract class PhabricatorModularTransactionType
  extends Phobject {

  private $storage;
  private $viewer;
  private $editor;

  final public function getTransactionTypeConstant() {
    return $this->getPhobjectClassConstant('TRANSACTIONTYPE');
  }

  public function generateOldValue($object) {
    throw new PhutilMethodNotImplementedException();
  }

  public function generateNewValue($object, $value) {
    return $value;
  }

  public function validateTransactions($object, array $xactions) {
    return array();
  }

  public function willApplyTransactions($object, array $xactions) {
    return;
  }

  public function applyInternalEffects($object, $value) {
    return;
  }

  public function applyExternalEffects($object, $value) {
    return;
  }

  public function extractFilePHIDs($object, $value) {
    return array();
  }

  public function shouldHide() {
    return false;
  }

  public function getIcon() {
    return null;
  }

  public function getTitle() {
    return null;
  }

  public function getTitleForFeed() {
    return null;
  }

  public function getColor() {
    return null;
  }

  public function hasChangeDetailView() {
    return false;
  }

  public function newChangeDetailView() {
    throw new PhutilMethodNotImplementedException();
  }

  final public function setStorage(
    PhabricatorApplicationTransaction $xaction) {
    $this->storage = $xaction;
    return $this;
  }

  private function getStorage() {
    return $this->storage;
  }

  final public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  final protected function getViewer() {
    return $this->viewer;
  }

  final public function setEditor(
    PhabricatorApplicationTransactionEditor $editor) {
    $this->editor = $editor;
    return $this;
  }

  final protected function getEditor() {
    if (!$this->editor) {
      throw new PhutilInvalidStateException('setEditor');
    }
    return $this->editor;
  }

  final protected function getAuthorPHID() {
    return $this->getStorage()->getAuthorPHID();
  }

  final protected function getObjectPHID() {
    return $this->getStorage()->getObjectPHID();
  }

  final protected function getObject() {
    return $this->getStorage()->getObject();
  }

  final protected function getOldValue() {
    return $this->getStorage()->getOldValue();
  }

  final protected function getNewValue() {
    return $this->getStorage()->getNewValue();
  }

  final protected function renderAuthor() {
    $author_phid = $this->getAuthorPHID();
    return $this->getStorage()->renderHandleLink($author_phid);
  }

  final protected function renderObject() {
    $object_phid = $this->getObjectPHID();
    return $this->getStorage()->renderHandleLink($object_phid);
  }

  final protected function newError($title, $message, $xaction = null) {
    return new PhabricatorApplicationTransactionValidationError(
      $this->getTransactionTypeConstant(),
      $title,
      $message,
      $xaction);
  }

}
