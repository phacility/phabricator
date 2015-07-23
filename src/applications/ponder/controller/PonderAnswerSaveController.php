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
      ->needAnswers(true)
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
          phutil_tag('p', array(), pht('Your answer must not be empty.')))
        ->addCancelButton('/Q'.$question_id);

      return id(new AphrontDialogResponse())->setDialog($dialog);
    }

    $content_source = PhabricatorContentSource::newForSource(
      PhabricatorContentSource::SOURCE_WEB,
      array(
        'ip' => $request->getRemoteAddr(),
      ));

    $res = id(new PonderAnswer())
      ->setAuthorPHID($viewer->getPHID())
      ->setQuestionID($question->getID())
      ->setContent($answer)
      ->setVoteCount(0)
      ->setContentSource($content_source);

    $xactions = array();
    $xactions[] = id(new PonderQuestionTransaction())
      ->setTransactionType(PonderQuestionTransaction::TYPE_ANSWERS)
      ->setNewValue(
        array(
          '+' => array(
            array('answer' => $res),
          ),
        ));

    $editor = id(new PonderQuestionEditor())
      ->setActor($viewer)
      ->setContentSourceFromRequest($request);

    $editor->applyTransactions($question, $xactions);

    return id(new AphrontRedirectResponse())->setURI(
      id(new PhutilURI('/Q'.$question->getID())));
  }
}
