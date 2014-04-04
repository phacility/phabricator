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
      $base_uri = $blog->getLiveURI();

      // Don't redirect directly, since the domain is user-controlled and there
      // are a bevy of security issues associated with automatic redirects to
      // external domains.

      // Previously we CSRF'd this and someone found a way to pass OAuth
      // information through it using anchors. Just make users click a normal
      // link so that this is no more dangerous than any other external link
      // on the site.

      $dialog = id(new AphrontDialogView())
        ->setTitle(pht('Blog Moved'))
        ->setUser($user)
        ->appendParagraph(pht('This blog is now hosted here:'))
        ->appendParagraph(
          phutil_tag(
            'a',
            array(
              'href' => $base_uri,
            ),
            $base_uri))
        ->addCancelButton('/');

      return id(new AphrontDialogResponse())->setDialog($dialog);
    }

    $phame_request = clone $request;
    $phame_request->setPath('/'.ltrim($this->more, '/'));

    $uri = $blog->getLiveURI();

    $skin = $blog->getSkinRenderer($phame_request);
    $skin
      ->setBlog($blog)
      ->setBaseURI($uri);

    $skin->willProcessRequest(array());
    return $skin->processRequest();
  }

}
