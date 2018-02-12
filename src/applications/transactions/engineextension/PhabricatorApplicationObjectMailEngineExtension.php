<?php

final class PhabricatorApplicationObjectMailEngineExtension
  extends PhabricatorMailEngineExtension {

  const EXTENSIONKEY = 'application/object';

  public function supportsObject($object) {
    return true;
  }

  public function newMailStampTemplates($object) {
    $templates = array(
      id(new PhabricatorStringMailStamp())
        ->setKey('application')
        ->setLabel(pht('Application')),
    );

    if ($this->hasMonogram($object)) {
      $templates[] = id(new PhabricatorStringMailStamp())
        ->setKey('monogram')
        ->setLabel(pht('Object Monogram'));
    }

    if ($this->hasPHID($object)) {
      // This is a PHID, but we always want to render it as a raw string, so
      // use a string mail stamp.
      $templates[] = id(new PhabricatorStringMailStamp())
        ->setKey('phid')
        ->setLabel(pht('Object PHID'));

      $templates[] = id(new PhabricatorStringMailStamp())
        ->setKey('object-type')
        ->setLabel(pht('Object Type'));
    }

    return $templates;
  }

  public function newMailStamps($object, array $xactions) {
    $editor = $this->getEditor();
    $viewer = $this->getViewer();

    $application = null;
    $class = $editor->getEditorApplicationClass();
    if (PhabricatorApplication::isClassInstalledForViewer($class, $viewer)) {
      $application = newv($class, array());
    }

    if ($application) {
      $application_name = $application->getName();
      $this->getMailStamp('application')
        ->setValue($application_name);
    }

    if ($this->hasMonogram($object)) {
      $monogram = $object->getMonogram();
      $this->getMailStamp('monogram')
        ->setValue($monogram);
    }

    if ($this->hasPHID($object)) {
      $object_phid = $object->getPHID();

      $this->getMailStamp('phid')
        ->setValue($object_phid);

      $phid_type = phid_get_type($object_phid);
      if ($phid_type != PhabricatorPHIDConstants::PHID_TYPE_UNKNOWN) {
        $this->getMailStamp('object-type')
          ->setValue($phid_type);
      }
    }
  }

  private function hasPHID($object) {
    if (!($object instanceof LiskDAO)) {
      return false;
    }

    if (!$object->getConfigOption(LiskDAO::CONFIG_AUX_PHID)) {
      return false;
    }

    return true;
  }

  private function hasMonogram($object) {
    return method_exists($object, 'getMonogram');
  }

}
