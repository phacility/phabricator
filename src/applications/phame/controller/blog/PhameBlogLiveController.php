<?php

final class PhameBlogLiveController extends PhameController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $user = $request->getUser();

    $site = $request->getSite();
    if ($site instanceof PhameBlogSite) {
      $blog = $site->getBlog();
    } else {
      $id = $request->getURIData('id');

      $blog = id(new PhameBlogQuery())
        ->setViewer($user)
        ->withIDs(array($id))
        ->executeOne();
      if (!$blog) {
        return new Aphront404Response();
      }
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
    $more = $phame_request->getURIData('more', '');
    $phame_request->setPath('/'.ltrim($more, '/'));

    $uri = $blog->getLiveURI();

    $skin = $blog->getSkinRenderer($phame_request);
    $skin
      ->setBlog($blog)
      ->setBaseURI($uri);

    $skin->willProcessRequest(array());
    return $skin->processRequest();
  }

}
