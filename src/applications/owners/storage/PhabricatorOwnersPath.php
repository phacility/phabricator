<?php

final class PhabricatorOwnersPath extends PhabricatorOwnersDAO {

  protected $packageID;
  protected $repositoryPHID;
  protected $pathIndex;
  protected $path;
  protected $pathDisplay;
  protected $excluded;

  private $fragments;
  private $fragmentCount;

  protected function getConfiguration() {
    return array(
      self::CONFIG_TIMESTAMPS => false,
      self::CONFIG_COLUMN_SCHEMA => array(
        'path' => 'text',
        'pathDisplay' => 'text',
        'pathIndex' => 'bytes12',
        'excluded' => 'bool',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_path' => array(
          'columns' => array('packageID', 'repositoryPHID', 'pathIndex'),
          'unique' => true,
        ),
        'key_repository' => array(
          'columns' => array('repositoryPHID', 'pathIndex'),
        ),
      ),
    ) + parent::getConfiguration();
  }

  public static function newFromRef(array $ref) {
    $path = new PhabricatorOwnersPath();
    $path->repositoryPHID = $ref['repositoryPHID'];

    $raw_path = $ref['path'];

    $path->pathIndex = PhabricatorHash::digestForIndex($raw_path);
    $path->path = $raw_path;
    $path->pathDisplay = $raw_path;

    $path->excluded = $ref['excluded'];

    return $path;
  }

  public function getRef() {
    return array(
      'repositoryPHID' => $this->getRepositoryPHID(),
      'path' => $this->getPath(),
      'display' => $this->getPathDisplay(),
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
      $key = self::getScalarKeyForRef($ref);
      $set[$key] = true;
    }
    return $set;
  }

  public static function isRefInSet(array $ref, array $set) {
    $key = self::getScalarKeyForRef($ref);
    return isset($set[$key]);
  }

  private static function getScalarKeyForRef(array $ref) {
    // See T13464. When building refs from raw transactions, the path has
    // not been normalized yet and doesn't have a separate "display" path.
    // If the "display" path isn't populated, just use the actual path to
    // build the ref key.

    if (isset($ref['display'])) {
      $display = $ref['display'];
    } else {
      $display = $ref['path'];
    }

    return sprintf(
      'repository=%s path=%s display=%s excluded=%d',
      $ref['repositoryPHID'],
      $ref['path'],
      $display,
      $ref['excluded']);
  }


  /**
   * Get the number of directory matches between this path specification and
   * some real path.
   */
  public function getPathMatchStrength($path_fragments, $path_count) {
    $this_path = $this->path;

    if ($this_path === '/') {
      // The root path "/" just matches everything with strength 1.
      return 1;
    }

    if ($this->fragments === null) {
      $this->fragments = PhabricatorOwnersPackage::splitPath($this_path);
      $this->fragmentCount = count($this->fragments);
    }

    $self_fragments = $this->fragments;
    $self_count = $this->fragmentCount;
    if ($self_count > $path_count) {
      // If this path is longer (and therefore more specific) than the target
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
