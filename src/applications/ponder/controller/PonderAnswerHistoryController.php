<?php

final class PonderAnswerHistoryController extends PonderController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $answer = id(new PonderAnswerQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->id))
      ->executeOne();
    if (!$answer) {
      return new Aphront404Response();
    }


    $timeline = $this->buildTransactionTimeline(
      $answer,
      new PonderAnswerTransactionQuery());
    $timeline->setShouldTerminate(true);

    $qid = $answer->getQuestion()->getID();
    $aid = $answer->getID();

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb("Q{$qid}", "/Q{$qid}");
    $crumbs->addTextCrumb("A{$aid}", "/Q{$qid}#{$aid}");
    $crumbs->addTextCrumb(pht('History'));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $timeline,
      ),
      array(
        'title' => pht('Answer History'),
      ));
  }

}
