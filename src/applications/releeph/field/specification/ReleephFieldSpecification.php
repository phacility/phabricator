<?php

abstract class ReleephFieldSpecification {

  abstract public function getName();

/* -(  Storage  )------------------------------------------------------------ */

  public function getStorageKey() {
    return null;
  }

  final public function isEditable() {
    return $this->getStorageKey() !== null;
  }

  /**
   * This will be called many times if you are using **Selecting**.  In
   * particular, for N selecting fields, selectReleephRequests() is called
   * N-squared times, each time for R ReleephRequests.
   */
  final public function getValue() {
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


/* -(  Edit View  )---------------------------------------------------------- */

  public function renderEditControl(AphrontRequest $request) {
    throw new ReleephFieldSpecificationIncompleteException($this);
  }

  public function setValueFromAphrontRequest(AphrontRequest $request) {
    $data = $request->getRequestData();
    $value = idx($data, $this->getRequiredStorageKey());
    $this->validate($value);
    $this->setValue($value);
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


/* -(  Selecting  )---------------------------------------------------------- */

  /**
   * Append select controls to the given form.
   *
   * You are given:
   *
   *  - the AphrontFormView to append to;
   *
   *  - the AphrontRequest, so you can make use of the value currently selected
   *    in the form;
   *
   *  - $all_releeph_requests: an array of all the ReleephRequests without any
   *    selection based filtering; and
   *
   *  - $all_releeph_requests_without_this_field: an array of ReleephRequests
   *    that have been selected by all the other select controls on this page.
   *
   * The example in ReleephLevelFieldSpecification shows how to use these.
   * $all_releeph_requests lets you find out all the values of a field in all
   * ReleephRequests, so you can render controls for every known value.
   *
   * $all_releeph_requests_without_this_field lets you count how many
   * ReleephRequests could be affected by this field's select control, after
   * all the other fields have made their selections.
   * ReleephLevelFieldSpecification uses this to render a preview count for
   * each select button, and disables the button completely (but still renders
   * it) if it couldn't possibly select anything.
   */
  protected function appendSelectControls(
    AphrontFormView $form,
    AphrontRequest $request,
    array $all_releeph_requests,
    array $all_releeph_requests_without_this_field) {

    return null;
  }

  /**
   * Filter the $releeph_requests using the data you set with your form
   * controls, and which is now available in the provided AphrontRequest.
   */
  protected function selectReleephRequests(AphrontRequest $request,
                                           array &$releeph_requests) {
    return null;
  }

  /**
   * If you have PHIDs that can be used in an AphrontFormTokenizerControl,
   * return true here, return the PHIDs in getSelectablePHIDs(), and return the
   * URL the Tokenizer should use for the form control in
   * getSelectTokenizerDatasource().
   *
   * This is a cheap alternative to implementing appendSelectControls() and
   * selectReleephRequests() in full.
   */
  protected function hasSelectablePHIDs() {
    return false;
  }

  protected function getSelectablePHIDs() {
    throw new ReleephFieldSpecificationIncompleteException($this);
  }

  protected function getSelectTokenizerDatasource() {
    throw new ReleephFieldSpecificationIncompleteException($this);
  }


/* -(  Commit Messages  )---------------------------------------------------- */

  public function shouldAppearOnCommitMessage() {
    return false;
  }

  public function renderLabelForCommitMessage() {
    throw new ReleephFieldSpecificationIncompleteException($this);
  }

  public function renderValueForCommitMessage() {
    throw new ReleephFieldSpecificationIncompleteException($this);
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


/* -(  Implementation  )----------------------------------------------------- */

  protected function getRequiredStorageKey() {
    $key = $this->getStorageKey();
    if ($key === null) {
      throw new ReleephFieldSpecificationIncompleteException($this);
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

  /**
   * The "hook" functions ##appendSelectControlsHook()## and
   * ##selectReleephRequestsHook()## are used with ##hasSelectablePHIDs()##, to
   * use the tokenizing helpers if ##hasSelectablePHIDs()## returns true.
   */
  public function appendSelectControlsHook(
    AphrontFormView $form,
    AphrontRequest $request,
    array $all_releeph_requests,
    array $all_releeph_requests_without_this_field) {

    if ($this->hasSelectablePHIDs()) {
      $this->appendTokenizingSelectControl(
        $form,
        $request,
        $all_releeph_requests,
        $all_releeph_requests_without_this_field);
    } else {
      $this->appendSelectControls(
        $form,
        $request,
        $all_releeph_requests,
        $all_releeph_requests_without_this_field);
    }
  }

  // See above
  public function selectReleephRequestsHook(AphrontRequest $request,
                                            array &$releeph_requests) {

    if ($this->hasSelectablePHIDs()) {
      $this->selectReleephRequestsFromTokens(
        $request,
        $releeph_requests);
    } else {
      $this->selectReleephRequests(
        $request,
        $releeph_requests);
    }
  }

  private function appendTokenizingSelectControl(
    AphrontFormView $form,
    AphrontRequest $request,
    array $all_releeph_requests,
    array $all_releeph_requests_without_this_field) {

    $key = urlencode(strtolower($this->getName()));
    $selected_phids = $request->getArr($key);
    $handles = id(new PhabricatorObjectHandleData($selected_phids))
      ->setViewer($request->getUser())
      ->loadHandles();

    $tokens = array();
    foreach ($selected_phids as $phid) {
      $tokens[$phid] = $handles[$phid]->getFullName();
    }

    $datasource = $this->getSelectTokenizerDatasource();
    $control =
      id(new AphrontFormTokenizerControl())
        ->setDatasource($datasource)
        ->setName($key)
        ->setLabel($this->getName())
        ->setValue($tokens);

    $form->appendChild($control);
  }

  private function selectReleephRequestsFromTokens(AphrontRequest $request,
                                                   array &$releeph_requests) {

    $key = urlencode(strtolower($this->getName()));
    $selected_phids = $request->getArr($key);
    if (!$selected_phids) {
      return;
    }

    $selected_phid_lookup = array();
    foreach ($selected_phids as $phid) {
      $selected_phid_lookup[$phid] = $phid;
    }

    $filtered = array();
    foreach ($releeph_requests as $releeph_request) {
      $rq_phids = $this
        ->setReleephRequest($releeph_request)
        ->getSelectablePHIDs();
      foreach ($rq_phids as $rq_phid) {
        if (idx($selected_phid_lookup, $rq_phid)) {
          $filtered[] = $releeph_request;
          break;
        }
      }
    }

    $releeph_requests = $filtered;
  }

}
