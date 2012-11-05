<?php

/**
 * @group phriction
 */
final class PhrictionDocumentPreviewController
  extends PhrictionController {

  public function processRequest() {

    $request = $this->getRequest();
    $document = $request->getStr('document');

    $draft_key = $request->getStr('draftkey');
    if ($draft_key) {
      id(new PhabricatorDraft())
        ->setAuthorPHID($request->getUser()->getPHID())
        ->setDraftKey($draft_key)
        ->setDraft($document)
        ->replaceOrDelete();
    }

    $content_obj = new PhrictionContent();
    $content_obj->setContent($document);
    $content = $content_obj->renderContent($request->getUser());

    return id(new AphrontAjaxResponse())->setContent($content);
  }
}
