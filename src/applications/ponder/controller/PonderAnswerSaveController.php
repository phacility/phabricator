<?php

final class PonderAnswerSaveController extends PonderController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();

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

    $content = $request->getStr('answer');

    if (!strlen(trim($content))) {
      $dialog = id(new AphrontDialogView())
        ->setUser($viewer)
        ->setTitle(pht('Empty Answer'))
        ->appendChild(
          phutil_tag('p', array(), pht('Your answer must not be empty.')))
        ->addCancelButton('/Q'.$question_id);

      return id(new AphrontDialogResponse())->setDialog($dialog);
    }

    $answer = PonderAnswer::initializeNewAnswer($viewer, $question);

    // Question Editor

    $xactions = array();
    $xactions[] = id(new PonderQuestionTransaction())
      ->setTransactionType(PonderQuestionTransaction::TYPE_ANSWERS)
      ->setNewValue(
        array(
          '+' => array(
            array('answer' => $answer),
          ),
        ));

    $editor = id(new PonderQuestionEditor())
      ->setActor($viewer)
      ->setContentSourceFromRequest($request);

    $editor->applyTransactions($question, $xactions);

    // Answer Editor

    $template = id(new PonderAnswerTransaction());
    $xactions = array();

    $xactions[] = id(clone $template)
      ->setTransactionType(PonderAnswerTransaction::TYPE_QUESTION_ID)
      ->setNewValue($question->getID());

    $xactions[] = id(clone $template)
      ->setTransactionType(PonderAnswerTransaction::TYPE_CONTENT)
      ->setNewValue($content);

    $editor = id(new PonderAnswerEditor())
      ->setActor($viewer)
      ->setContentSourceFromRequest($request)
      ->setContinueOnNoEffect(true);

    $editor->applyTransactions($answer, $xactions);


    return id(new AphrontRedirectResponse())->setURI(
      id(new PhutilURI('/Q'.$question->getID())));
  }
}
