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

  public function newRemarkupChanges() {
    return array();
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

  final public function getActor() {
    return $this->getEditor()->getActor();
  }

  final public function getActingAsPHID() {
    return $this->getEditor()->getActingAsPHID();
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

  final protected function renderHandle($phid) {
    $viewer = $this->getViewer();
    $display = $viewer->renderHandle($phid);

    if ($this->isTextMode()) {
      $display->setAsText(true);
    }

    return $display;
  }

  final protected function renderOldHandle() {
    return $this->renderHandle($this->getOldValue());
  }

  final protected function renderNewHandle() {
    return $this->renderHandle($this->getNewValue());
  }

  final protected function renderHandleList(array $phids) {
    $viewer = $this->getViewer();
    $display = $viewer->renderHandleList($phids)
      ->setAsInline(true);

    if ($this->isTextMode()) {
      $display->setAsText(true);
    }

    return $display;
  }

  final protected function renderValue($value) {
    if ($this->isTextMode()) {
      return sprintf('"%s"', $value);
    }

    return phutil_tag(
      'span',
      array(
        'class' => 'phui-timeline-value',
      ),
      $value);
  }

  final protected function renderOldValue() {
    return $this->renderValue($this->getOldValue());
  }

  final protected function renderNewValue() {
    return $this->renderValue($this->getNewValue());
  }

  final protected function renderDate($epoch) {
    $viewer = $this->getViewer();

    $display = phabricator_datetime($epoch, $viewer);

    // When rendering to text, we explicitly render the offset from UTC to
    // provide context to the date: the mail may be generating with the
    // server's settings, or the user may later refer back to it after changing
    // timezones.

    if ($this->isTextMode()) {
      $offset = $viewer->getTimeZoneOffsetInHours();
      if ($offset >= 0) {
        $display = pht('%s (UTC+%d)', $display, $offset);
      } else {
        $display = pht('%s (UTC-%d)', $display, abs($offset));
      }
    }

    return $this->renderValue($display);
  }

  final protected function renderOldDate() {
    return $this->renderDate($this->getOldValue());
  }

  final protected function renderNewDate() {
    return $this->renderDate($this->getNewValue());
  }

  final protected function newError($title, $message, $xaction = null) {
    return new PhabricatorApplicationTransactionValidationError(
      $this->getTransactionTypeConstant(),
      $title,
      $message,
      $xaction);
  }

  final protected function newRequiredError($message, $xaction = null) {
    return $this->newError(pht('Required'), $message, $xaction)
      ->setIsMissingFieldError(true);
  }

  final protected function newInvalidError($message, $xaction = null) {
    return $this->newError(pht('Invalid'), $message, $xaction);
  }

  final protected function isNewObject() {
    return $this->getEditor()->getIsNewObject();
  }

  final protected function isEmptyTextTransaction($value, array $xactions) {
    foreach ($xactions as $xaction) {
      $value = $xaction->getNewValue();
    }

    return !strlen($value);
  }

  private function isTextMode() {
    $target = $this->getStorage()->getRenderingTarget();
    return ($target == PhabricatorApplicationTransaction::TARGET_TEXT);
  }

  final protected function newRemarkupChange() {
    return id(new PhabricatorTransactionRemarkupChange())
      ->setTransaction($this->getStorage());
  }

}
