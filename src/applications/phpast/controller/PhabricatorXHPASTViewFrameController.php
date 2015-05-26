<?php

final class PhabricatorXHPASTViewFrameController
  extends PhabricatorXHPASTViewController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $id = $this->id;

    return $this->buildStandardPageResponse(
      phutil_tag(
        'iframe',
        array(
          'src'         => '/xhpast/frameset/'.$id.'/',
          'frameborder' => '0',
          'style'       => 'width: 100%; height: 800px;',
        '',
      )),
      array(
        'title' => pht('XHPAST View'),
      ));
  }
}
