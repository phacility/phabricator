<?php

final class PhamePostPreviewController extends PhamePostController {

  protected function getSideNavFilter() {
    return null;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $body = $request->getStr('body');

    $post = id(new PhamePost())
      ->setBody($body);

    $content = PhabricatorMarkupEngine::renderOneObject(
      $post,
      PhamePost::MARKUP_FIELD_BODY,
      $viewer);

    $content = phutil_tag_div('phabricator-remarkup', $content);

    return id(new AphrontAjaxResponse())->setContent($content);
  }

}
