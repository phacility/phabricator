<?php

final class PhamePostFramedController extends PhamePostController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

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

    $blog = $post->getBlog();

    $phame_request = $request->setPath('/post/'.$post->getPhameTitle());
    $skin = $post->getBlog()->getSkinRenderer($phame_request);

    $uri = clone $request->getRequestURI();
    $uri->setPath('/phame/live/'.$blog->getID().'/');

    $skin
      ->setPreview(true)
      ->setBlog($post->getBlog())
      ->setBaseURI((string)$uri);

    $response = $skin->processRequest();
    $response->setFrameable(true);
    return $response;
  }

}
