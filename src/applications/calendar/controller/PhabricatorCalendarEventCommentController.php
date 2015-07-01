<?php

final class PhabricatorCalendarEventCommentController
  extends PhabricatorCalendarController {

  private $id;


  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function handleRequest(AphrontRequest $request) {
    if (!$request->isFormPost()) {
      return new Aphront400Response();
    }

    $user = $request->getUser();
    $is_preview = $request->isPreviewRequest();
    $draft = PhabricatorDraft::buildFromRequest($request);

    $event = id(new PhabricatorCalendarEventQuery())
      ->setViewer($user)
      ->withIDs(array($this->id))
      ->executeOne();
    if (!$event) {
      return new Aphront404Response();
    }

    $index = $request->getURIData('sequence');
    if ($index && !$is_preview) {
      $result = $this->getEventAtIndexForGhostPHID(
        $user,
        $event->getPHID(),
        $index);

      if ($result) {
        $event = $result;
      } else {
        $event = $this->createEventFromGhost(
          $user,
          $event,
          $index);
        $event->applyViewerTimezone($user);
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
      ->setActor($user)
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
        ->setViewer($user)
        ->setTransactions($xactions)
        ->setIsPreview($is_preview);
    } else {
      return id(new AphrontRedirectResponse())
        ->setURI($view_uri);
    }
  }

}
