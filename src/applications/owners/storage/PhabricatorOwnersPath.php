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

  /**
   * Get the number of directory matches between this path specification and
   * some real path.
   */
  public function getPathMatchStrength($path) {
    $this_path = $this->getPath();

    if ($this_path === '/') {
      // The root path "/" just matches everything with strength 1.
      return 1;
    }

    $self_fragments = PhabricatorOwnersPackage::splitPath($this_path);
    $path_fragments = PhabricatorOwnersPackage::splitPath($path);

    $self_count = count($self_fragments);
    $path_count = count($path_fragments);
    if ($self_count > $path_count) {
      // If this path is longer (and therefor more specific) than the target
      // path, we don't match it at all.
      return 0;
    }

    for ($ii = 0; $ii < $self_count; $ii++) {
      if ($self_fragments[$ii] != $path_fragments[$ii]) {
        return 0;
      }
    }

    return $self_count;
  }

}
