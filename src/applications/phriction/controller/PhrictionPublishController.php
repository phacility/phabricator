<?php

final class PhrictionPublishController
  extends PhrictionController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('documentID');
    $content_id = $request->getURIData('contentID');

    $document = id(new PhrictionDocumentQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->needContent(true)
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_EDIT,
          PhabricatorPolicyCapability::CAN_VIEW,
        ))
      ->executeOne();
    if (!$document) {
      return new Aphront404Response();
    }

    $document_uri = $document->getURI();

    $content = id(new PhrictionContentQuery())
      ->setViewer($viewer)
      ->withIDs(array($content_id))
      ->executeOne();
    if (!$content) {
      return new Aphront404Response();
    }

    if ($content->getPHID() == $document->getContentPHID()) {
      return $this->newDialog()
        ->setTitle(pht('Already Published'))
        ->appendChild(
          pht(
            'This version of the document is already the published '.
            'version.'))
        ->addCancelButton($document_uri);
    }

    $content_uri = $document_uri.'?v='.$content->getVersion();

    if ($request->isFormPost()) {
      $xactions = array();

      $xactions[] = id(new PhrictionTransaction())
        ->setTransactionType(
          PhrictionDocumentPublishTransaction::TRANSACTIONTYPE)
        ->setNewValue($content->getPHID());

      id(new PhrictionTransactionEditor())
        ->setActor($viewer)
        ->setContentSourceFromRequest($request)
        ->setContinueOnNoEffect(true)
        ->setContinueOnMissingFields(true)
        ->applyTransactions($document, $xactions);

      return id(new AphrontRedirectResponse())->setURI($document_uri);
    }

    if ($content->getVersion() < $document->getContent()->getVersion()) {
      $title = pht('Publish Older Version?');
      $body = pht(
        'Revert the published version of this document to an older '.
        'version?');
      $button = pht('Revert');
    } else {
      $title = pht('Publish Draft?');
      $body = pht(
        'Update the published version of this document to this newer '.
        'version?');
      $button = pht('Publish');
    }

    return $this->newDialog()
      ->setTitle($title)
      ->appendChild($body)
      ->addSubmitButton($button)
      ->addCancelButton($content_uri);
  }

}
