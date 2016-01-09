<?php

final class PhabricatorApplicationTransactionRemarkupPreviewController
  extends PhabricatorApplicationTransactionController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $text = $request->getStr('text');

    $remarkup = new PHUIRemarkupView($viewer, $text);

    $content = array(
      'content' => hsprintf('%s', $remarkup),
    );

    return id(new AphrontAjaxResponse())
      ->setContent($content);
  }

}
