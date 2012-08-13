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

final class PonderAddAnswerView extends AphrontView {

  private $question;
  private $user;
  private $actionURI;
  private $draft;

  public function setQuestion($question) {
    $this->question = $question;
    return $this;
  }

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function setActionURI($uri) {
    $this->actionURI = $uri;
    return $this;
  }

  public function render() {
    require_celerity_resource('ponder-core-view-css');

    $is_serious = PhabricatorEnv::getEnvConfig('phabricator.serious-business');

    $question = $this->question;

    $panel = id(new AphrontPanelView())
      ->addClass("ponder-panel")
      ->setHeader("Your Answer:");

    $form = new AphrontFormView();
    $form
      ->setUser($this->user)
      ->setAction($this->actionURI)
      ->addHiddenInput('question_id', $question->getID())
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setName('answer')
          ->setID('answer-content')
          ->setEnableDragAndDropFileUploads(true)
          ->setCaption(phutil_render_tag(
            'a',
            array(
              'href' => PhabricatorEnv::getDoclink(
                'article/Remarkup_Reference.html'),
              'tabindex' => '-1',
              'target' => '_blank',
            ),
            'Formatting Reference')))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue($is_serious ? 'Submit' : 'Make it so.'));

    $panel->appendChild($form);
    $panel->appendChild(
      '<div class="aphront-panel-flush">'.
        '<div id="answer-preview">'.
          '<span class="aphront-panel-preview-loading-text">'.
            'Loading answer preview...'.
          '</span>'.
        '</div>'.
      '</div>'
    );

    Javelin::initBehavior(
      'ponder-feedback-preview',
      array(
        'uri'         => '/ponder/answer/preview/',
        'content'     => 'answer-content',
        'preview'     => 'answer-preview',
        'question_id' => $question->getID()
      ));

    return $panel->render();
  }
}
