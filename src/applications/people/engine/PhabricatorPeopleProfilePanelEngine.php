<?php

final class PhabricatorPeopleProfilePanelEngine
  extends PhabricatorProfilePanelEngine {

  const PANEL_PROFILE = 'people.profile';
  const PANEL_MANAGE = 'people.manage';

  protected function isPanelEngineConfigurable() {
    return false;
  }

  protected function getPanelURI($path) {
    $user = $this->getProfileObject();
    $username = $user->getUsername();
    $username = phutil_escape_uri($username);
    return "/p/{$username}/panel/{$path}";
  }

  protected function getBuiltinProfilePanels($object) {
    $viewer = $this->getViewer();

    $panels = array();

    $panels[] = $this->newPanel()
      ->setBuiltinKey(self::PANEL_PROFILE)
      ->setPanelKey(PhabricatorPeopleDetailsProfilePanel::PANELKEY);

    $have_maniphest = PhabricatorApplication::isClassInstalledForViewer(
      'PhabricatorManiphestApplication',
      $viewer);
    if ($have_maniphest) {
      $uri = urisprintf(
        '/maniphest/?statuses=open()&assigned=%s#R',
        $object->getPHID());

      $panels[] = $this->newPanel()
        ->setBuiltinKey('tasks')
        ->setPanelKey(PhabricatorLinkProfilePanel::PANELKEY)
        ->setPanelProperty('icon', 'maniphest')
        ->setPanelProperty('name', pht('Open Tasks'))
        ->setPanelProperty('uri', $uri);
    }

    $have_differential = PhabricatorApplication::isClassInstalledForViewer(
      'PhabricatorDifferentialApplication',
      $viewer);
    if ($have_differential) {
      $uri = urisprintf(
        '/differential/?authors=%s#R',
        $object->getPHID());

      $panels[] = $this->newPanel()
        ->setBuiltinKey('revisions')
        ->setPanelKey(PhabricatorLinkProfilePanel::PANELKEY)
        ->setPanelProperty('icon', 'differential')
        ->setPanelProperty('name', pht('Revisions'))
        ->setPanelProperty('uri', $uri);
    }

    $have_diffusion = PhabricatorApplication::isClassInstalledForViewer(
      'PhabricatorDiffusionApplication',
      $viewer);
    if ($have_diffusion) {
      $uri = urisprintf(
        '/audit/?authors=%s#R',
        $object->getPHID());

      $panels[] = $this->newPanel()
        ->setBuiltinKey('commits')
        ->setPanelKey(PhabricatorLinkProfilePanel::PANELKEY)
        ->setPanelProperty('icon', 'diffusion')
        ->setPanelProperty('name', pht('Commits'))
        ->setPanelProperty('uri', $uri);
    }

    $panels[] = $this->newPanel()
      ->setBuiltinKey(self::PANEL_MANAGE)
      ->setPanelKey(PhabricatorPeopleManageProfilePanel::PANELKEY);

    return $panels;
  }

}
