<?php

final class DifferentialAddCommentView extends AphrontView {

  private $revision;
  private $actions;
  private $actionURI;
  private $draft;
  private $auxFields;
  private $reviewers = array();
  private $ccs = array();

  public function setRevision($revision) {
    $this->revision = $revision;
    return $this;
  }

  public function setAuxFields(array $aux_fields) {
    assert_instances_of($aux_fields, 'DifferentialFieldSpecification');
    $this->auxFields = $aux_fields;
    return $this;
  }

  public function setActions(array $actions) {
    $this->actions = $actions;
    return $this;
  }

  public function setActionURI($uri) {
    $this->actionURI = $uri;
    return $this;
  }

  public function setDraft(PhabricatorDraft $draft = null) {
    $this->draft = $draft;
    return $this;
  }

  public function setReviewers(array $names) {
    $this->reviewers = $names;
    return $this;
  }

  public function setCCs(array $names) {
    $this->ccs = $names;
    return $this;
  }

  public function render() {

    require_celerity_resource('differential-revision-add-comment-css');

    $is_serious = PhabricatorEnv::getEnvConfig('phabricator.serious-business');

    $revision = $this->revision;

    $action = null;
    if ($this->draft) {
      $action = idx($this->draft->getMetadata(), 'action');
    }

    $enable_reviewers = DifferentialAction::allowReviewers($action);
    $enable_ccs = ($action == DifferentialAction::ACTION_ADDCCS);

    $form = new AphrontFormView();
    $form
      ->setWorkflow(true)
      ->setFlexible(true)
      ->setUser($this->user)
      ->setAction($this->actionURI)
      ->addHiddenInput('revision_id', $revision->getID())
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Action'))
          ->setName('action')
          ->setValue($action)
          ->setID('comment-action')
          ->setOptions($this->actions))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('Add Reviewers'))
          ->setName('reviewers')
          ->setControlID('add-reviewers')
          ->setControlStyle($enable_reviewers ? null : 'display: none')
          ->setID('add-reviewers-tokenizer')
          ->setDisableBehavior(true))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('Add CCs'))
          ->setName('ccs')
          ->setControlID('add-ccs')
          ->setControlStyle($enable_ccs ? null : 'display: none')
          ->setID('add-ccs-tokenizer')
          ->setDisableBehavior(true))
      ->appendChild(
        id(new PhabricatorRemarkupControl())
          ->setName('comment')
          ->setID('comment-content')
          ->setLabel(pht('Comment'))
          ->setValue($this->draft ? $this->draft->getDraft() : null)
          ->setUser($this->user))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue($is_serious ? pht('Submit') : pht('Clowncopterize')));

    Javelin::initBehavior(
      'differential-add-reviewers-and-ccs',
      array(
        'dynamic' => array(
          'add-reviewers-tokenizer' => array(
            'actions' => array('request_review' => 1, 'add_reviewers' => 1),
            'src' => '/typeahead/common/users/',
            'value' => $this->reviewers,
            'row' => 'add-reviewers',
            'ondemand' => PhabricatorEnv::getEnvConfig('tokenizer.ondemand'),
            'placeholder' => pht('Type a user name...'),
          ),
          'add-ccs-tokenizer' => array(
            'actions' => array('add_ccs' => 1),
            'src' => '/typeahead/common/mailable/',
            'value' => $this->ccs,
            'row' => 'add-ccs',
            'ondemand' => PhabricatorEnv::getEnvConfig('tokenizer.ondemand'),
            'placeholder' => pht('Type a user or mailing list...'),
          ),
        ),
        'select' => 'comment-action',
      ));

    $diff = $revision->loadActiveDiff();
    $warnings = mpull($this->auxFields, 'renderWarningBoxForRevisionAccept');

    Javelin::initBehavior(
      'differential-accept-with-errors',
      array(
        'select' => 'comment-action',
        'warnings' => 'warnings',
      ));

    $rev_id = $revision->getID();

    Javelin::initBehavior(
      'differential-feedback-preview',
      array(
        'uri'       => '/differential/comment/preview/'.$rev_id.'/',
        'preview'   => 'comment-preview',
        'action'    => 'comment-action',
        'content'   => 'comment-content',
        'previewTokenizers' => array(
          'reviewers' => 'add-reviewers-tokenizer',
          'ccs'       => 'add-ccs-tokenizer',
        ),

        'inlineuri' => '/differential/comment/inline/preview/'.$rev_id.'/',
        'inline'    => 'inline-comment-preview',
      ));

    $warning_container = array();
    foreach ($warnings as $warning) {
      if ($warning) {
        $warning_container[] = $warning->render();
      }
    }

    $header = id(new PhabricatorHeaderView())
      ->setHeader($is_serious ? pht('Add Comment') : pht('Leap Into Action'));

    return hsprintf(
      '%s'.
      '<div class="differential-add-comment-panel">'.
        '%s%s%s'.
        '<div class="aphront-panel-preview aphront-panel-flush">'.
          '<div id="comment-preview">'.
            '<span class="aphront-panel-preview-loading-text">%s</span>'.
          '</div>'.
          '<div id="inline-comment-preview">'.
          '</div>'.
        '</div>'.
      '</div>',
      id(new PhabricatorAnchorView())
        ->setAnchorName('comment')
        ->setNavigationMarker(true)
        ->render(),
      $header->render(),
      $form->render(),
      phutil_tag('div', array('id' => 'warnings'), $warning_container),
      pht('Loading comment preview...'));
  }
}
