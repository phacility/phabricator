<?php

final class DifferentialAddCommentView extends AphrontView {

  private $revision;
  private $actions;
  private $actionURI;
  private $draft;
  private $reviewers = array();
  private $ccs = array();
  private $errorView;

  public function setInfoView(PHUIInfoView $error_view) {
    $this->errorView = $error_view;
    return $this;
  }

  public function getErrorView() {
    return $this->errorView;
  }

  public function setRevision($revision) {
    $this->revision = $revision;
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
    $viewer = $this->getViewer();

    $this->requireResource('differential-revision-add-comment-css');
    $revision = $this->revision;

    $action = null;
    if ($this->draft) {
      $action = idx($this->draft->getMetadata(), 'action');
    }

    $enable_reviewers = DifferentialAction::allowReviewers($action);
    $enable_ccs = ($action == DifferentialAction::ACTION_ADDCCS);
    $add_reviewers_labels = array(
      'add_reviewers' => pht('Add Reviewers'),
      'request_review' => pht('Add Reviewers'),
      'resign' => pht('Suggest Reviewers'),
    );

    $mailable_source = new PhabricatorMetaMTAMailableDatasource();

    // TODO: This should be a reviewers datasource, but it's a mess.
    $reviewer_source = new PhabricatorMetaMTAMailableDatasource();

    $form = new AphrontFormView();
    $form
      ->setWorkflow(true)
      ->setViewer($viewer)
      ->setAction($this->actionURI)
      ->addHiddenInput('revision_id', $revision->getID())
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel(pht('Action'))
          ->setName('action')
          ->setValue($action)
          ->setID('comment-action')
          ->setOptions($this->actions))
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setLabel($enable_reviewers ? $add_reviewers_labels[$action] :
            $add_reviewers_labels['add_reviewers'])
          ->setName('reviewers')
          ->setControlID('add-reviewers')
          ->setControlStyle($enable_reviewers ? null : 'display: none')
          ->setID('add-reviewers-tokenizer')
          ->setDisableBehavior(true)
          ->setDatasource($reviewer_source))
      ->appendControl(
        id(new AphrontFormTokenizerControl())
          ->setLabel(pht('Add Subscribers'))
          ->setName('ccs')
          ->setControlID('add-ccs')
          ->setControlStyle($enable_ccs ? null : 'display: none')
          ->setID('add-ccs-tokenizer')
          ->setDisableBehavior(true)
          ->setDatasource($mailable_source))
      ->appendChild(
        id(new PhabricatorRemarkupControl())
          ->setName('comment')
          ->setID('comment-content')
          ->setLabel(pht('Comment'))
          ->setValue($this->draft ? $this->draft->getDraft() : null)
          ->setViewer($viewer))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Submit')));

    Javelin::initBehavior(
      'differential-add-reviewers-and-ccs',
      array(
        'dynamic' => array(
          'add-reviewers-tokenizer' => array(
            'actions' => array(
              'request_review' => 1,
              'add_reviewers' => 1,
              'resign' => 1,
            ),
            'src' => $reviewer_source->getDatasourceURI(),
            'value' => $this->reviewers,
            'row' => 'add-reviewers',
            'labels' => $add_reviewers_labels,
            'placeholder' => $reviewer_source->getPlaceholderText(),
          ),
          'add-ccs-tokenizer' => array(
            'actions' => array('add_ccs' => 1),
            'src' => $mailable_source->getDatasourceURI(),
            'value' => $this->ccs,
            'row' => 'add-ccs',
            'placeholder' => $mailable_source->getPlaceholderText(),
          ),
        ),
        'select' => 'comment-action',
      ));

    $diff = $revision->loadActiveDiff();
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

    $is_serious = PhabricatorEnv::getEnvConfig('phabricator.serious-business');
    $header_text = $is_serious
      ? pht('Add Comment')
      : pht('Leap Into Action!');

    $header = id(new PHUIHeaderView())
      ->setHeader($header_text);

    $anchor = id(new PhabricatorAnchorView())
        ->setAnchorName('comment')
        ->setNavigationMarker(true);

    $loading = phutil_tag(
      'span',
      array('class' => 'aphront-panel-preview-loading-text'),
      pht('Loading comment preview...'));

    $preview = phutil_tag_div(
      'aphront-panel-preview aphront-panel-flush',
      array(
        phutil_tag('div', array('id' => 'comment-preview'), $loading),
        phutil_tag('div', array('id' => 'inline-comment-preview')),
      ));


    $comment_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->appendChild($anchor)
      ->appendChild($form);

    if ($this->errorView) {
      $comment_box->setInfoView($this->errorView);
    }

    return array($comment_box, $preview);
  }
}
