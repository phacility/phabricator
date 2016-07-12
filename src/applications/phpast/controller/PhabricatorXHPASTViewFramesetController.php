<?php

final class PhabricatorXHPASTViewFramesetController
  extends PhabricatorXHPASTViewController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $id = $request->getURIData('id');

    return id(new AphrontWebpageResponse())
      ->setFrameable(true)
      ->setContent(phutil_tag(
        'frameset',
        array('cols' => '33%, 34%, 33%'),
          array(
            phutil_tag('frame', array('src' => "/xhpast/input/{$id}/")),
            phutil_tag('frame', array('src' => "/xhpast/tree/{$id}/")),
            phutil_tag('frame', array('src' => "/xhpast/stream/{$id}/")),
        )));
  }
}
