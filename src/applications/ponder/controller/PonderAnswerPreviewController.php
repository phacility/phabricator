<?php

final class PonderAnswerPreviewController
  extends PonderController {

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();
    $question_id = $request->getInt('question_id');

    $question = id(new PonderQuestionQuery())
      ->setViewer($viewer)
      ->withIDs(array($question_id))
      ->executeOne();
    if (!$question) {
      return new Aphront404Response();
    }

    $author_phid = $viewer->getPHID();
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
      ->setUser($viewer)
      ->setHandles($handles)
      ->setAction(PonderLiterals::LITERAL_ANSWERED);

    return id(new AphrontAjaxResponse())
      ->setContent($view->render());
  }

}
