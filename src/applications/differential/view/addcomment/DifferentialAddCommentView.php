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

  public function render() {

    require_celerity_resource('differential-revision-add-comment-css');

    $revision = $this->revision;

    $actions = array();
    foreach ($this->actions as $action) {
      $actions[$action] = DifferentialAction::getActionVerb($action);
    }

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
          ->setOptions($actions))
      ->appendChild(
        id(new AphrontFormTokenizerControl())
          ->setLabel('Add Reviewers')
          ->setName('reviewers')
          ->setControlID('add-reviewers')
          ->setControlStyle('display: none')
          ->setID('add-reviewers-tokenizer')
          ->setDisableBehavior(true))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setName('comment')
          ->setID('comment-content')
          ->setLabel('Comment'))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Comment'));

    Javelin::initBehavior(
      'differential-add-reviewers',
      array(
        'src' => '/typeahead/common/users/',
        'tokenizer' => 'add-reviewers-tokenizer',
        'select' => 'comment-action',
        'row' => 'add-reviewers',
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

    return
      '<div class="differential-add-comment-panel">'.
        '<div class="differential-panel">'.
          '<h1>Add Comment</h1>'.
          $form->render().
        '</div>'.
        '<div class="differential-comment-preview">'.
          '<div id="comment-preview">'.
            '<span class="differential-loading-text">'.
              'Loading comment preview...'.
            '</span>'.
          '</div>'.
          '<div id="inline-comment-preview">'.
          '</div>'.
        '</div>'.
      '</div>';
  }
}
