<?php

abstract class PhabricatorRoleController extends PhabricatorController {

  private $role;
  private $profileMenu;
  private $profileMenuEngine;

  protected function setRole(PhabricatorRole $role) {
    $this->role = $role;
    return $this;
  }

  protected function getRole() {
    return $this->role;
  }

  protected function loadRole() {
    return $this->loadRoleWithCapabilities(
      array(
        PhabricatorPolicyCapability::CAN_VIEW,
      ));
  }

  protected function loadRoleForEdit() {
    return $this->loadRoleWithCapabilities(
      array(
        PhabricatorPolicyCapability::CAN_VIEW,
        PhabricatorPolicyCapability::CAN_EDIT,
      ));
  }

  private function loadRoleWithCapabilities(array $capabilities) {
    $viewer = $this->getViewer();
    $request = $this->getRequest();

    $id = nonempty(
      $request->getURIData('roleID'),
      $request->getURIData('id'));

    $slug = $request->getURIData('slug');

    if ($slug) {
      $normal_slug = PhabricatorSlug::normalizeProjectSlug($slug);
      $is_abnormal = ($slug !== $normal_slug);
      $normal_uri = "/tag/{$normal_slug}/";
    } else {
      $is_abnormal = false;
    }

    $query = id(new PhabricatorRoleQuery())
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
      $role = $query->executeOne();
    } catch (PhabricatorPolicyException $ex) {
      $policy_exception = $ex;
      $role = null;
    }

    if (!$role) {
      // This role legitimately does not exist, so just 404 the user.
      if (!$policy_exception) {
        return new Aphront404Response();
      }

      // Here, the role exists but the user can't see it. If they are
      // using a non-canonical slug to view the role, redirect to the
      // canonical slug. If they're already using the canonical slug, rethrow
      // the exception to give them the policy error.
      if ($is_abnormal) {
        return id(new AphrontRedirectResponse())->setURI($normal_uri);
      } else {
        throw $policy_exception;
      }
    }

    // The user can view the role, but is using a noncanonical slug.
    // Redirect to the canonical slug.
    $primary_slug = $role->getPrimarySlug();
    if ($slug && ($slug !== $primary_slug)) {
      $primary_uri = "/tag/{$primary_slug}/";
      return id(new AphrontRedirectResponse())->setURI($primary_uri);
    }

    $this->setRole($role);

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

    $role = $this->getRole();
    if ($role) {
      $ancestors = $role->getAncestorRoles();
      $ancestors = array_reverse($ancestors);
      $ancestors[] = $role;
      foreach ($ancestors as $ancestor) {
        if ($ancestor->getPHID() === $role->getPHID()) {
          // Link the current role's crumb to its profile no matter what,
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
      $role = $this->getRole();
      if ($role) {
        $engine = id(new PhabricatorRoleProfileMenuEngine())
          ->setViewer($viewer)
          ->setController($this)
          ->setProfileObject($role);
        $this->profileMenuEngine = $engine;
      }
    }

    return $this->profileMenuEngine;
  }

  protected function setProfileMenuEngine(
    PhabricatorRoleProfileMenuEngine $engine) {
    $this->profileMenuEngine = $engine;
    return $this;
  }

  protected function newCardResponse(
    $board_phid,
    $object_phid,
    PhabricatorRoleColumnOrder $ordering = null,
    $sounds = array()) {

    $viewer = $this->getViewer();

    $request = $this->getRequest();
    $visible_phids = $request->getStrList('visiblePHIDs');
    if (!$visible_phids) {
      $visible_phids = array();
    }

    $engine = id(new PhabricatorRoleBoardResponseEngine())
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
    PhabricatorRole $role,
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

    if ($item_identifier === PhabricatorRole::ITEM_WORKBOARD) {
      $navigation->addClass('role-board-nav');
    }

    return $navigation;
  }

}
