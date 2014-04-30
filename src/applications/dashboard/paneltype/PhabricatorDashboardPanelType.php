<?php

abstract class PhabricatorDashboardPanelType extends Phobject {

  abstract public function getPanelTypeKey();
  abstract public function getPanelTypeName();
  abstract public function getPanelTypeDescription();
  abstract public function getFieldSpecifications();

  public static function getAllPanelTypes() {
    static $types;

    if ($types === null) {
      $objects = id(new PhutilSymbolLoader())
        ->setAncestorClass(__CLASS__)
        ->loadObjects();

      $map = array();
      foreach ($objects as $object) {
        $key = $object->getPanelTypeKey();
        if (!empty($map[$key])) {
          $this_class = get_class($object);
          $that_class = get_class($map[$key]);
          throw new Exception(
            pht(
              'Two dashboard panels (of classes "%s" and "%s") have the '.
              'same panel type key ("%s"). Each panel type must have a '.
              'unique panel type key.',
              $this_class,
              $that_class,
              $key));
        }

        $map[$key] = $object;
      }

      $types = $map;
    }

    return $types;
  }

  public function renderPanel(
    PhabricatorUser $viewer,
    PhabricatorDashboardPanel $panel) {

    $content = $this->renderPanelContent($viewer, $panel);

    return id(new PHUIObjectBoxView())
      ->setHeaderText($panel->getName())
      ->appendChild($content);
  }

  protected function renderPanelContent(
    PhabricatorUser $viewer,
    PhabricatorDashboardPanel $panel) {
    return pht('TODO: Panel content goes here.');
  }

  public function shouldRenderAsync() {
    // TODO: For now, just make these things random so we can catch anything
    // that breaks.
    return (mt_rand(0, 1) == 1);
  }

}
