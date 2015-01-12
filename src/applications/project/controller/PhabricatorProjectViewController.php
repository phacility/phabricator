<?php

final class PhabricatorProjectViewController
  extends PhabricatorProjectController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $request = $this->getRequest();
    $user = $request->getUser();

    $query = id(new PhabricatorProjectQuery())
      ->setViewer($user)
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
      return new Aphront404Response();
    }


    $columns = id(new PhabricatorProjectColumnQuery())
      ->setViewer($user)
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

}
