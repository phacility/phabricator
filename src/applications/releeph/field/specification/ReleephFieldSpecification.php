<?php

abstract class ReleephFieldSpecification
  extends PhabricatorCustomField
  implements PhabricatorMarkupInterface {

  // TODO: This is temporary, until ReleephFieldSpecification is more conformant
  // to PhabricatorCustomField.
  private $requestValue;

  public function readValueFromRequest(AphrontRequest $request) {
    $this->requestValue = $request->getStr($this->getRequiredStorageKey());
    return $this;
  }

  abstract public function getName();

/* -(  Storage  )------------------------------------------------------------ */

  public function getStorageKey() {
    return null;
  }

  public function getRequiredStorageKey() {
    $key = $this->getStorageKey();
    if ($key === null) {
      throw new PhabricatorCustomFieldImplementationIncompleteException($this);
    }
    if (strpos($key, '.') !== false) {
      /**
       * Storage keys are reused for form controls, and periods in form control
       * names break HTML forms.
       */
      throw new Exception(
        "You can't use '.' in storage keys!");
    }
    return $key;
  }

  public function shouldAppearInEditView() {
    return $this->isEditable();
  }

  final public function isEditable() {
    return $this->getStorageKey() !== null;
  }

  final public function getValue() {
    if ($this->requestValue !== null) {
      return $this->requestValue;
    }

    $key = $this->getRequiredStorageKey();
    return $this->getReleephRequest()->getDetail($key);
  }

  final public function setValue($value) {
    $key = $this->getRequiredStorageKey();
    return $this->getReleephRequest()->setDetail($key, $value);
  }

  /**
   * @throws ReleephFieldParseException, to show an error.
   */
  public function validate($value) {
    return;
  }

  /**
   * Turn values as they are stored in a ReleephRequest into a text that can be
   * rendered as a transactions old/new values.
   */
  public function normalizeForTransactionView(
    PhabricatorApplicationTransaction $xaction,
    $value) {

    return $value;
  }


/* -(  Header View  )-------------------------------------------------------- */

  /**
   * Return a label for use in rendering the fields table.  If you return null,
   * the renderLabelForHeaderView data will span both columns.
   */
  public function renderLabelForHeaderView() {
    return $this->getName();
  }

  public function renderValueForHeaderView() {
    $key = $this->getRequiredStorageKey();
    return $this->getReleephRequest()->getDetail($key);
  }


/* -(  Conduit  )------------------------------------------------------------ */

  public function getKeyForConduit() {
    return $this->getRequiredStorageKey();
  }

  public function getValueForConduit() {
    return $this->getValue();
  }

  public function setValueFromConduitAPIRequest(ConduitAPIRequest $request) {
    $value = idx(
      $request->getValue('fields', array()),
      $this->getRequiredStorageKey());
    $this->validate($value);
    $this->setValue($value);
  }


/* -(  Arcanist  )----------------------------------------------------------- */

  public function renderHelpForArcanist() {
    return '';
  }


/* -(  Context  )------------------------------------------------------------ */

  private $releephProject;
  private $releephBranch;
  private $releephRequest;
  private $user;

  final public function setReleephProject(ReleephProject $rp) {
    $this->releephProject = $rp;
    return $this;
  }

  final public function setReleephBranch(ReleephBranch $rb) {
    $this->releephRequest = $rb;
    return $this;
  }

  final public function setReleephRequest(ReleephRequest $rr) {
    $this->releephRequest = $rr;
    return $this;
  }

  final public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  final public function getReleephProject() {
    return $this->releephProject;
  }

  final public function getReleephBranch() {
    return $this->releephBranch;
  }

  final public function getReleephRequest() {
    return $this->releephRequest;
  }

  final public function getUser() {
    return $this->user;
  }


/* -(  Bulk loading  )------------------------------------------------------- */

  public function bulkLoad(array $releeph_requests) {
  }

/* -(  Commit Messages  )---------------------------------------------------- */

  public function shouldAppearOnCommitMessage() {
    return false;
  }

  public function renderLabelForCommitMessage() {
    throw new PhabricatorCustomFieldImplementationIncompleteException($this);
  }

  public function renderValueForCommitMessage() {
    throw new PhabricatorCustomFieldImplementationIncompleteException($this);
  }

  public function shouldAppearOnRevertMessage() {
    return false;
  }

  public function renderLabelForRevertMessage() {
    return $this->renderLabelForCommitMessage();
  }

  public function renderValueForRevertMessage() {
    return $this->renderValueForCommitMessage();
  }


/* -(  Markup Interface  )--------------------------------------------------- */

  const MARKUP_FIELD_GENERIC = 'releeph:generic-markup-field';

  private $engine;

  /**
   * ReleephFieldSpecification implements much of PhabricatorMarkupInterface
   * for you.  If you return true from `shouldMarkup()`, and implement
   * `getMarkupText()` then your text will be rendered through the Phabricator
   * markup pipeline.
   *
   * Output is retrievable with `getMarkupEngineOutput()`.
   */
  public function shouldMarkup() {
    return false;
  }

  public function getMarkupText($field) {
    throw new PhabricatorCustomFieldImplementationIncompleteException($this);
  }

  final public function getMarkupEngineOutput() {
    return $this->engine->getOutput($this, self::MARKUP_FIELD_GENERIC);
  }

  final public function setMarkupEngine(PhabricatorMarkupEngine $engine) {
    $this->engine = $engine;
    $engine->addObject($this, self::MARKUP_FIELD_GENERIC);
    return $this;
  }

  final public function getMarkupFieldKey($field) {
    return sprintf(
      '%s:%s:%s:%s',
      $this->getReleephRequest()->getPHID(),
      $this->getStorageKey(),
      $field,
      PhabricatorHash::digest($this->getMarkupText($field)));
  }

  final public function newMarkupEngine($field) {
    return PhabricatorMarkupEngine::newDifferentialMarkupEngine();
  }

  final public function didMarkupText(
    $field,
    $output,
    PhutilMarkupEngine $engine) {

    return $output;
  }

  final public function shouldUseMarkupCache($field) {
    return true;
  }

}
