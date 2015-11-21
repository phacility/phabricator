<?php

final class PhabricatorPhurlURLCommentController
  extends PhabricatorPhurlController {

  public function handleRequest(AphrontRequest $request) {
    if (!$request->isFormPost()) {
      return new Aphront400Response();
    }

    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $is_preview = $request->isPreviewRequest();
    $draft = PhabricatorDraft::buildFromRequest($request);

    $phurl = id(new PhabricatorPhurlURLQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$phurl) {
      return new Aphront404Response();
    }

    $view_uri = '/'.$phurl->getMonogram();

    $xactions = array();
    $xactions[] = id(new PhabricatorPhurlURLTransaction())
      ->setTransactionType(PhabricatorTransactions::TYPE_COMMENT)
      ->attachComment(
        id(new PhabricatorPhurlURLTransactionComment())
          ->setContent($request->getStr('comment')));

    $editor = id(new PhabricatorPhurlURLEditor())
      ->setActor($viewer)
      ->setContinueOnNoEffect($request->isContinueRequest())
      ->setContentSourceFromRequest($request)
      ->setIsPreview($is_preview);

    try {
      $xactions = $editor->applyTransactions($phurl, $xactions);
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
