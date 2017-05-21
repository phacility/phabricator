<?php

final class PhabricatorPeopleProfileRevisionsController
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

    $class = 'PhabricatorDifferentialApplication';
    if (!PhabricatorApplication::isClassInstalledForViewer($class, $viewer)) {
      return new Aphront404Response();
    }

    $this->setUser($user);
    $title = array(pht('Recent Revisions'), $user->getUsername());
    $header = $this->buildProfileHeader();
    $commits = $this->buildRevisionsView($user);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Recent Revisions'));
    $crumbs->setBorder(true);

    $nav = $this->getProfileMenu();
    $nav->selectFilter(PhabricatorPeopleProfileMenuEngine::ITEM_REVISIONS);

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

  private function buildRevisionsView(PhabricatorUser $user) {
    $viewer = $this->getViewer();

    $revisions = id(new DifferentialRevisionQuery())
      ->setViewer($viewer)
      ->withAuthors(array($user->getPHID()))
      ->needFlags(true)
      ->needDrafts(true)
      ->needReviewers(true)
      ->setLimit(100)
      ->execute();

    $list = id(new DifferentialRevisionListView())
      ->setUser($viewer)
      ->setNoBox(true)
      ->setRevisions($revisions)
      ->setNoDataString(pht('No recent revisions.'));

    $object_phids = $list->getRequiredHandlePHIDs();
    $handles = $this->loadViewerHandles($object_phids);
    $list->setHandles($handles);

    $view = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Recent Revisions'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($list);

    return $view;
  }
}
