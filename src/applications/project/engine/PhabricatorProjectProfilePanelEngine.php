<?php

final class PhabricatorProjectProfilePanelEngine
  extends PhabricatorProfilePanelEngine {

  protected function isPanelEngineConfigurable() {
    return true;
  }

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
      ->setBuiltinKey(PhabricatorProject::PANEL_POINTS)
      ->setPanelKey(PhabricatorProjectPointsProfilePanel::PANELKEY);

    $panels[] = $this->newPanel()
      ->setBuiltinKey(PhabricatorProject::PANEL_WORKBOARD)
      ->setPanelKey(PhabricatorProjectWorkboardProfilePanel::PANELKEY);

    $panels[] = $this->newPanel()
      ->setBuiltinKey(PhabricatorProject::PANEL_MEMBERS)
      ->setPanelKey(PhabricatorProjectMembersProfilePanel::PANELKEY);

    $panels[] = $this->newPanel()
      ->setBuiltinKey(PhabricatorProject::PANEL_SUBPROJECTS)
      ->setPanelKey(PhabricatorProjectSubprojectsProfilePanel::PANELKEY);

    $panels[] = $this->newPanel()
      ->setBuiltinKey(PhabricatorProject::PANEL_MANAGE)
      ->setPanelKey(PhabricatorProjectManageProfilePanel::PANELKEY);

    return $panels;
  }

}
