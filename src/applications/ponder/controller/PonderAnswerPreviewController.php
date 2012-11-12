<?php

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
    $handles = $this->loadViewerHandles($object_phids);

    $answer = new PonderAnswer();
    $answer->setContent($request->getStr('content'));
    $answer->setAuthorPHID($author_phid);

    $view = new PonderPostBodyView();
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
