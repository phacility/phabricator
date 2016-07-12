<?php

abstract class PhabricatorFileTransform extends Phobject {

  abstract public function getTransformName();
  abstract public function getTransformKey();
  abstract public function canApplyTransform(PhabricatorFile $file);
  abstract public function applyTransform(PhabricatorFile $file);

  public function getDefaultTransform(PhabricatorFile $file) {
    return null;
  }

  public function generateTransforms() {
    return array($this);
  }

  public function executeTransform(PhabricatorFile $file) {
    if ($this->canApplyTransform($file)) {
      try {
        return $this->applyTransform($file);
      } catch (Exception $ex) {
        // Ignore.
      }
    }

    return $this->getDefaultTransform($file);
  }

  public static function getAllTransforms() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setExpandMethod('generateTransforms')
      ->setUniqueMethod('getTransformKey')
      ->execute();
  }

  public static function getTransformByKey($key) {
    $all = self::getAllTransforms();

    $xform = idx($all, $key);
    if (!$xform) {
      throw new Exception(
        pht(
          'No file transform with key "%s" exists.',
          $key));
    }

    return $xform;
  }

}
