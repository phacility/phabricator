<?php

final class PonderAnswerHistoryController extends PonderController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $answer = id(new PonderAnswerQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
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
    $crumbs->setBorder(true);
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
