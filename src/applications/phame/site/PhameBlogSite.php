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
    $full_uri = $this->getBlog()->getDomainFullURI();
    $full_uri = new PhutilURI($full_uri);

    return ($full_uri->getProtocol() == 'https');
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
        ->needHeaderImage(true)
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

  public function new404Controller(AphrontRequest $request) {
    return new PhameBlog404Controller();
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
