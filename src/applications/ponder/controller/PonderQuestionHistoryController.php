<?php

final class PonderQuestionHistoryController extends PonderController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $question = id(new PonderQuestionQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
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
    $crumbs->setBorder(true);
    $crumbs->addTextCrumb("Q{$qid}", "/Q{$qid}");
    $crumbs->addTextCrumb(pht('History'));
    $crumbs->setBorder(true);

    $header = id(new PHUIHeaderView())
      ->setHeader($question->getTitle())
      ->setHeaderIcon('fa-history');

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter($timeline);

    return $this->newPage()
      ->setTitle(pht('Question History'))
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

}
