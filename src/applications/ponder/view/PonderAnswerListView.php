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

final class PonderAnswerListView extends AphrontView {

  private $question;
  private $handles;
  private $user;
  private $answers;

  public function setQuestion($question) {
    $this->question = $question;
    return $this;
  }

  public function setHandles(array $handles) {
    assert_instances_of($handles, 'PhabricatorObjectHandle');
    $this->handles = $handles;
    return $this;
  }

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function setAnswers(array $answers) {
    assert_instances_of($answers, 'PonderAnswer');

    $this->answers = array();

    // group by descreasing score, randomizing
    // order within groups
    $by_score = mgroup($answers, 'getVoteCount');
    $scores = array_keys($by_score);
    rsort($scores);

    foreach ($scores as $score) {
      $group = $by_score[$score];
      shuffle($group);
      foreach ($group as $cur_answer) {
        $this->answers[] = $cur_answer;
      }
    }

    return $this;
  }

  public function render() {
    require_celerity_resource('ponder-post-css');

    $question = $this->question;
    $user = $this->user;
    $handles = $this->handles;

    $panel = id(new AphrontPanelView())
      ->addClass("ponder-panel")
      ->setHeader("Responses:");

    foreach ($this->answers as $cur_answer) {
      $view = new PonderCommentBodyView();
      $view
        ->setQuestion($question)
        ->setTarget($cur_answer)
        ->setAction(PonderConstants::ANSWERED_LITERAL)
        ->setHandles($handles)
        ->setUser($user);

      $panel->appendChild($view);
    }

    return $panel->render();
  }
}
