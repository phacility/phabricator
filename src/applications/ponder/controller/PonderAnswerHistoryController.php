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

    $xactions = id(new PonderAnswerTransactionQuery())
      ->setViewer($viewer)
      ->withObjectPHIDs(array($answer->getPHID()))
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
      ->setObjectPHID($answer->getPHID())
      ->setTransactions($xactions)
      ->setMarkupEngine($engine);

    $qid = $answer->getQuestion()->getID();
    $aid = $answer->getID();

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName("Q{$qid}")
        ->setHref("/Q{$qid}"));
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName("A{$aid}")
        ->setHref("/Q{$qid}#{$aid}"));
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('History')));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $timeline,
      ),
      array(
        'title' => pht('Answer History'),
        'dust' => true,
        'device' => true,
      ));
  }

}
