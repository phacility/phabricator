<?php

final class PhrictionMarkupPreviewController
  extends PhabricatorController {

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $text = $request->getStr('text');
    $slug = $request->getURIData('slug');

    $output = PhabricatorMarkupEngine::renderOneObject(
      id(new PhabricatorMarkupOneOff())
        ->setPreserveLinebreaks(true)
        ->setDisableCache(true)
        ->setContent($text),
      'default',
      $viewer,
      array(
        'phriction.isPreview' => true,
        'phriction.slug' => $slug,
      ));

    return id(new AphrontAjaxResponse())
      ->setContent($output);
  }
}
