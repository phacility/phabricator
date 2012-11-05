<?php

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
