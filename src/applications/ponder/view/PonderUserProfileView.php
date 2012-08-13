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

final class PonderUserProfileView extends AphrontView {
  private $user;
  private $questionoffset;
  private $answeroffset;
  private $questions;
  private $answers;
  private $pagesize;
  private $uri;
  private $qparam;
  private $aparam;

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function setQuestionOffset($offset) {
    $this->questionoffset = $offset;
    return $this;
  }

  public function setAnswerOffset($offset) {
    $this->answeroffset = $offset;
    return $this;
  }

  public function setQuestions($data) {
    $this->questions = $data;
    return $this;
  }

  public function setAnswers($data) {
    $this->answers = $data;
    return $this;
  }

  public function setPageSize($pagesize) {
    $this->pagesize = $pagesize;
    return $this;
  }

  public function setHandles($handles) {
    $this->handles = $handles;
    return $this;
  }

  public function setURI($uri, $qparam, $aparam) {
    $this->uri = $uri;
    $this->qparam = $qparam;
    $this->aparam = $aparam;
    return $this;
  }

  public function render() {
    require_celerity_resource('ponder-core-view-css');
    require_celerity_resource('ponder-feed-view-css');

    $user = $this->user;
    $qoffset = $this->questionoffset;
    $aoffset = $this->answeroffset;
    $questions = $this->questions;
    $answers = $this->answers;
    $handles = $this->handles;
    $uri = $this->uri;
    $qparam = $this->qparam;
    $aparam = $this->aparam;
    $pagesize = $this->pagesize;


    // display questions
    $question_panel = id(new AphrontPanelView())
      ->setHeader("Your Questions")
      ->addClass("ponder-panel");

    $question_panel->addButton(
      phutil_render_tag(
        'a',
        array(
          'href' => "/ponder/question/ask/",
          'class' => 'green button',
        ),
        "Ask a question"));

    $qpagebuttons = id(new AphrontPagerView())
      ->setPageSize($pagesize)
      ->setOffset($qoffset)
      ->setURI(
        $uri->alter(
          $aparam,
          $aoffset),
        $qparam);

    $questions = $qpagebuttons->sliceResults($questions);

    foreach ($questions as $question) {
      $cur = id(new PonderQuestionSummaryView())
        ->setUser($user)
        ->setQuestion($question)
        ->setHandles($handles);
      $question_panel->appendChild($cur);
    }

    $question_panel->appendChild($qpagebuttons);

    // display answers
    $answer_panel = id(new AphrontPanelView())
      ->setHeader("Your Answers")
      ->addClass("ponder-panel")
      ->appendChild(
        phutil_render_tag(
          'a',
          array('id' => 'answers'),
          "")
      );

    $apagebuttons = id(new AphrontPagerView())
      ->setPageSize($pagesize)
      ->setOffset($aoffset)
      ->setURI(
        $uri
          ->alter(
            $qparam,
            $qoffset)
          ->setFragment("answers"),
        $aparam);

    $answers = $apagebuttons->sliceResults($answers);

    foreach ($answers as $answer) {
      $cur = id(new PonderAnswerSummaryView())
        ->setUser($user)
        ->setAnswer($answer)
        ->setHandles($handles);
      $answer_panel->appendChild($cur);
    }

    $answer_panel->appendChild($apagebuttons);

    return $question_panel->render() . $answer_panel->render();
  }
}
