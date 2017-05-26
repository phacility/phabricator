<?php

abstract class PhabricatorProjectController extends PhabricatorController {

  private $project;
  private $profileMenu;
  private $profileMenuEngine;

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

    $id = nonempty(
      $request->getURIData('projectID'),
      $request->getURIData('id'));

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
    $menu = $this->newApplicationMenu();

    $profile_menu = $this->getProfileMenu();
    if ($profile_menu) {
      $menu->setProfileMenu($profile_menu);
    }

    $menu->setSearchEngine(new PhabricatorProjectSearchEngine());

    return $menu;
  }

  protected function getProfileMenu() {
    if (!$this->profileMenu) {
      $engine = $this->getProfileMenuEngine();
      if ($engine) {
        $this->profileMenu = $engine->buildNavigation();
      }
    }

    return $this->profileMenu;
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
          $ancestor->getProfileURI()
        );
      }
    }

    return $crumbs;
  }

  protected function getProfileMenuEngine() {
    if (!$this->profileMenuEngine) {
      $viewer = $this->getViewer();
      $project = $this->getProject();
      if ($project) {
        $engine = id(new PhabricatorProjectProfileMenuEngine())
          ->setViewer($viewer)
          ->setController($this)
          ->setProfileObject($project);
        $this->profileMenuEngine = $engine;
      }
    }

    return $this->profileMenuEngine;
  }

  protected function setProfileMenuEngine(
    PhabricatorProjectProfileMenuEngine $engine) {
    $this->profileMenuEngine = $engine;
    return $this;
  }

  protected function newCardResponse($board_phid, $object_phid) {
    $viewer = $this->getViewer();

    $request = $this->getRequest();
    $visible_phids = $request->getStrList('visiblePHIDs');
    if (!$visible_phids) {
      $visible_phids = array();
    }

    return id(new PhabricatorBoardResponseEngine())
      ->setViewer($viewer)
      ->setBoardPHID($board_phid)
      ->setObjectPHID($object_phid)
      ->setVisiblePHIDs($visible_phids)
      ->buildResponse();
  }

  public function renderHashtags(array $tags) {
    $result = array();
    foreach ($tags as $key => $tag) {
      $result[] = '#'.$tag;
    }
    return implode(', ', $result);
  }

}
