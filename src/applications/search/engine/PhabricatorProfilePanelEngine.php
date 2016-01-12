<?php

final class PhabricatorProfilePanelEngine extends Phobject {

  private $viewer;
  private $profileObject;
  private $panels;

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function setProfileObject(
    PhabricatorProfilePanelInterface $profile_object) {
    $this->profileObject = $profile_object;
    return $this;
  }

  public function getProfileObject() {
    return $this->profileObject;
  }

  public function buildNavigation() {
    $nav = id(new AphrontSideNavFilterView())
      ->setIconNav(true)
      ->setBaseURI(new PhutilURI('/project/'));

    $panels = $this->getPanels();

    foreach ($panels as $panel) {
      $items = $panel->buildNavigationMenuItems();
      foreach ($items as $item) {
        $this->validateNavigationMenuItem($item);
      }

      // If the panel produced only a single item which does not otherwise
      // have a key, try to automatically assign it a reasonable key. This
      // makes selecting the correct item simpler.

      if (count($items) == 1) {
        $item = head($items);
        if ($item->getKey() === null) {
          $builtin_key = $panel->getBuiltinKey();
          $panel_phid = $panel->getPHID();
          if ($builtin_key !== null) {
            $item->setKey($builtin_key);
          } else if ($panel_phid !== null) {
            $item->setKey($panel_phid);
          }
        }
      }

      foreach ($items as $item) {
        $nav->addMenuItem($item);
      }
    }

    $nav->selectFilter(null);

    return $nav;
  }

  private function getPanels() {
    if ($this->panels === null) {
      $this->panels = $this->loadPanels();
    }

    return $this->panels;
  }

  private function loadPanels() {
    $viewer = $this->getViewer();

    $panels = $this->loadBuiltinProfilePanels();

    // TODO: Load persisted panels.

    foreach ($panels as $panel) {
      $impl = $panel->getPanel();

      $impl->setViewer($viewer);
    }

    return $panels;
  }

  private function loadBuiltinProfilePanels() {
    $object = $this->getProfileObject();
    $builtins = $object->getBuiltinProfilePanels();

    $panels = PhabricatorProfilePanel::getAllPanels();

    $order = 1;
    $map = array();
    foreach ($builtins as $builtin) {
      $builtin_key = $builtin->getBuiltinKey();

      if (!$builtin_key) {
        throw new Exception(
          pht(
            'Object produced a builtin panel with no builtin panel key! '.
            'Builtin panels must have a unique key.'));
      }

      if (isset($map[$builtin_key])) {
        throw new Exception(
          pht(
            'Object produced two panels with the same builtin key ("%s"). '.
            'Each panel must have a unique builtin key.',
            $builtin_key));
      }

      $panel_key = $builtin->getPanelKey();

      $panel = idx($panels, $panel_key);
      if (!$panel) {
        throw new Exception(
          pht(
            'Builtin panel ("%s") specifies a bad panel key ("%s"); there '.
            'is no corresponding panel implementation available.',
            $builtin_key,
            $panel_key));
      }

      $builtin
        ->attachPanel($panel)
        ->attachProfileObject($object)
        ->setPanelOrder($order);

      $map[$builtin_key] = $builtin;

      $order++;
    }

    return $map;
  }

  private function validateNavigationMenuItem($item) {
    if (!($item instanceof PHUIListItemView)) {
      throw new Exception(
        pht(
          'Expected buildNavigationMenuItems() to return a list of '.
          'PHUIListItemView objects, but got a surprise.'));
    }
  }

}
