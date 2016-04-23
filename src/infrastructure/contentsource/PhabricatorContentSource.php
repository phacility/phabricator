<?php

abstract class PhabricatorContentSource extends Phobject {

  private $source;
  private $params = array();

  abstract public function getSourceName();
  abstract public function getSourceDescription();

  final public function getSourceTypeConstant() {
    return $this->getPhobjectClassConstant('SOURCECONST', 32);
  }

  final public static function getAllContentSources() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getSourceTypeConstant')
      ->execute();
  }

  /**
   * Construct a new content source object.
   *
   * @param const The source type constant to build a source for.
   * @param array Source parameters.
   * @param bool True to suppress errors and force construction of a source
   *   even if the source type is not valid.
   * @return PhabricatorContentSource New source object.
   */
  final public static function newForSource(
    $source,
    array $params = array(),
    $force = false) {

    $map = self::getAllContentSources();
    if (isset($map[$source])) {
      $obj = clone $map[$source];
    } else {
      if ($force) {
        $obj = new PhabricatorUnknownContentSource();
      } else {
        throw new Exception(
          pht(
            'Content source type "%s" is not known to Phabricator!',
            $source));
      }
    }

    $obj->source = $source;
    $obj->params = $params;

    return $obj;
  }

  public static function newFromSerialized($serialized) {
    $dict = json_decode($serialized, true);
    if (!is_array($dict)) {
      $dict = array();
    }

    $source = idx($dict, 'source');
    $params = idx($dict, 'params');
    if (!is_array($params)) {
      $params = array();
    }

    return self::newForSource($source, $params, true);
  }

  public static function newFromRequest(AphrontRequest $request) {
    return self::newForSource(
      PhabricatorWebContentSource::SOURCECONST);
  }

  final public function serialize() {
    return phutil_json_encode(
      array(
        'source' => $this->getSource(),
        'params' => $this->params,
      ));
  }

  final public function getSource() {
    return $this->source;
  }

  final public function getContentSourceParameter($key, $default = null) {
    return idx($this->params, $key, $default);
  }

}
