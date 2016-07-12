<?php

abstract class PhabricatorIconSet
  extends Phobject {

  final public function getIconSetKey() {
    return $this->getPhobjectClassConstant('ICONSETKEY');
  }

  public function getChooseButtonText() {
    return pht('Choose Icon...');
  }

  public function getSelectIconTitleText() {
    return pht('Choose Icon');
  }

  public function getSelectURI() {
    $key = $this->getIconSetKey();
    return "/file/iconset/{$key}/select/";
  }

  final public function getIcons() {
    $icons = $this->newIcons();

    // TODO: Validate icons.
    $icons = mpull($icons, null, 'getKey');

    return $icons;
  }

  final public function getIcon($key) {
    $icons = $this->getIcons();
    return idx($icons, $key);
  }

  final public function getIconLabel($key) {
    $icon = $this->getIcon($key);

    if ($icon) {
      return $icon->getLabel();
    }

    return $key;
  }

  final public function renderIconForControl(PhabricatorIconSetIcon $icon) {
    return phutil_tag(
      'span',
      array(),
      array(
        id(new PHUIIconView())->setIcon($icon->getIcon()),
        ' ',
        $icon->getLabel(),
      ));
  }

  final public static function getIconSetByKey($key) {
    $sets = self::getAllIconSets();
    return idx($sets, $key);
  }

  final public static function getAllIconSets() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getIconSetKey')
      ->execute();
  }

}
