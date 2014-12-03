<?php

final class PonderAnswerCommentController extends PonderController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    if (!$request->isFormPost()) {
      return new Aphront400Response();
    }

    $answer = id(new PonderAnswerQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->id))
      ->executeOne();
    if (!$answer) {
      return new Aphront404Response();
    }

    $is_preview = $request->isPreviewRequest();
//    $draft = PhabricatorDraft::buildFromRequest($request);

    $qid = $answer->getQuestion()->getID();
    $aid = $answer->getID();
    $view_uri = "/Q{$qid}#A{$aid}";

    $xactions = array();
    $xactions[] = id(new PonderAnswerTransaction())
      ->setTransactionType(PhabricatorTransactions::TYPE_COMMENT)
      ->attachComment(
        id(new PonderAnswerTransactionComment())
          ->setContent($request->getStr('comment')));

    $editor = id(new PonderAnswerEditor())
      ->setActor($viewer)
      ->setContinueOnNoEffect($request->isContinueRequest())
      ->setContentSourceFromRequest($request)
      ->setIsPreview($is_preview);

    try {
      $xactions = $editor->applyTransactions($answer, $xactions);
    } catch (PhabricatorApplicationTransactionNoEffectException $ex) {
      return id(new PhabricatorApplicationTransactionNoEffectResponse())
        ->setCancelURI($view_uri)
        ->setException($ex);
    }

//    if ($draft) {
//      $draft->replaceOrDelete();
//    }

    if ($request->isAjax() && $is_preview) {
      return id(new PhabricatorApplicationTransactionResponse())
        ->setViewer($viewer)
        ->setTransactions($xactions)
        ->setIsPreview($is_preview);
    } else {
      return id(new AphrontRedirectResponse())
        ->setURI($view_uri);
    }
  }

}
