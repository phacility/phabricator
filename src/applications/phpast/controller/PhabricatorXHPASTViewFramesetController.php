<?php

final class PhabricatorXHPASTViewFramesetController
  extends PhabricatorXHPASTViewController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $id = $this->id;

    $response = new AphrontWebpageResponse();
    $response->setFrameable(true);
    $response->setContent(hsprintf(
      '<frameset cols="33%%, 34%%, 33%%">'.
        '<frame src="/xhpast/input/%s/" />'.
        '<frame src="/xhpast/tree/%s/" />'.
        '<frame src="/xhpast/stream/%s/" />'.
      '</frameset>',
      $id,
      $id,
      $id));

    return $response;
  }
}
