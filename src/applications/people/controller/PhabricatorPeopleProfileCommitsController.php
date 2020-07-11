<?php

final class PhabricatorPeopleProfileCommitsController
  extends PhabricatorPeopleProfileController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $id = $request->getURIData('id');

    $user = id(new PhabricatorPeopleQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->needProfile(true)
      ->needProfileImage(true)
      ->needAvailability(true)
      ->executeOne();
    if (!$user) {
      return new Aphront404Response();
    }

    $class = 'PhabricatorDiffusionApplication';
    if (!PhabricatorApplication::isClassInstalledForViewer($class, $viewer)) {
      return new Aphront404Response();
    }

    $this->setUser($user);
    $title = array(pht('Recent Commits'), $user->getUsername());
    $header = $this->buildProfileHeader();
    $commits = $this->buildCommitsView($user);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Recent Commits'));
    $crumbs->setBorder(true);

    $nav = $this->newNavigation(
      $user,
      PhabricatorPeopleProfileMenuEngine::ITEM_COMMITS);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->addClass('project-view-home')
      ->addClass('project-view-people-home')
      ->setFooter(array(
        $commits,
      ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->setNavigation($nav)
      ->appendChild($view);
  }

  private function buildCommitsView(PhabricatorUser $user) {
    $viewer = $this->getViewer();

    $commits = id(new DiffusionCommitQuery())
      ->setViewer($viewer)
      ->withAuthorPHIDs(array($user->getPHID()))
      ->needCommitData(true)
      ->needIdentities(true)
      ->setLimit(100)
      ->execute();

    $list = id(new DiffusionCommitGraphView())
      ->setViewer($viewer)
      ->setCommits($commits);

    return $list;
  }
}
