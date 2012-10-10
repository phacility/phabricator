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

final class PonderQuestionDetailView extends AphrontView {

  private $question;
  private $user;
  private $handles;

  public function setQuestion($question) {
    $this->question = $question;
    return $this;
  }

  public function setUser($user) {
    $this->user = $user;
    return $this;
  }

  public function setHandles($handles) {
    $this->handles = $handles;
    return $this;
  }

  public function render() {
    require_celerity_resource('ponder-core-view-css');

    $question = $this->question;
    $handles = $this->handles;
    $user = $this->user;

    $panel = id(new AphrontPanelView())
      ->addClass("ponder-panel");

    $contentview = new PonderPostBodyView();
    $contentview
      ->setTarget($question)
      ->setQuestion($question)
      ->setUser($user)
      ->setHandles($handles)
      ->setAction(PonderConstants::ASKED_LITERAL);

    $commentview = new PonderCommentListView();
    $commentview
      ->setUser($user)
      ->setHandles($handles)
      ->setComments($question->getComments())
      ->setTarget($question->getPHID())
      ->setQuestionID($question->getID())
      ->setActionURI(new PhutilURI('/ponder/comment/add/'));

    $panel->appendChild($contentview);
    $panel->appendChild($commentview);

    return $panel->render();
  }

}
