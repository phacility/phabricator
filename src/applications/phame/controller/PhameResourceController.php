<?php

final class PhameResourceController extends CelerityResourceController {

  private $id;
  private $hash;
  private $name;
  private $root;
  private $celerityResourceMap;

  public function getCelerityResourceMap() {
    return $this->celerityResourceMap;
  }

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
    $this->hash = $data['hash'];
    $this->name = $data['name'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    // We require a visible blog associated with a given skin to serve
    // resources, so you can't go fishing around where you shouldn't be.
    // However, since these resources may be served off a CDN domain, we're
    // bypassing the actual policy check. The blog needs to exist, but you
    // don't necessarily need to be able to see it in order to see static
    // resources on it.

    $blog = id(new PhameBlogQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withIDs(array($this->id))
      ->executeOne();
    if (!$blog) {
      return new Aphront404Response();
    }

    $skin = $blog->getSkinRenderer($request);
    $spec = $skin->getSpecification();

    $resources = new PhameCelerityResources();
    $resources->setSkin($spec);

    $this->root = $spec->getRootDirectory();
    $this->celerityResourceMap = new CelerityResourceMap($resources);

    return $this->serveResource($this->name);
  }

  protected function buildResourceTransformer() {
    $xformer = new CelerityResourceTransformer();
    $xformer->setMinify(false);
    $xformer->setTranslateURICallback(array($this, 'translateResourceURI'));
    return $xformer;
  }

  public function translateResourceURI(array $matches) {
    $uri = trim($matches[1], "'\" \r\t\n");

    if (Filesystem::pathExists($this->root.$uri)) {
      $hash = filemtime($this->root.$uri);
    } else {
      $hash = '-';
    }

    $uri = '/phame/r/'.$this->id.'/'.$hash.'/'.$uri;
    return 'url('.$uri.')';
  }

}
