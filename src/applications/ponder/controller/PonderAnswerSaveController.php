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

final class PonderAnswerSaveController extends PonderController {

  public function processRequest() {
    $request = $this->getRequest();
    if (!$request->isFormPost()) {
      return new Aphront400Response();
    }

    $user = $request->getUser();
    $question_id = $request->getInt('question_id');
    $question = PonderQuestionQuery::loadSingle($user, $question_id);

    if (!$question) {
      return new Aphront404Response();
    }

    $answer = $request->getStr('answer');

    $content_source = PhabricatorContentSource::newForSource(
      PhabricatorContentSource::SOURCE_WEB,
      array(
        'ip' => $request->getRemoteAddr(),
      ));

    $res = new PonderAnswer();
    $res
      ->setContent($answer)
      ->setAuthorPHID($user->getPHID())
      ->setVoteCount(0)
      ->setQuestionID($question_id)
      ->setContentSource($content_source);

    id(new PonderAnswerEditor())
      ->setQuestion($question)
      ->setAnswer($res)
      ->saveAnswer();

    PhabricatorSearchPonderIndexer::indexQuestion($question);

    return id(new AphrontRedirectResponse())
      ->setURI(id(new PhutilURI('/Q'. $question->getID()))
        ->setFragment('A'.$res->getID()));
  }

}
