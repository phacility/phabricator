<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

final class DifferentialAddCommentView extends AphrontView {

  private $revision;
  private $actions;
  private $actionURI;
  private $user;
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

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
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

  private function generateWarningView(
    $status,
    array $titles,
    $id,
    $content) {

    $warning = new AphrontErrorView();
    $warning->setSeverity(AphrontErrorView::SEVERITY_ERROR);
    $warning->setID($id);
    $warning->appendChild($content);
    $warning->setTitle(idx($titles, $status, 'Warning'));

    return $warning;
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
          ->setLabel('Action')
          ->setName('action')
          ->setValue($action)
          ->setID('comment-action')
          ->setOptions($this->actions))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setLabel('Add Reviewers')
          ->setName('reviewers')
          ->setControlID('add-reviewers')
          ->setControlStyle($enable_reviewers ? null : 'display: none')
          ->setID('add-reviewers-tokenizer')
          ->setDisableBehavior(true))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setLabel('Add CCs')
          ->setName('ccs')
          ->setControlID('add-ccs')
          ->setControlStyle($enable_ccs ? null : 'display: none')
          ->setID('add-ccs-tokenizer')
          ->setDisableBehavior(true))
      ->appendChild(
        id(new PhabricatorRemarkupControl())
          ->setName('comment')
          ->setID('comment-content')
          ->setLabel('Comment')
          ->setValue($this->draft ? $this->draft->getDraft() : null))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue($is_serious ? 'Submit' : 'Clowncopterize'));

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
            'placeholder' => 'Type a user name...',
          ),
          'add-ccs-tokenizer' => array(
            'actions' => array('add_ccs' => 1),
            'src' => '/typeahead/common/mailable/',
            'value' => $this->ccs,
            'row' => 'add-ccs',
            'ondemand' => PhabricatorEnv::getEnvConfig('tokenizer.ondemand'),
            'placeholder' => 'Type a user or mailing list...',
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

    $warning_container = '<div id="warnings">';
    foreach ($warnings as $warning) {
      if ($warning) {
        $warning_container .= $warning->render();
      }
    }
    $warning_container .= '</div>';

    $header = id(new PhabricatorHeaderView())
      ->setHeader($is_serious ? 'Add Comment' : 'Leap Into Action');

    return
      id(new PhabricatorAnchorView())
        ->setAnchorName('comment')
        ->setNavigationMarker(true)
        ->render().
      '<div class="differential-add-comment-panel">'.
        $header->render().
        $form->render().
        $warning_container.
        '<div class="aphront-panel-preview aphront-panel-flush">'.
          '<div id="comment-preview">'.
            '<span class="aphront-panel-preview-loading-text">'.
              'Loading comment preview...'.
            '</span>'.
          '</div>'.
          '<div id="inline-comment-preview">'.
          '</div>'.
        '</div>'.
      '</div>';
  }
}
