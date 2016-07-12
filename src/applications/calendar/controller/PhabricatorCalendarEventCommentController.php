<?php

final class PhabricatorCalendarEventCommentController
  extends PhabricatorCalendarController {

  public function handleRequest(AphrontRequest $request) {
    if (!$request->isFormPost()) {
      return new Aphront400Response();
    }

    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $is_preview = $request->isPreviewRequest();
    $draft = PhabricatorDraft::buildFromRequest($request);

    $event = id(new PhabricatorCalendarEventQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$event) {
      return new Aphront404Response();
    }

    $index = $request->getURIData('sequence');
    if ($index && !$is_preview) {
      $result = $this->getEventAtIndexForGhostPHID(
        $viewer,
        $event->getPHID(),
        $index);

      if ($result) {
        $event = $result;
      } else {
        $event = $this->createEventFromGhost(
          $viewer,
          $event,
          $index);
        $event->applyViewerTimezone($viewer);
      }
    }

    $view_uri = '/'.$event->getMonogram();

    $xactions = array();
    $xactions[] = id(new PhabricatorCalendarEventTransaction())
      ->setTransactionType(PhabricatorTransactions::TYPE_COMMENT)
      ->attachComment(
        id(new PhabricatorCalendarEventTransactionComment())
          ->setContent($request->getStr('comment')));

    $editor = id(new PhabricatorCalendarEventEditor())
      ->setActor($viewer)
      ->setContinueOnNoEffect($request->isContinueRequest())
      ->setContentSourceFromRequest($request)
      ->setIsPreview($is_preview);

    try {
      $xactions = $editor->applyTransactions($event, $xactions);
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
