<?php

final class ReleephRequestCommentController
  extends ReleephProjectController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $rq = $this->getReleephRequest();

    if (!$request->isFormPost()) {
      return new Aphront400Response();
    }

    $is_preview = $request->isPreviewRequest();
    $draft = PhabricatorDraft::buildFromRequest($request);

    $view_uri = $this->getApplicationURI('/RQ'.$rq->getID());

    $xactions = array();
    $xactions[] = id(new ReleephRequestTransaction())
      ->setTransactionType(PhabricatorTransactions::TYPE_COMMENT)
      ->attachComment(
        id(new ReleephRequestTransactionComment())
          ->setContent($request->getStr('comment')));

    $editor = id(new ReleephRequestTransactionalEditor())
      ->setActor($user)
      ->setContinueOnNoEffect($request->isContinueRequest())
      ->setContentSourceFromRequest($request)
      ->setIsPreview($is_preview);

    try {
      $xactions = $editor->applyTransactions($rq, $xactions);
    } catch (PhabricatorApplicationTransactionNoEffectException $ex) {
      return id(new PhabricatorApplicationTransactionNoEffectResponse())
        ->setCancelURI($view_uri)
        ->setException($ex);
    }

    if ($draft) {
      $draft->replaceOrDelete();
    }

    if ($request->isAjax()) {
      return id(new PhabricatorApplicationTransactionResponse())
        ->setViewer($user)
        ->setTransactions($xactions)
        ->setIsPreview($is_preview)
        ->setAnchorOffset($request->getStr('anchor'));
    } else {
      return id(new AphrontRedirectResponse())
        ->setURI($view_uri);
    }
  }

}
