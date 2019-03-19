<?php

abstract class PhabricatorProjectColumnOrder
  extends Phobject {

  private $viewer;

  final public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  final public function getViewer() {
    return $this->viewer;
  }

  final public function getColumnOrderKey() {
    return $this->getPhobjectClassConstant('ORDERKEY');
  }

  final public static function getAllOrders() {
    return id(new PhutilClassMapQuery())
      ->setAncestorClass(__CLASS__)
      ->setUniqueMethod('getColumnOrderKey')
      ->setSortMethod('getMenuOrder')
      ->execute();
  }

  final public static function getEnabledOrders() {
    $map = self::getAllOrders();

    foreach ($map as $key => $order) {
      if (!$order->isEnabled()) {
        unset($map[$key]);
      }
    }

    return $map;
  }

  final public static function getOrderByKey($key) {
    $map = self::getAllOrders();

    if (!isset($map[$key])) {
      throw new Exception(
        pht(
          'No column ordering exists with key "%s".',
          $key));
    }

    return $map[$key];
  }

  final public function getColumnTransactions($object, array $header) {
    $result = $this->newColumnTransactions($object, $header);

    if (!is_array($result) && !is_null($result)) {
      throw new Exception(
        pht(
          'Expected "newColumnTransactions()" on "%s" to return "null" or a '.
          'list of transactions, but got "%s".',
          get_class($this),
          phutil_describe_type($result)));
    }

    if ($result === null) {
      $result = array();
    }

    assert_instances_of($result, 'PhabricatorApplicationTransaction');

    return $result;
  }

  final public function getMenuIconIcon() {
    return $this->newMenuIconIcon();
  }

  protected function newMenuIconIcon() {
    return 'fa-sort-amount-asc';
  }

  abstract public function getDisplayName();
  abstract public function getHasHeaders();
  abstract public function getCanReorder();

  public function getMenuOrder() {
    return 9000;
  }

  public function isEnabled() {
    return true;
  }

  protected function newColumnTransactions($object, array $header) {
    return array();
  }

  final public function getHeadersForObjects(array $objects) {
    $headers = $this->newHeadersForObjects($objects);

    if (!is_array($headers)) {
      throw new Exception(
        pht(
          'Expected "newHeadersForObjects()" on "%s" to return a list '.
          'of headers, but got "%s".',
          get_class($this),
          phutil_describe_type($headers)));
    }

    assert_instances_of($headers, 'PhabricatorProjectColumnHeader');

    // Add a "0" to the end of each header. This makes them sort above object
    // cards in the same group.
    foreach ($headers as $header) {
      $vector = $header->getSortVector();
      $vector[] = 0;
      $header->setSortVector($vector);
    }

    return $headers;
  }

  protected function newHeadersForObjects(array $objects) {
    return array();
  }

  final public function getSortVectorsForObjects(array $objects) {
    $vectors = $this->newSortVectorsForObjects($objects);

    if (!is_array($vectors)) {
      throw new Exception(
        pht(
          'Expected "newSortVectorsForObjects()" on "%s" to return a '.
          'map of vectors, but got "%s".',
          get_class($this),
          phutil_describe_type($vectors)));
    }

    assert_same_keys($objects, $vectors);

    return $vectors;
  }

  protected function newSortVectorsForObjects(array $objects) {
    $vectors = array();

    foreach ($objects as $key => $object) {
      $vectors[$key] = $this->newSortVectorForObject($object);
    }

    return $vectors;
  }

  protected function newSortVectorForObject($object) {
    return array();
  }

  final public function getHeaderKeysForObjects(array $objects) {
    $header_keys = $this->newHeaderKeysForObjects($objects);

    if (!is_array($header_keys)) {
      throw new Exception(
        pht(
          'Expected "newHeaderKeysForObject()" on "%s" to return a '.
          'map of header keys, but got "%s".',
          get_class($this),
          phutil_describe_type($header_keys)));
    }

    assert_same_keys($objects, $header_keys);

    return $header_keys;
  }

  protected function newHeaderKeysForObjects(array $objects) {
    $header_keys = array();

    foreach ($objects as $key => $object) {
      $header_keys[$key] = $this->newHeaderKeyForObject($object);
    }

    return $header_keys;
  }

  protected function newHeaderKeyForObject($object) {
    return null;
  }

  final protected function newTransaction($object) {
    return $object->getApplicationTransactionTemplate();
  }

  final protected function newHeader() {
    return id(new PhabricatorProjectColumnHeader())
      ->setOrderKey($this->getColumnOrderKey());
  }

  final protected function newEffect() {
    return new PhabricatorProjectDropEffect();
  }

  final public function toDictionary() {
    return array(
      'orderKey' => $this->getColumnOrderKey(),
      'hasHeaders' => $this->getHasHeaders(),
      'canReorder' => $this->getCanReorder(),
    );
  }

}
