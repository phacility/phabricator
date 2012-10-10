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

final class PonderCommentSaveController extends PonderController {

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

    $target = $request->getStr('target');
    $objects = id(new PhabricatorObjectHandleData(array($target)))
      ->loadHandles();
    if (!$objects) {
      return new Aphront404Response();
    }

    $content = $request->getStr('content');

    if (!strlen(trim($content))) {
      $dialog = new AphrontDialogView();
      $dialog->setUser($request->getUser());
      $dialog->setTitle('Empty comment');
      $dialog->appendChild('<p>Your comment must not be empty.</p>');
      $dialog->addCancelButton('/Q'.$question_id);

      return id(new AphrontDialogResponse())->setDialog($dialog);
    }

    $res = new PonderComment();
    $res
      ->setContent($content)
      ->setAuthorPHID($user->getPHID())
      ->setTargetPHID($target);

    id(new PonderCommentEditor())
      ->setQuestion($question)
      ->setComment($res)
      ->setTargetPHID($target)
      ->setActor($user)
      ->save();

    return id(new AphrontRedirectResponse())
      ->setURI(
        id(new PhutilURI('/Q'. $question->getID())));
  }

}
