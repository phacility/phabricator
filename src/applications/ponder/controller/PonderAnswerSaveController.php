<?php

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

    // Only want answers with some non whitespace content
    if (!strlen(trim($answer))) {
      $dialog = new AphrontDialogView();
      $dialog->setUser($request->getUser());
      $dialog->setTitle('Empty answer');
      $dialog->appendChild(phutil_tag('p', array(), pht(
        'Your answer must not be empty.')));
      $dialog->addCancelButton('/Q'.$question_id);

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
      ->setAuthorPHID($user->getPHID())
      ->setVoteCount(0)
      ->setQuestionID($question_id)
      ->setContentSource($content_source);

    id(new PonderAnswerEditor())
      ->setActor($user)
      ->setQuestion($question)
      ->setAnswer($res)
      ->saveAnswer();

    return id(new AphrontRedirectResponse())->setURI(
      id(new PhutilURI('/Q'. $question->getID())));
  }
}
