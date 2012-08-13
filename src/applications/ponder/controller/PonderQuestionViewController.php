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

final class PonderQuestionViewController extends PonderController {

  private $questionID;

  public function willProcessRequest(array $data) {
    $this->questionID = $data['id'];
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $question = PonderQuestionQuery::loadSingle($user, $this->questionID);
    if (!$question) {
      return new Aphront404Response();
    }
    $question->attachRelated($user->getPHID());
    $answers = $question->getAnswers();

    $object_phids = array($user->getPHID(), $question->getAuthorPHID());
    foreach ($answers as $answer) {
      $object_phids[] = $answer->getAuthorPHID();
    }

    $handles = id(new PhabricatorObjectHandleData($object_phids))
      ->loadHandles();

    $detail_panel = new PonderQuestionDetailView();
    $detail_panel
      ->setQuestion($question)
      ->setUser($user)
      ->setHandles($handles);

    $responses_panel = new PonderAnswerListView();
    $responses_panel
      ->setQuestion($question)
      ->setHandles($handles)
      ->setUser($user)
      ->setAnswers($answers);

    $answer_add_panel = new PonderAddAnswerView();
    $answer_add_panel
      ->setQuestion($question)
      ->setUser($user)
      ->setActionURI("/ponder/answer/add/");

    return $this->buildStandardPageResponse(
      array(
        $detail_panel,
        $responses_panel,
        $answer_add_panel
      ),
      array(
        'title' => 'Q'.$question->getID().' '.$question->getTitle()
      ));
  }
}
