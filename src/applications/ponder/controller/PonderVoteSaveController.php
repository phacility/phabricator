<?php

final class PonderVoteSaveController extends PonderController {

  private $kind;

  public function willProcessRequest(array $data) {
    $this->kind = $data['kind'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();
    $newvote = $request->getInt("vote");
    $phid = $request->getStr("phid");

    if (1 < $newvote || $newvote < -1) {
      return new Aphront400Response();
    }

    $target = null;

    if ($this->kind == "question") {
      $target = PonderQuestionQuery::loadSingleByPHID($user, $phid);
    } else if ($this->kind == "answer") {
      $target = PonderAnswerQuery::loadSingleByPHID($user, $phid);
    }

    if (!$target) {
      return new Aphront404Response();
    }

    $editor = id(new PonderVoteEditor())
      ->setVotable($target)
      ->setActor($user)
      ->setVote($newvote)
      ->saveVote();

    return id(new AphrontAjaxResponse())->setContent(".");
  }
}
