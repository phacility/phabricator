<?php

abstract class PhabricatorSettingsPanelGroup extends Phobject {

  private $panels;

  abstract public function getPanelGroupName();

  protected function getPanelGroupOrder() {
    return 1000;
  }

  final public function getPanelGroupOrderVector() {
    return id(new PhutilSortVector())
      ->addInt($this->getPanelGroupOrder())
      ->addString($this->getPanelGroupName());
  }

  final public function getPanelGroupKey() {
    return $this->getPhobjectClassConstant('PANELGROUPKEY');
  }

  final public static function getAllPanelGroups() {
    $groups = id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getPanelGroupKey')
      ->execute();

    return msortv($groups, 'getPanelGroupOrderVector');
  }

  final public static function getAllPanelGroupsWithPanels() {
    $groups = self::getAllPanelGroups();

    $panels = PhabricatorSettingsPanel::getAllPanels();
    $panels = mgroup($panels, 'getPanelGroupKey');
    foreach ($groups as $key => $group) {
      $group->panels = idx($panels, $key, array());
    }

    return $groups;
  }

  public function getPanels() {
    return $this->panels;
  }

}
