<?php

abstract class PhabricatorTestDataGenerator extends Phobject {

  private $viewer;

  abstract public function getGeneratorName();
  abstract public function generateObject();

  final public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  final public function getViewer() {
    return $this->viewer;
  }

  protected function loadRandomPHID($table) {
    $conn_r = $table->establishConnection('r');

    $row = queryfx_one(
      $conn_r,
      'SELECT phid FROM %T ORDER BY RAND() LIMIT 1',
      $table->getTableName());

    if (!$row) {
      return null;
    }

    return $row['phid'];
  }

  protected function loadRandomUser() {
    $viewer = $this->getViewer();

    $user_phid = $this->loadRandomPHID(new PhabricatorUser());

    $user = null;
    if ($user_phid) {
      $user = id(new PhabricatorPeopleQuery())
        ->setViewer($viewer)
        ->withPHIDs(array($user_phid))
        ->executeOne();
    }

    if (!$user) {
      throw new Exception(
        pht(
          'Failed to load a random user. You may need to generate more '.
          'test users first.'));
    }

    return $user;
  }

  protected function getLipsumContentSource() {
    return PhabricatorContentSource::newForSource(
      PhabricatorLipsumContentSource::SOURCECONST);
  }

  /**
   * Roll `n` dice with `d` sides each, then add `bonus` and return the sum.
   */
  protected function roll($n, $d, $bonus = 0) {
    $sum = 0;
    for ($ii = 0; $ii < $n; $ii++) {
      $sum += mt_rand(1, $d);
    }

    $sum += $bonus;

    return $sum;
  }

  protected function newEmptyTransaction() {
    throw new PhutilMethodNotImplementedException();
  }

  protected function newTransaction($type, $value, $metadata = array()) {
    $xaction = $this->newEmptyTransaction()
      ->setTransactionType($type)
      ->setNewValue($value);

    foreach ($metadata as $key => $value) {
      $xaction->setMetadataValue($key, $value);
    }

    return $xaction;
  }




  public function loadOneRandom($classname) {
    try {
      return newv($classname, array())
        ->loadOneWhere('1 = 1 ORDER BY RAND() LIMIT 1');
    } catch (PhutilMissingSymbolException $ex) {
      throw new PhutilMissingSymbolException(
        pht(
          'Unable to load symbol %s: this class does not exit.',
          $classname));
    }
  }

  public function loadPhabrictorUserPHID() {
    return $this->loadOneRandom('PhabricatorUser')->getPHID();
  }

  public function loadPhabrictorUser() {
    return $this->loadOneRandom('PhabricatorUser');
  }


}
