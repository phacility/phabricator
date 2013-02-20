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
      $base_uri = 'http://'.$blog->getDomain().'/';
      if ($request->isFormPost()) {
        return id(new AphrontRedirectResponse())
          ->setURI($base_uri.$this->more);
      } else {
        // If we don't have CSRF, return a dialog instead of automatically
        // redirecting, to prevent this endpoint from serving semi-open
        // redirects.
        $dialog = id(new AphrontDialogView())
          ->setTitle(pht('Blog Moved'))
          ->setUser($user)
          ->appendChild(
            pht('This blog is now hosted at %s.',
              $base_uri))
          ->addSubmitButton(pht('Continue'));
        return id(new AphrontDialogResponse())->setDialog($dialog);
      }
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
