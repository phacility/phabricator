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

  public function applyInternalEffects($object, $value) {
    return;
  }

  public function applyExternalEffects($object, $value) {
    return;
  }

  public function didCommitTransaction($object, $value) {
    return;
  }

  public function getTransactionHasEffect($object, $old, $new) {
    return ($old !== $new);
  }

  public function extractFilePHIDs($object, $value) {
    return array();
  }

  public function shouldHide() {
    return false;
  }

  public function shouldHideForFeed() {
    return false;
  }

  public function shouldHideForMail() {
    return false;
  }

  public function shouldHideForNotifications() {
    return null;
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

  public function getActionName() {
    return null;
  }

  public function getActionStrength() {
    return null;
  }

  public function getColor() {
    return null;
  }

  public function hasChangeDetailView() {
    return false;
  }

  public function newChangeDetailView() {
    return null;
  }

  public function getMailDiffSectionHeader() {
    return pht('EDIT DETAILS');
  }

  public function newRemarkupChanges() {
    return array();
  }

  public function mergeTransactions(
    $object,
    PhabricatorApplicationTransaction $u,
    PhabricatorApplicationTransaction $v) {
    return null;
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

  final protected function hasEditor() {
    return (bool)$this->editor;
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

  final protected function renderOldPolicy() {
    return $this->renderPolicy($this->getOldValue(), 'old');
  }

  final protected function renderNewPolicy() {
    return $this->renderPolicy($this->getNewValue(), 'new');
  }

  final protected function renderPolicy($phid, $mode) {
    $viewer = $this->getViewer();
    $handles = $viewer->loadHandles(array($phid));

    $policy = PhabricatorPolicy::newFromPolicyAndHandle(
      $phid,
      $handles[$phid]);

    $ref = $policy->newRef($viewer);

    if ($this->isTextMode()) {
      $name = $ref->getPolicyDisplayName();
    } else {
      $storage = $this->getStorage();
      $name = $ref->newTransactionLink($mode, $storage);
    }

    return $this->renderValue($name);
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

  final protected function renderValueList(array $values) {
    $result = array();
    foreach ($values as $value) {
      $result[] = $this->renderValue($value);
    }

    if ($this->isTextMode()) {
      return implode(', ', $result);
    }

    return phutil_implode_html(', ', $result);
  }

  final protected function renderOldValue() {
    return $this->renderValue($this->getOldValue());
  }

  final protected function renderNewValue() {
    return $this->renderValue($this->getNewValue());
  }

  final protected function renderDate($epoch) {
    $viewer = $this->getViewer();

    // We accept either epoch timestamps or dictionaries describing a
    // PhutilCalendarDateTime.
    if (is_array($epoch)) {
      $datetime = PhutilCalendarAbsoluteDateTime::newFromDictionary($epoch)
        ->setViewerTimezone($viewer->getTimezoneIdentifier());

      $all_day = $datetime->getIsAllDay();

      $epoch = $datetime->getEpoch();
    } else {
      $all_day = false;
    }

    if ($all_day) {
      $display = phabricator_date($epoch, $viewer);
    } else if ($this->isRenderingTargetExternal()) {
      // When rendering to text, we explicitly render the offset from UTC to
      // provide context to the date: the mail may be generating with the
      // server's settings, or the user may later refer back to it after
      // changing timezones.

      $display = phabricator_datetimezone($epoch, $viewer);
    } else {
      $display = phabricator_datetime($epoch, $viewer);
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

    return $value === null || !strlen($value);
  }

  /**
   * When rendering to external targets (Email/Asana/etc), we need to include
   * more information that users can't obtain later.
   */
  final protected function isRenderingTargetExternal() {
    // Right now, this is our best proxy for this:
    return $this->isTextMode();
    // "TARGET_TEXT" means "EMail" and "TARGET_HTML" means "Web".
  }

  final protected function isTextMode() {
    $target = $this->getStorage()->getRenderingTarget();
    return ($target == PhabricatorApplicationTransaction::TARGET_TEXT);
  }

  final protected function newRemarkupChange() {
    return id(new PhabricatorTransactionRemarkupChange())
      ->setTransaction($this->getStorage());
  }

  final protected function isCreateTransaction() {
    return $this->getStorage()->getIsCreateTransaction();
  }

  final protected function getPHIDList(array $old, array $new) {
    $editor = $this->getEditor();

    return $editor->getPHIDList($old, $new);
  }

  public function getMetadataValue($key, $default = null) {
    return $this->getStorage()->getMetadataValue($key, $default);
  }

  public function loadTransactionTypeConduitData(array $xactions) {
    return null;
  }

  public function getTransactionTypeForConduit($xaction) {
    return null;
  }

  public function getFieldValuesForConduit($xaction, $data) {
    return array();
  }

  protected function requireApplicationCapability($capability) {
    $application_class = $this->getEditor()->getEditorApplicationClass();
    $application = newv($application_class, array());

    PhabricatorPolicyFilter::requireCapability(
      $this->getActor(),
      $application,
      $capability);
  }

  /**
   * Get a list of capabilities the actor must have on the object to apply
   * a transaction to it.
   *
   * Usually, you should use this to reduce capability requirements when a
   * transaction (like leaving a Conpherence thread) can be applied without
   * having edit permission on the object. You can override this method to
   * remove the CAN_EDIT requirement, or to replace it with a different
   * requirement.
   *
   * If you are increasing capability requirements and need to add an
   * additional capability or policy requirement above and beyond CAN_EDIT, it
   * is usually better implemented as a validation check.
   *
   * @param object Object being edited.
   * @param PhabricatorApplicationTransaction Transaction being applied.
   * @return null|const|list<const> A capability constant (or list of
   *    capability constants) which the actor must have on the object. You can
   *    return `null` as a shorthand for "no capabilities are required".
   */
  public function getRequiredCapabilities(
    $object,
    PhabricatorApplicationTransaction $xaction) {
    return PhabricatorPolicyCapability::CAN_EDIT;
  }

  public function shouldTryMFA(
    $object,
    PhabricatorApplicationTransaction $xaction) {
    return false;
  }

  // NOTE: See T12921. These APIs are somewhat aspirational. For now, all of
  // these use "TARGET_TEXT" (even the HTML methods!) and the body methods
  // actually return Remarkup, not text or HTML.

  final public function getTitleForTextMail() {
    return $this->getTitleForMailWithRenderingTarget(
      PhabricatorApplicationTransaction::TARGET_TEXT);
  }

  final public function getTitleForHTMLMail() {
    return $this->getTitleForMailWithRenderingTarget(
      PhabricatorApplicationTransaction::TARGET_TEXT);
  }

  final public function getBodyForTextMail() {
    return $this->getBodyForMailWithRenderingTarget(
      PhabricatorApplicationTransaction::TARGET_TEXT);
  }

  final public function getBodyForHTMLMail() {
    return $this->getBodyForMailWithRenderingTarget(
      PhabricatorApplicationTransaction::TARGET_TEXT);
  }

  private function getTitleForMailWithRenderingTarget($target) {
    $storage = $this->getStorage();

    $old_target = $storage->getRenderingTarget();
    try {
      $storage->setRenderingTarget($target);
      $result = $this->getTitleForMail();
    } catch (Exception $ex) {
      $storage->setRenderingTarget($old_target);
      throw $ex;
    }
    $storage->setRenderingTarget($old_target);

    return $result;
  }

  private function getBodyForMailWithRenderingTarget($target) {
    $storage = $this->getStorage();

    $old_target = $storage->getRenderingTarget();
    try {
      $storage->setRenderingTarget($target);
      $result = $this->getBodyForMail();
    } catch (Exception $ex) {
      $storage->setRenderingTarget($old_target);
      throw $ex;
    }
    $storage->setRenderingTarget($old_target);

    return $result;
  }

  protected function getTitleForMail() {
    return false;
  }

  protected function getBodyForMail() {
    return false;
  }

}
