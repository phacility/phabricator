<?php

/**
 * @group phame
 */
final class PhameBlogLiveController extends PhameController {

  private $id;
  private $more;

  public function shouldAllowPublic() {
    return true;
  }

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
    $this->more = idx($data, 'more', '');
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $blog = id(new PhameBlogQuery())
      ->setViewer($user)
      ->withIDs(array($this->id))
      ->executeOne();
    if (!$blog) {
      return new Aphront404Response();
    }

    if ($blog->getDomain() && ($request->getHost() != $blog->getDomain())) {
      return id(new AphrontRedirectResponse())
        ->setURI('http://'.$blog->getDomain().'/'.$this->more);
    }

    $phame_request = clone $request;
    $phame_request->setPath('/'.ltrim($this->more, '/'));

    if ($blog->getDomain()) {
      $uri = new PhutilURI('http://'.$blog->getDomain().'/');
    } else {
      $uri = '/phame/live/'.$blog->getID().'/';
      $uri = PhabricatorEnv::getURI($uri);
    }

    $skin = $blog->getSkinRenderer($phame_request);
    $skin
      ->setBlog($blog)
      ->setBaseURI((string)$uri);

    $skin->willProcessRequest(array());
    return $skin->processRequest();
  }

}
