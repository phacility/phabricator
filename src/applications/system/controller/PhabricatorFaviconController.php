<?php

final class PhabricatorFaviconController
  extends PhabricatorController {

  public function shouldRequireLogin() {
    return false;
  }

  public function handleRequest(AphrontRequest $request) {
    // See PHI1719. Phabricator uses "<link /"> tags in the document body
    // to direct user agents to icons, like this:
    //
    //   <link rel="icon" href="..." />
    //
    // However, some software requests the hard-coded path "/favicon.ico"
    // directly. To tidy the logs, serve some reasonable response rather than
    // a 404.

    // NOTE: Right now, this only works for the "PhabricatorPlatformSite".
    // Other sites (like custom Phame blogs) won't currently route this
    // path.

    $ref = id(new PhabricatorFaviconRef())
      ->setWidth(64)
      ->setHeight(64);

    id(new PhabricatorFaviconRefQuery())
      ->withRefs(array($ref))
      ->execute();

    return id(new AphrontRedirectResponse())
      ->setIsExternal(true)
      ->setURI($ref->getURI());
  }
}
