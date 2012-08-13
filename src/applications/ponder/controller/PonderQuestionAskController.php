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

final class PonderQuestionAskController extends PonderController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    if ($request->isFormPost()) {
      return $this->handlePost();
    }

    return $this->showForm();
  }

  private function handlePost() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $errors = array();
    $title = $request->getStr('title');
    $content = $request->getStr('content');

    // form validation
    if (phutil_utf8_strlen($title) < 1 || phutil_utf8_strlen($title) > 255) {
      $errors[] = "Please enter a title (1-255 characters)";
    }

    if ($errors) {
      return $this->showForm($errors, $title, $content);
    }

    // no validation errors -> save it

    $content_source = PhabricatorContentSource::newForSource(
      PhabricatorContentSource::SOURCE_WEB,
      array(
        'ip' => $request->getRemoteAddr(),
      ));

    $question = id(new PonderQuestion())
      ->setTitle($title)
      ->setContent($content)
      ->setAuthorPHID($user->getPHID())
      ->setContentSource($content_source)
      ->setVoteCount(0)
      ->setAnswerCount(0)
      ->setHeat(0.0)
      ->save();

    PhabricatorSearchPonderIndexer::indexQuestion($question);

    return id(new AphrontRedirectResponse())
      ->setURI('/Q'.$question->getID());
  }

  private function showForm(
      $errors = null,
      $title = "",
      $content = "",
      $id = null) {

    require_celerity_resource('ponder-core-view-css');
    require_celerity_resource('phabricator-remarkup-css');
    require_celerity_resource('ponder-post-css');

    $request = $this->getRequest();
    $user = $request->getUser();
    $error_view = null;

    if ($errors) {
      $error_view = id(new AphrontErrorView())
        ->setTitle('Form Errors')
        ->setErrors($errors);
    }

    $form = new AphrontFormView();
    $form->setUser($user);
    $form->setAction('/ponder/question/ask/');
    $form
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Title')
          ->setName('title')
          ->setValue($title))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setName('content')
          ->setID('content')
          ->setValue($content)
          ->setLabel("Question")
          ->setCaption(phutil_render_tag(
            'a',
            array(
              'href' => PhabricatorEnv::getDoclink(
                'article/Remarkup_Reference.html'),
              'tabindex' => '-1',
              'target' => '_blank',
            ),
            "Formatting Reference")))
      ->appendChild(
        id(new AphrontFormSubmitControl())
        ->setValue('Ask Away!'));

    $panel = id(new AphrontPanelView())
      ->addClass("ponder-panel")
      ->setHeader("Your Question:")
      ->appendChild($error_view)
      ->appendChild($form);

    $panel->appendChild(
      '<div class="aphront-panel-flush">'.
        '<div id="question-preview">'.
          '<span class="aphront-panel-preview-loading-text">'.
            'Loading question preview...'.
          '</span>'.
        '</div>'.
      '</div>'
    );

    Javelin::initBehavior(
      'ponder-feedback-preview',
      array(
        'uri'         => '/ponder/question/preview/',
        'content'     => 'content',
        'preview'     => 'question-preview',
        'question_id' => null
      ));

    return $this->buildStandardPageResponse(
      array($panel),
      array('title' => 'Ask a Question')
    );
  }

}
