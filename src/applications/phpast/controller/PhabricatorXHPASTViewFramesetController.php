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
    $response->setContent(phutil_tag(
      'frameset',
      array('cols' => '33%, 34%, 33%'),
      array(
        phutil_tag('frame', array('src' => "/xhpast/input/{$id}/")),
        phutil_tag('frame', array('src' => "/xhpast/tree/{$id}/")),
        phutil_tag('frame', array('src' => "/xhpast/stream/{$id}/")),
      )));

    return $response;
  }
}
