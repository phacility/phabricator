<?php

final class PhabricatorProjectProfileMenuEngine
  extends PhabricatorProfileMenuEngine {

  protected function isMenuEngineConfigurable() {
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
      ->setMenuItemKey(PhabricatorProjectDetailsProfilePanel::PANELKEY);

    $panels[] = $this->newPanel()
      ->setBuiltinKey(PhabricatorProject::PANEL_POINTS)
      ->setMenuItemKey(PhabricatorProjectPointsProfilePanel::PANELKEY);

    $panels[] = $this->newPanel()
      ->setBuiltinKey(PhabricatorProject::PANEL_WORKBOARD)
      ->setMenuItemKey(PhabricatorProjectWorkboardProfilePanel::PANELKEY);

    $panels[] = $this->newPanel()
      ->setBuiltinKey(PhabricatorProject::PANEL_MEMBERS)
      ->setMenuItemKey(PhabricatorProjectMembersProfilePanel::PANELKEY);

    $panels[] = $this->newPanel()
      ->setBuiltinKey(PhabricatorProject::PANEL_SUBPROJECTS)
      ->setMenuItemKey(PhabricatorProjectSubprojectsProfilePanel::PANELKEY);

    $panels[] = $this->newPanel()
      ->setBuiltinKey(PhabricatorProject::PANEL_MANAGE)
      ->setMenuItemKey(PhabricatorProjectManageProfilePanel::PANELKEY);

    return $panels;
  }

}
