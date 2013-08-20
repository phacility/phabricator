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

    $xactions = id(new PonderQuestionTransactionQuery())
      ->setViewer($viewer)
      ->withObjectPHIDs(array($question->getPHID()))
      ->execute();

    $engine = id(new PhabricatorMarkupEngine())
      ->setViewer($viewer);
    foreach ($xactions as $xaction) {
      if ($xaction->getComment()) {
        $engine->addObject(
          $xaction->getComment(),
          PhabricatorApplicationTransactionComment::MARKUP_FIELD_COMMENT);
      }
    }
    $engine->process();

    $timeline = id(new PhabricatorApplicationTransactionView())
      ->setUser($viewer)
      ->setObjectPHID($question->getPHID())
      ->setTransactions($xactions)
      ->setMarkupEngine($engine);

    $qid = $question->getID();

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName("Q{$qid}")
        ->setHref("/Q{$qid}"));
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('History')));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $timeline,
      ),
      array(
        'title' => pht('Question History'),
        'device' => true,
      ));
  }

}
