<?php

final class PhabricatorProjectViewController
  extends PhabricatorProjectController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $request = $this->getRequest();
    $viewer = $request->getViewer();

    $query = id(new PhabricatorProjectQuery())
      ->setViewer($viewer)
      ->needMembers(true)
      ->needWatchers(true)
      ->needImages(true)
      ->needSlugs(true);
    $id = $request->getURIData('id');
    $slug = $request->getURIData('slug');
    if ($slug) {
      $query->withSlugs(array($slug));
    } else {
      $query->withIDs(array($id));
    }
    $project = $query->executeOne();
    if (!$project) {

      // If this request corresponds to a project but just doesn't have the
      // slug quite right, redirect to the proper URI.
      $uri = $this->getNormalizedURI($slug);
      if ($uri !== null) {
        return id(new AphrontRedirectResponse())->setURI($uri);
      }

      return new Aphront404Response();
    }

    $columns = id(new PhabricatorProjectColumnQuery())
      ->setViewer($viewer)
      ->withProjectPHIDs(array($project->getPHID()))
      ->execute();
    if ($columns) {
      $controller = 'board';
    } else {
      $controller = 'profile';
    }

    switch ($controller) {
      case 'board':
        $controller_object = new PhabricatorProjectBoardViewController();
        break;
      case 'profile':
      default:
        $controller_object = new PhabricatorProjectProfileController();
        break;
    }

    return $this->delegateToController($controller_object);
  }

  private function getNormalizedURI($slug) {
    if (!strlen($slug)) {
      return null;
    }

    $normal = PhabricatorSlug::normalizeProjectSlug($slug);
    if ($normal === $slug) {
      return null;
    }

    $viewer = $this->getViewer();

    // Do execute() instead of executeOne() here so we canonicalize before
    // raising a policy exception. This is a little more polished than letting
    // the user hit the error on any variant of the slug.

    $projects = id(new PhabricatorProjectQuery())
      ->setViewer($viewer)
      ->withSlugs(array($normal))
      ->execute();
    if (!$projects) {
      return null;
    }

    return "/tag/{$normal}/";
  }

}
