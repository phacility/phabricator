<?php

final class PonderQuestionPreviewController
  extends PonderController {

  const VERB_ASKED = "asked";

  public function processRequest() {

    $request = $this->getRequest();

    $user = $request->getUser();
    $author_phid = $user->getPHID();

    $object_phids = array($author_phid);
    $handles = $this->loadViewerHandles($object_phids);

    $question = new PonderQuestion();
    $question->setContent($request->getStr('content'));
    $question->setAuthorPHID($author_phid);

    $view = new PonderPostBodyView();
    $view
      ->setQuestion($question)
      ->setTarget($question)
      ->setPreview(true)
      ->setUser($user)
      ->setHandles($handles)
      ->setAction(self::VERB_ASKED);

    return id(new AphrontAjaxResponse())
      ->setContent($view->render());
  }

}
