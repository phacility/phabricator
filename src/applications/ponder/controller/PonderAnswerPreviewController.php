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

final class PonderAnswerPreviewController
  extends PonderController {

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();
    $question_id = $request->getInt('question_id');

    $question = PonderQuestionQuery::loadSingle($user, $question_id);
    if (!$question) {
      return new Aphront404Response();
    }

    $author_phid = $user->getPHID();
    $object_phids = array($author_phid);
    $handles = id(new PhabricatorObjectHandleData($object_phids))
      ->loadHandles();

    $answer = new PonderAnswer();
    $answer->setContent($request->getStr('content'));
    $answer->setAuthorPHID($author_phid);

    $view = new PonderCommentBodyView();
    $view
      ->setQuestion($question)
      ->setTarget($answer)
      ->setPreview(true)
      ->setUser($user)
      ->setHandles($handles)
      ->setAction(PonderConstants::ANSWERED_LITERAL);

    return id(new AphrontAjaxResponse())
      ->setContent($view->render());
  }

}
