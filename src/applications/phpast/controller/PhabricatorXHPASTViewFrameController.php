<?php

final class PhabricatorXHPASTViewFrameController
  extends PhabricatorXHPASTViewController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $id = $request->getURIData('id');

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
