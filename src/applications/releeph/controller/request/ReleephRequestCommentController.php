<?php

final class ReleephRequestCommentController
  extends ReleephRequestController {

  private $requestID;

  public function willProcessRequest(array $data) {
    $this->requestID = $data['requestID'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    if (!$request->isFormPost()) {
      return new Aphront400Response();
    }

    $pull = id(new ReleephRequestQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->requestID))
      ->executeOne();
    if (!$pull) {
      return new Aphront404Response();
    }

    $is_preview = $request->isPreviewRequest();
    $draft = PhabricatorDraft::buildFromRequest($request);

    $view_uri = $this->getApplicationURI('/'.$pull->getMonogram());

    $xactions = array();
    $xactions[] = id(new ReleephRequestTransaction())
      ->setTransactionType(PhabricatorTransactions::TYPE_COMMENT)
      ->attachComment(
        id(new ReleephRequestTransactionComment())
          ->setContent($request->getStr('comment')));

    $editor = id(new ReleephRequestTransactionalEditor())
      ->setActor($viewer)
      ->setContinueOnNoEffect($request->isContinueRequest())
      ->setContentSourceFromRequest($request)
      ->setIsPreview($is_preview);

    try {
      $xactions = $editor->applyTransactions($pull, $xactions);
    } catch (PhabricatorApplicationTransactionNoEffectException $ex) {
      return id(new PhabricatorApplicationTransactionNoEffectResponse())
        ->setCancelURI($view_uri)
        ->setException($ex);
    }

    if ($draft) {
      $draft->replaceOrDelete();
    }

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
