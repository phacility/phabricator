<?php

abstract class PhabricatorProjectController extends PhabricatorController {

  public function buildStandardPageResponse($view, array $data) {
    $page = $this->buildStandardPageView();

    $page->setApplicationName('Project');
    $page->setBaseURI('/project/');
    $page->setTitle(idx($data, 'title'));
    $page->setGlyph("\xE2\x98\xA3");
    $page->appendChild($view);

    $response = new AphrontWebpageResponse();
    return $response->setContent($page->render());
  }

  protected function buildLocalNavigation(PhabricatorProject $project) {
    $id = $project->getID();

    $nav_view = new AphrontSideNavFilterView();
    $uri = new PhutilURI('/project/view/'.$id.'/');
    $nav_view->setBaseURI($uri);

    $external_arrow = "\xE2\x86\x97";
    $tasks_uri = '/maniphest/view/all/?projects='.$project->getPHID();
    $slug = PhabricatorSlug::normalize($project->getName());
    $phriction_uri = '/w/projects/'.$slug;

    $edit_uri = '/project/edit/'.$id.'/';
    $members_uri = '/project/members/'.$id.'/';

    $nav_view->addFilter('dashboard', 'Dashboard');
    $nav_view->addSpacer();
    $nav_view->addFilter('feed', 'Feed');
    $nav_view->addFilter(null, 'Tasks '.$external_arrow, $tasks_uri);
    $nav_view->addFilter(null, 'Wiki '.$external_arrow, $phriction_uri);
    $nav_view->addFilter('people', 'People');
    $nav_view->addFilter('about', 'About');

    $user = $this->getRequest()->getUser();
    $can_edit = PhabricatorPolicyCapability::CAN_EDIT;

    $nav_view->addSpacer();
    if (PhabricatorPolicyFilter::hasCapability($user, $project, $can_edit)) {
      $nav_view->addFilter('edit', "Edit Project\xE2\x80\xA6", $edit_uri);
      $nav_view->addFilter('members', "Edit Members\xE2\x80\xA6", $members_uri);
    } else {
      $nav_view->addFilter(
        'edit',
        "Edit Project\xE2\x80\xA6",
        $edit_uri,
        $relative = false,
        'disabled');
      $nav_view->addFilter(
        'members',
        "Edit Members\xE2\x80\xA6",
        $members_uri,
        $relative = false,
        'disabled');
    }

    return $nav_view;
  }

}
