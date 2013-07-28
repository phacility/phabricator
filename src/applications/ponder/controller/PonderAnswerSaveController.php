<?php

final class PonderAnswerSaveController extends PonderController {

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    if (!$request->isFormPost()) {
      return new Aphront400Response();
    }

    $question_id = $request->getInt('question_id');
    $question = id(new PonderQuestionQuery())
      ->setViewer($viewer)
      ->withIDs(array($question_id))
      ->executeOne();
    if (!$question) {
      return new Aphront404Response();
    }

    $answer = $request->getStr('answer');

    if (!strlen(trim($answer))) {
      $dialog = id(new AphrontDialogView())
        ->setUser($viewer)
        ->setTitle(pht('Empty Answer'))
        ->appendChild(
          phutil_tag('p', array(), pht(
          'Your answer must not be empty.')))
        ->addCancelButton('/Q'.$question_id);

      return id(new AphrontDialogResponse())->setDialog($dialog);
    }

    $content_source = PhabricatorContentSource::newForSource(
      PhabricatorContentSource::SOURCE_WEB,
      array(
        'ip' => $request->getRemoteAddr(),
      ));

    $res = new PonderAnswer();
    $res
      ->setContent($answer)
      ->setAuthorPHID($viewer->getPHID())
      ->setVoteCount(0)
      ->setQuestionID($question_id)
      ->setContentSource($content_source);

    id(new PonderAnswerEditor())
      ->setActor($viewer)
      ->setQuestion($question)
      ->setAnswer($res)
      ->saveAnswer();

    return id(new AphrontRedirectResponse())->setURI(
      id(new PhutilURI('/Q'. $question->getID())));
  }
}
