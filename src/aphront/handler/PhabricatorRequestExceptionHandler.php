<?php

abstract class PhabricatorRequestExceptionHandler
  extends AphrontRequestExceptionHandler {

  protected function isPhabricatorSite(AphrontRequest $request) {
    $site = $request->getSite();
    if (!$site) {
      return false;
    }

    return ($site instanceof PhabricatorSite);
  }

  protected function getViewer(AphrontRequest $request) {
    $viewer = $request->getUser();

    if ($viewer) {
      return $viewer;
    }

    // If we hit an exception very early, we won't have a user yet.
    return new PhabricatorUser();
  }

}
