<?php

final class PonderQuestionHistoryController extends PonderController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $question = id(new PonderQuestionQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->id))
      ->executeOne();
    if (!$question) {
      return new Aphront404Response();
    }

    $timeline = $this->buildTransactionTimeline(
      $question,
      new PonderQuestionTransactionQuery());
    $timeline->setShouldTerminate(true);

    $qid = $question->getID();

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb("Q{$qid}", "/Q{$qid}");
    $crumbs->addTextCrumb(pht('History'));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $timeline,
      ),
      array(
        'title' => pht('Question History'),
      ));
  }

}
