<?php

abstract class PhabricatorProjectController extends PhabricatorController {

  private $project;

  protected function setProject(PhabricatorProject $project) {
    $this->project = $project;
    return $this;
  }

  protected function getProject() {
    return $this->project;
  }

  protected function loadProject() {
    $viewer = $this->getViewer();
    $request = $this->getRequest();

    $id = $request->getURIData('id');
    $slug = $request->getURIData('slug');

    if ($slug) {
      $normal_slug = PhabricatorSlug::normalizeProjectSlug($slug);
      $is_abnormal = ($slug !== $normal_slug);
      $normal_uri = "/tag/{$normal_slug}/";
    } else {
      $is_abnormal = false;
    }

    $query = id(new PhabricatorProjectQuery())
      ->setViewer($viewer)
      ->needMembers(true)
      ->needWatchers(true)
      ->needImages(true)
      ->needSlugs(true);

    if ($slug) {
      $query->withSlugs(array($slug));
    } else {
      $query->withIDs(array($id));
    }

    $policy_exception = null;
    try {
      $project = $query->executeOne();
    } catch (PhabricatorPolicyException $ex) {
      $policy_exception = $ex;
      $project = null;
    }

    if (!$project) {
      // This project legitimately does not exist, so just 404 the user.
      if (!$policy_exception) {
        return new Aphront404Response();
      }

      // Here, the project exists but the user can't see it. If they are
      // using a non-canonical slug to view the project, redirect to the
      // canonical slug. If they're already using the canonical slug, rethrow
      // the exception to give them the policy error.
      if ($is_abnormal) {
        return id(new AphrontRedirectResponse())->setURI($normal_uri);
      } else {
        throw $policy_exception;
      }
    }

    // The user can view the project, but is using a noncanonical slug.
    // Redirect to the canonical slug.
    $primary_slug = $project->getPrimarySlug();
    if ($slug && ($slug !== $primary_slug)) {
      $primary_uri = "/tag/{$primary_slug}/";
      return id(new AphrontRedirectResponse())->setURI($primary_uri);
    }

    $this->setProject($project);

    return null;
  }

  public function buildApplicationMenu() {
    return $this->buildSideNavView(true)->getMenu();
  }

  public function buildSideNavView($for_app = false) {
    $project = $this->getProject();

    $nav = new AphrontSideNavFilterView();
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));

    $viewer = $this->getViewer();

    $id = null;
    if ($for_app) {
      if ($project) {
        $id = $project->getID();
        $nav->addFilter("profile/{$id}/", pht('Profile'));
        $nav->addFilter("board/{$id}/", pht('Workboard'));
        $nav->addFilter("members/{$id}/", pht('Members'));
        $nav->addFilter("feed/{$id}/", pht('Feed'));
      }
      $nav->addFilter('create', pht('Create Project'));
    }

    if (!$id) {
      id(new PhabricatorProjectSearchEngine())
        ->setViewer($viewer)
        ->addNavigationItems($nav->getMenu());
    }

    $nav->selectFilter(null);

    return $nav;
  }

  public function buildIconNavView(PhabricatorProject $project) {
    $this->setProject($project);
    $viewer = $this->getViewer();
    $id = $project->getID();
    $picture = $project->getProfileImageURI();
    $name = $project->getName();

    $columns = id(new PhabricatorProjectColumnQuery())
      ->setViewer($viewer)
      ->withProjectPHIDs(array($project->getPHID()))
      ->execute();
    if ($columns) {
      $board_icon = 'fa-columns';
    } else {
      $board_icon = 'fa-columns grey';
    }

    $nav = new AphrontSideNavFilterView();
    $nav->setIconNav(true);
    $nav->setBaseURI(new PhutilURI($this->getApplicationURI()));
    $nav->addIcon("profile/{$id}/", $name, null, $picture);

    $class = 'PhabricatorManiphestApplication';
    if (PhabricatorApplication::isClassInstalledForViewer($class, $viewer)) {
      $phid = $project->getPHID();
      $nav->addIcon("board/{$id}/", pht('Workboard'), $board_icon);
      $query_uri = urisprintf(
        '/maniphest/?statuses=open()&projects=%s#R',
        $phid);
      $nav->addIcon(null, pht('Open Tasks'), 'fa-anchor', null, $query_uri);
    }

    $nav->addIcon("feed/{$id}/", pht('Feed'), 'fa-newspaper-o');
    $nav->addIcon("members/{$id}/", pht('Members'), 'fa-group');

    if (false && PhabricatorEnv::getEnvConfig('phabricator.show-prototypes')) {
      if ($project->supportsSubprojects()) {
        $subprojects_icon = 'fa-sitemap';
      } else {
        $subprojects_icon = 'fa-sitemap grey';
      }

      $key = PhabricatorProjectIconSet::getMilestoneIconKey();
      $milestones_icon = PhabricatorProjectIconSet::getIconIcon($key);
      if (!$project->supportsMilestones()) {
        $milestones_icon = "{$milestones_icon} grey";
      }

      $nav->addIcon(
        "subprojects/{$id}/",
        pht('Subprojects'),
        $subprojects_icon);

      $nav->addIcon(
        "milestones/{$id}/",
        pht('Milestones'),
        $milestones_icon);
    }

    return $nav;
  }

  protected function buildApplicationCrumbs() {
    $crumbs = parent::buildApplicationCrumbs();

    $project = $this->getProject();
    if ($project) {
      $ancestors = $project->getAncestorProjects();
      $ancestors = array_reverse($ancestors);
      $ancestors[] = $project;
      foreach ($ancestors as $ancestor) {
        $crumbs->addTextCrumb(
          $ancestor->getName(),
          $ancestor->getURI());
      }
    }

    return $crumbs;
  }

}
