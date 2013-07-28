<?php

final class PonderQuestionStatusController
  extends PonderController {

  private $status;
  private $id;

  public function willProcessRequest(array $data) {
    $this->status = idx($data, 'status');
    $this->id = idx($data, 'id');
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $question = id(new PonderQuestion())->load($this->id);
    if (!$question) {
      return new Aphront404Response();
    }

    switch ($this->status) {
      case 'open':
        $question->setStatus(PonderQuestionStatus::STATUS_OPEN);
        break;
      case 'close':
        $question->setStatus(PonderQuestionStatus::STATUS_CLOSED);
        break;
      default:
        return new Aphront400Response();
    }

    $question->save();

    return id(new AphrontRedirectResponse())->setURI('/Q'.$question->getID());
  }

}
