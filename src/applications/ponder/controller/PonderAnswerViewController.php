<?php

final class PonderAnswerViewController extends PonderController {

  private $answerID;

  public function willProcessRequest(array $data) {
    $this->answerID = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $answer = id(new PonderAnswer())->load($this->answerID);

    if (!$answer) {
      return new Aphront404Response();
    }

    $question_id = $answer->getQuestionID();

    return id(new AphrontRedirectResponse())
      ->setURI('/Q'.$question_id . '#A' . $answer->getID());
  }

}
