<?php

final class PhamePostPreviewController extends PhameController {

  protected function getSideNavFilter() {
    return null;
  }

  public function processRequest() {
    $request     = $this->getRequest();
    $user        = $request->getUser();
    $body        = $request->getStr('body');

    $post = id(new PhamePost())
      ->setBody($body);

    $content = PhabricatorMarkupEngine::renderOneObject(
      $post,
      PhamePost::MARKUP_FIELD_BODY,
      $user);

    $content = phutil_tag_div('phabricator-remarkup', $content);

    return id(new AphrontAjaxResponse())->setContent($content);
  }

}
