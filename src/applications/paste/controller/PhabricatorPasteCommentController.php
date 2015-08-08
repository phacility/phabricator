<?php

final class PhabricatorPasteCommentController
  extends PhabricatorPasteController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    if (!$request->isFormPost()) {
      return new Aphront400Response();
    }

    $paste = id(new PhabricatorPasteQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$paste) {
      return new Aphront404Response();
    }

    $is_preview = $request->isPreviewRequest();
    $draft = PhabricatorDraft::buildFromRequest($request);

    $view_uri = $paste->getURI();

    $xactions = array();
    $xactions[] = id(new PhabricatorPasteTransaction())
      ->setTransactionType(PhabricatorTransactions::TYPE_COMMENT)
      ->attachComment(
        id(new PhabricatorPasteTransactionComment())
          ->setContent($request->getStr('comment')));

    $editor = id(new PhabricatorPasteEditor())
      ->setActor($viewer)
      ->setContinueOnNoEffect($request->isContinueRequest())
      ->setContentSourceFromRequest($request)
      ->setIsPreview($is_preview);

    try {
      $xactions = $editor->applyTransactions($paste, $xactions);
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
