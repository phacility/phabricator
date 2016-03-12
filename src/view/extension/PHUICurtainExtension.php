<?php

abstract class PHUICurtainExtension extends Phobject {

  private $viewer;

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  abstract public function shouldEnableForObject($object);
  abstract public function getExtensionApplication();

  public function buildCurtainPanels($object) {
    $panel = $this->buildCurtainPanel($object);

    if ($panel !== null) {
      return array($panel);
    }

    return array();
  }

  public function buildCurtainPanel($object) {
    throw new PhutilMethodNotImplementedException();
  }

  final public function getExtensionKey() {
    return $this->getPhobjectClassConstant('EXTENSIONKEY');
  }

  final public static function getAllExtensions() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getExtensionKey')
      ->execute();
  }

  protected function newPanel() {
    return new PHUICurtainPanelView();
  }

  final public static function buildExtensionPanels(
    PhabricatorUser $viewer,
    $object) {

    $extensions = self::getAllExtensions();
    foreach ($extensions as $extension) {
      $extension->setViewer($viewer);
    }

    foreach ($extensions as $key => $extension) {
      $application = $extension->getExtensionApplication();
      if (!($application instanceof PhabricatorApplication)) {
        throw new Exception(
          pht(
            'Curtain extension ("%s", of class "%s") did not return an '.
            'application from method "%s". This method must return an '.
            'object of class "%s".',
            $key,
            get_class($extension),
            'getExtensionApplication()',
            'PhabricatorApplication'));
      }

      $has_application = PhabricatorApplication::isClassInstalledForViewer(
        get_class($application),
        $viewer);

      if (!$has_application) {
        unset($extensions[$key]);
      }
    }

    foreach ($extensions as $key => $extension) {
      if (!$extension->shouldEnableForObject($object)) {
        unset($extensions[$key]);
      }
    }

    $result = array();

    foreach ($extensions as $key => $extension) {
      $panels = $extension->buildCurtainPanels($object);
      if (!is_array($panels)) {
        throw new Exception(
          pht(
            'Curtain extension ("%s", of class "%s") did not return a list of '.
            'curtain panels from method "%s". This method must return an '.
            'array, and each value in the array must be a "%s" object.',
            $key,
            get_class($extension),
            'buildCurtainPanels()',
            'PHUICurtainPanelView'));
      }

      foreach ($panels as $panel_key => $panel) {
        if (!($panel instanceof PHUICurtainPanelView)) {
          throw new Exception(
            pht(
              'Curtain extension ("%s", of class "%s") returned a list of '.
              'curtain panels from "%s" that contains an invalid value: '.
              'a value (with key "%s") is not an object of class "%s". '.
              'Each item in the returned array must be a panel.',
              $key,
              get_class($extension),
              'buildCurtainPanels()',
              $panel_key,
              'PHUICurtainPanelView'));
        }

        $result[] = $panel;
      }
    }

    return $result;
  }

}
