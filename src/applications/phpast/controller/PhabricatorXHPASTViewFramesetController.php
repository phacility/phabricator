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
    $response->setContent(
      '<frameset cols="33%, 34%, 33%">'.
        '<frame src="/xhpast/input/'.$id.'/" />'.
        '<frame src="/xhpast/tree/'.$id.'/" />'.
        '<frame src="/xhpast/stream/'.$id.'/" />'.
      '</frameset>');

    return $response;
  }
}
