<?php

final class PhabricatorProjectProfilePanelEngine
  extends PhabricatorProfilePanelEngine {

  protected function getPanelURI($path) {
    $project = $this->getProfileObject();
    $id = $project->getID();
    return "/project/{$id}/panel/{$path}";
  }

  protected function getBuiltinProfilePanels($object) {
    $panels = array();

    $panels[] = $this->newPanel()
      ->setBuiltinKey(PhabricatorProject::PANEL_PROFILE)
      ->setPanelKey(PhabricatorProjectDetailsProfilePanel::PANELKEY);

    $panels[] = $this->newPanel()
      ->setBuiltinKey(PhabricatorProject::PANEL_WORKBOARD)
      ->setPanelKey(PhabricatorProjectWorkboardProfilePanel::PANELKEY);

    // TODO: This is temporary.
    $uri = urisprintf(
      '/maniphest/?statuses=open()&projects=%s#R',
      $object->getPHID());

    $panels[] = $this->newPanel()
      ->setBuiltinKey('tasks')
      ->setPanelKey(PhabricatorLinkProfilePanel::PANELKEY)
      ->setPanelProperty('icon', 'maniphest')
      ->setPanelProperty('name', pht('Open Tasks'))
      ->setPanelProperty('uri', $uri);

    $panels[] = $this->newPanel()
      ->setBuiltinKey(PhabricatorProject::PANEL_MEMBERS)
      ->setPanelKey(PhabricatorProjectMembersProfilePanel::PANELKEY);

    return $panels;
  }

}
