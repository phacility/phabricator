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
    return $this->loadProjectWithCapabilities(
      array(
        PhabricatorPolicyCapability::CAN_VIEW,
      ));
  }

  protected function loadProjectForEdit() {
    return $this->loadProjectWithCapabilities(
      array(
        PhabricatorPolicyCapability::CAN_VIEW,
        PhabricatorPolicyCapability::CAN_EDIT,
      ));
  }

  private function loadProjectWithCapabilities(array $capabilities) {
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
      ->requireCapabilities($capabilities)
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

  protected function buildApplicationCrumbs() {
    return $this->newApplicationCrumbs('profile');
  }

  protected function newWorkboardCrumbs() {
    return $this->newApplicationCrumbs('workboard');
  }

  private function newApplicationCrumbs($mode) {
    $crumbs = parent::buildApplicationCrumbs();

    $project = $this->getProject();
    if ($project) {
      $ancestors = $project->getAncestorProjects();
      $ancestors = array_reverse($ancestors);
      $ancestors[] = $project;
      foreach ($ancestors as $ancestor) {
        if ($ancestor->getPHID() === $project->getPHID()) {
          // Link the current project's crumb to its profile no matter what,
          // since we're already on the right context page for it and linking
          // to the current page isn't helpful.
          $crumb_uri = $ancestor->getProfileURI();
        } else {
          switch ($mode) {
            case 'workboard':
              if ($ancestor->getHasWorkboard()) {
                $crumb_uri = $ancestor->getWorkboardURI();
              } else {
                $crumb_uri = $ancestor->getProfileURI();
              }
              break;
            case 'profile':
            default:
              $crumb_uri = $ancestor->getProfileURI();
              break;
          }
        }

        $crumbs->addTextCrumb($ancestor->getName(), $crumb_uri);
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

  protected function newCardResponse(
    $board_phid,
    $object_phid,
    PhabricatorProjectColumnOrder $ordering = null,
    $sounds = array()) {

    $viewer = $this->getViewer();

    $request = $this->getRequest();
    $visible_phids = $request->getStrList('visiblePHIDs');
    if (!$visible_phids) {
      $visible_phids = array();
    }

    $engine = id(new PhabricatorBoardResponseEngine())
      ->setViewer($viewer)
      ->setBoardPHID($board_phid)
      ->setUpdatePHIDs(array($object_phid))
      ->setVisiblePHIDs($visible_phids)
      ->setSounds($sounds);

    if ($ordering) {
      $engine->setOrdering($ordering);
    }

    return $engine->buildResponse();
  }

  public function renderHashtags(array $tags) {
    $result = array();
    foreach ($tags as $key => $tag) {
      $result[] = '#'.$tag;
    }
    return implode(', ', $result);
  }

  final protected function newNavigation(
    PhabricatorProject $project,
    $item_identifier) {

    $engine = $this->getProfileMenuEngine();

    $view_list = $engine->newProfileMenuItemViewList();

    // See PHI1247. If the "Workboard" item is removed from the menu, we will
    // not be able to select it. This can happen if a user removes the item,
    // then manually navigate to the workboard URI (or follows an older link).
    // In this case, just render the menu with no selected item.
    if ($view_list->getViewsWithItemIdentifier($item_identifier)) {
      $view_list->setSelectedViewWithItemIdentifier($item_identifier);
    }

    $navigation = $view_list->newNavigationView();

    if ($item_identifier === PhabricatorProject::ITEM_WORKBOARD) {
      $navigation->addClass('project-board-nav');
    }

    return $navigation;
  }

}
