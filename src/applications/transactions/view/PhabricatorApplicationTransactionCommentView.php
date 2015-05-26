<?php

/**
 * @concrete-extensible
 */
class PhabricatorApplicationTransactionCommentView extends AphrontView {

  private $submitButtonName;
  private $action;

  private $previewPanelID;
  private $previewTimelineID;
  private $previewToggleID;
  private $formID;
  private $statusID;
  private $commentID;
  private $draft;
  private $requestURI;
  private $showPreview = true;
  private $objectPHID;
  private $headerText;

  public function setObjectPHID($object_phid) {
    $this->objectPHID = $object_phid;
    return $this;
  }

  public function getObjectPHID() {
    return $this->objectPHID;
  }

  public function setShowPreview($show_preview) {
    $this->showPreview = $show_preview;
    return $this;
  }

  public function getShowPreview() {
    return $this->showPreview;
  }

  public function setRequestURI(PhutilURI $request_uri) {
    $this->requestURI = $request_uri;
    return $this;
  }
  public function getRequestURI() {
    return $this->requestURI;
  }

  public function setDraft(PhabricatorDraft $draft) {
    $this->draft = $draft;
    return $this;
  }

  public function getDraft() {
    return $this->draft;
  }

  public function setSubmitButtonName($submit_button_name) {
    $this->submitButtonName = $submit_button_name;
    return $this;
  }

  public function getSubmitButtonName() {
    return $this->submitButtonName;
  }

  public function setAction($action) {
    $this->action = $action;
    return $this;
  }

  public function getAction() {
    return $this->action;
  }

  public function setHeaderText($text) {
    $this->headerText = $text;
    return $this;
  }

  public function render() {

    $user = $this->getUser();
    if (!$user->isLoggedIn()) {
      $uri = id(new PhutilURI('/login/'))
        ->setQueryParam('next', (string)$this->getRequestURI());
      return id(new PHUIObjectBoxView())
        ->setFlush(true)
        ->setHeaderText(pht('Add Comment'))
        ->appendChild(
          javelin_tag(
            'a',
            array(
              'class' => 'login-to-comment button',
              'href' => $uri,
            ),
            pht('Login to Comment')));
    }

    $data = array();

    $comment = $this->renderCommentPanel();

    if ($this->getShowPreview()) {
      $preview = $this->renderPreviewPanel();
    } else {
      $preview = null;
    }

    Javelin::initBehavior(
      'phabricator-transaction-comment-form',
      array(
        'formID'        => $this->getFormID(),
        'timelineID'    => $this->getPreviewTimelineID(),
        'panelID'       => $this->getPreviewPanelID(),
        'statusID'      => $this->getStatusID(),
        'commentID'     => $this->getCommentID(),

        'loadingString' => pht('Loading Preview...'),
        'savingString'  => pht('Saving Draft...'),
        'draftString'   => pht('Saved Draft'),

        'showPreview'   => $this->getShowPreview(),

        'actionURI'     => $this->getAction(),
      ));

    $comment_box = id(new PHUIObjectBoxView())
      ->setFlush(true)
      ->setHeaderText($this->headerText)
      ->appendChild($comment);

    return array($comment_box, $preview);
  }

  private function renderCommentPanel() {
    $status = phutil_tag(
      'div',
      array(
        'id' => $this->getStatusID(),
      ),
      '');

    $draft_comment = '';
    $draft_key = null;
    if ($this->getDraft()) {
      $draft_comment = $this->getDraft()->getDraft();
      $draft_key = $this->getDraft()->getDraftKey();
    }

    if (!$this->getObjectPHID()) {
      throw new PhutilInvalidStateException('setObjectPHID', 'render');
    }

    return id(new AphrontFormView())
      ->setUser($this->getUser())
      ->addSigil('transaction-append')
      ->setWorkflow(true)
      ->setMetadata(
        array(
          'objectPHID' => $this->getObjectPHID(),
        ))
      ->setAction($this->getAction())
      ->setID($this->getFormID())
      ->addHiddenInput('__draft__', $draft_key)
      ->appendChild(
        id(new PhabricatorRemarkupControl())
          ->setID($this->getCommentID())
          ->setName('comment')
          ->setLabel(pht('Comment'))
          ->setUser($this->getUser())
          ->setValue($draft_comment))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue($this->getSubmitButtonName()))
      ->appendChild(
        id(new AphrontFormMarkupControl())
          ->setValue($status));
  }

  private function renderPreviewPanel() {

    $preview = id(new PHUITimelineView())
      ->setID($this->getPreviewTimelineID());

    return phutil_tag(
      'div',
      array(
        'id'    => $this->getPreviewPanelID(),
        'style' => 'display: none',
      ),
      $preview);
  }

  private function getPreviewPanelID() {
    if (!$this->previewPanelID) {
      $this->previewPanelID = celerity_generate_unique_node_id();
    }
    return $this->previewPanelID;
  }

  private function getPreviewTimelineID() {
    if (!$this->previewTimelineID) {
      $this->previewTimelineID = celerity_generate_unique_node_id();
    }
    return $this->previewTimelineID;
  }

  public function setFormID($id) {
    $this->formID = $id;
    return $this;
  }

  private function getFormID() {
    if (!$this->formID) {
      $this->formID = celerity_generate_unique_node_id();
    }
    return $this->formID;
  }

  private function getStatusID() {
    if (!$this->statusID) {
      $this->statusID = celerity_generate_unique_node_id();
    }
    return $this->statusID;
  }

  private function getCommentID() {
    if (!$this->commentID) {
      $this->commentID = celerity_generate_unique_node_id();
    }
    return $this->commentID;
  }

}
