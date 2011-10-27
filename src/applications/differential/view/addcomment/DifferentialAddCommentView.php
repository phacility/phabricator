<?php

/*
 * Copyright 2011 Facebook, Inc.
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
  }

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
  }

  public function setDraft($draft) {
    $this->draft = $draft;
    return $this;
  }

  private function generateWarningView(
    $status,
    array $titles,
    $id,
    $content) {

    $warning = new AphrontErrorView();
    $warning->setSeverity(AphrontErrorView::SEVERITY_ERROR);
    $warning->setWidth(AphrontErrorView::WIDTH_WIDE);
    $warning->setID($id);
    $warning->appendChild($content);
    $warning->setTitle(idx($titles, $status, 'Warning'));

    return $warning;
  }

  public function render() {

    require_celerity_resource('differential-revision-add-comment-css');

    $revision = $this->revision;

    $form = new AphrontFormView();
    $form
      ->setUser($this->user)
      ->setAction($this->actionURI)
      ->addHiddenInput('revision_id', $revision->getID())
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setLabel('Action')
          ->setName('action')
          ->setID('comment-action')
          ->setOptions($this->actions))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setLabel('Add Reviewers')
          ->setName('reviewers')
          ->setControlID('add-reviewers')
          ->setControlStyle('display: none')
          ->setID('add-reviewers-tokenizer')
          ->setDisableBehavior(true))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setLabel('Add CCs')
          ->setName('ccs')
          ->setControlID('add-ccs')
          ->setControlStyle('display: none')
          ->setID('add-ccs-tokenizer')
          ->setDisableBehavior(true))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setName('comment')
          ->setID('comment-content')
          ->setLabel('Comment')
          ->setEnableDragAndDropFileUploads(true)
          ->setValue($this->draft))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Clowncopterize'));

    Javelin::initBehavior(
      'differential-add-reviewers-and-ccs',
      array(
        'dynamic' => array(
          'add_reviewers' => array(
            'tokenizer' => 'add-reviewers-tokenizer',
            'src' => '/typeahead/common/users/',
            'row' => 'add-reviewers',
            'ondemand' => PhabricatorEnv::getEnvConfig('tokenizer.ondemand'),
          ),
          'add_ccs' => array(
            'tokenizer' => 'add-ccs-tokenizer',
            'src' => '/typeahead/common/mailable/',
            'row' => 'add-ccs',
            'ondemand' => PhabricatorEnv::getEnvConfig('tokenizer.ondemand'),
          ),
        ),
        'select' => 'comment-action',
      ));

    $diff = $revision->loadActiveDiff();
    $lint_warning = null;
    $unit_warning = null;
    if ($diff->getLintStatus() >= DifferentialLintStatus::LINT_WARN) {
      $titles =
        array(
          DifferentialLintStatus::LINT_WARN => 'Lint Warning',
          DifferentialLintStatus::LINT_FAIL => 'Lint Failure',
          DifferentialLintStatus::LINT_SKIP => 'Lint Skipped'
        );
      $content =
        "<p>This diff has Lint Problems. Make sure you are OK with them ".
        "before you accept this diff.</p>";
      $lint_warning = $this->generateWarningView(
        $diff->getLintStatus(),
        $titles,
        'lint-warning',
        $content);
    }

    if ($diff->getUnitStatus() >= DifferentialUnitStatus::UNIT_WARN) {
      $titles =
        array(
          DifferentialUnitStatus::UNIT_WARN => 'Unit Tests Warning',
          DifferentialUnitStatus::UNIT_FAIL => 'Unit Tests Failure',
          DifferentialUnitStatus::UNIT_SKIP => 'Unit Tests Skipped',
          DifferentialUnitStatus::UNIT_POSTPONED => 'Unit Tests Postponed'
        );
      if ($diff->getUnitStatus() == DifferentialUnitStatus::UNIT_POSTPONED) {
        $content =
          "<p>This diff has postponed unit tests. The results should be ".
          "coming in soon. You should probably wait for them before accepting ".
          "this diff.</p>";
      } else {
        $content =
          "<p>This diff has Unit Test Problems. Make sure you are OK with ".
          "them before you accept this diff.</p>";
      }
      $unit_warning = $this->generateWarningView(
        $diff->getUnitStatus(),
        $titles,
        'unit-warning',
        $content);
    }

    Javelin::initBehavior(
      'differential-accept-with-errors',
      array(
        'select' => 'comment-action',
        'lint_warning' => $lint_warning ? 'lint-warning' : null,
        'unit_warning' => $unit_warning ? 'unit-warning' : null,
      ));

    $rev_id = $revision->getID();

    Javelin::initBehavior(
      'differential-feedback-preview',
      array(
        'uri'       => '/differential/comment/preview/'.$rev_id.'/',
        'preview'   => 'comment-preview',
        'action'    => 'comment-action',
        'content'   => 'comment-content',

        'inlineuri' => '/differential/comment/inline/preview/'.$rev_id.'/',
        'inline'    => 'inline-comment-preview',
      ));

    $panel_view = new AphrontPanelView();
    $panel_view->appendChild($form);
    if ($lint_warning) {
      $panel_view->appendChild($lint_warning);
    }
    if ($unit_warning) {
      $panel_view->appendChild($unit_warning);
    }
    $panel_view->setHeader('Leap Into Action');
    $panel_view->addClass('aphront-panel-accent');
    $panel_view->addClass('aphront-panel-flush');

    return
      '<div class="differential-add-comment-panel">'.
        $panel_view->render().
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
