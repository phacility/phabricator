<?php

final class PhabricatorApplicationTransactionRemarkupPreviewController
  extends PhabricatorApplicationTransactionController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $corpus = $request->getStr('corpus');

    $remarkup = new PHUIRemarkupView($viewer, $corpus);

    $content = array(
      'content' => hsprintf('%s', $remarkup),
    );

    return id(new AphrontAjaxResponse())
      ->setContent($content);
  }

}
