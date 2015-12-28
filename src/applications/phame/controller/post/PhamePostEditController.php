<?php

final class PhamePostEditController extends PhamePostController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    if ($id) {
      $post = id(new PhamePostQuery())
        ->setViewer($viewer)
        ->withIDs(array($id))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
      if (!$post) {
        return new Aphront404Response();
      }
      $blog_id = $post->getBlog()->getID();
    } else {
      $blog_id = $request->getInt('blog');
    }

    $blog = id(new PhameBlogQuery())
      ->setViewer($viewer)
      ->withIDs(array($blog_id))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();

    if (!$blog) {
      return new Aphront404Response();
    }

    return id(new PhamePostEditEngine())
      ->setController($this)
      ->setBlog($blog)
      ->buildResponse();
  }

}
