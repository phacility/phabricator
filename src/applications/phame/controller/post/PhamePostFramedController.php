<?php

final class PhamePostFramedController extends PhameController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $post = id(new PhamePostQuery())
      ->setViewer($user)
      ->withIDs(array($this->id))
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
