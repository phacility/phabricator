<?php

/**
 * @group legalpad
 */
final class LegalpadDocumentPreviewController
extends LegalpadController {

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();
    $text = $request->getStr('text');

    $body = id(new LegalpadDocumentBody())
      ->setText($text);

    $content = PhabricatorMarkupEngine::renderOneObject(
      $body,
      LegalpadDocumentBody::MARKUP_FIELD_TEXT,
      $user);

    $content = hsprintf('<div class="phabricator-remarkup">%s</div>', $content);

    return id(new AphrontAjaxResponse())->setContent($content);
  }
}
