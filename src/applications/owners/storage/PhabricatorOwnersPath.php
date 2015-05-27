<?php

final class PhabricatorOwnersPath extends PhabricatorOwnersDAO {

  protected $packageID;
  protected $repositoryPHID;
  protected $path;
  protected $excluded;

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_COLUMN_SCHEMA => array(
        'path' => 'text255',
        'excluded' => 'bool',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'packageID' => array(
          'columns' => array('packageID'),
        ),
      ),
    ) + parent::getConfiguration();
  }


  public static function newFromRef(array $ref) {
    $path = new PhabricatorOwnersPath();
    $path->repositoryPHID = $ref['repositoryPHID'];
    $path->path = $ref['path'];
    $path->excluded = $ref['excluded'];
    return $path;
  }

  public function getRef() {
    return array(
      'repositoryPHID' => $this->getRepositoryPHID(),
      'path' => $this->getPath(),
      'excluded' => (int)$this->getExcluded(),
    );
  }

  public static function getTransactionValueChanges(array $old, array $new) {
    return array(
      self::getTransactionValueDiff($old, $new),
      self::getTransactionValueDiff($new, $old),
    );
  }

  private static function getTransactionValueDiff(array $u, array $v) {
    $set = self::getSetFromTransactionValue($v);

    foreach ($u as $key => $ref) {
      if (self::isRefInSet($ref, $set)) {
        unset($u[$key]);
      }
    }

    return $u;
  }

  public static function getSetFromTransactionValue(array $v) {
    $set = array();
    foreach ($v as $ref) {
      $set[$ref['repositoryPHID']][$ref['path']][$ref['excluded']] = true;
    }
    return $set;
  }

  public static function isRefInSet(array $ref, array $set) {
    return isset($set[$ref['repositoryPHID']][$ref['path']][$ref['excluded']]);
  }

}
