<?php

final class PhameBlogSite extends PhameSite {

  private $blog;

  public function setBlog(PhameBlog $blog) {
    $this->blog = $blog;
    return $this;
  }

  public function getBlog() {
    return $this->blog;
  }

  public function getDescription() {
    return pht('Serves blogs with custom domains.');
  }

  public function shouldRequireHTTPS() {
    // TODO: We should probably provide options here eventually, but for now
    // just never require HTTPS for external-domain blogs.
    return false;
  }

  public function getPriority() {
    return 3000;
  }

  public function newSiteForRequest(AphrontRequest $request) {
    if (!$this->isPhameActive()) {
      return null;
    }

    $host = $request->getHost();

    try {
      $blog = id(new PhameBlogQuery())
        ->setViewer(new PhabricatorUser())
        ->withDomain($host)
        ->needProfileImage(true)
        ->withStatuses(
          array(
            PhameBlog::STATUS_ACTIVE,
          ))
        ->executeOne();
    } catch (PhabricatorPolicyException $ex) {
      throw new Exception(
        pht(
          'This blog is not visible to logged out users, so it can not be '.
          'visited from a custom domain.'));
    }

    if (!$blog) {
      return null;
    }

    return id(new PhameBlogSite())->setBlog($blog);
  }

  public function getRoutingMaps() {
    $app = PhabricatorApplication::getByClass('PhabricatorPhameApplication');

    $maps = array();
    $maps[] = $this->newRoutingMap()
      ->setApplication($app)
      ->setRoutes($app->getBlogRoutes());
    return $maps;
  }

}
