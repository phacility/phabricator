<?php

final class PhabricatorFeedStoryData
  extends PhabricatorFeedDAO
  implements PhabricatorDestructibleInterface {

  protected $phid;

  protected $storyType;
  protected $storyData;
  protected $authorPHID;
  protected $chronologicalKey;

  protected function getConfiguration() {
    return array(
      self::CONFIG_AUX_PHID       => true,
      self::CONFIG_SERIALIZATION  => array(
        'storyData'  => self::SERIALIZATION_JSON,
      ),
      self::CONFIG_COLUMN_SCHEMA => array(
        'chronologicalKey' => 'uint64',
        'storyType' => 'text64',
      ),
      self::CONFIG_KEY_SCHEMA => array(
        'key_phid' => null,
        'phid' => array(
          'columns' => array('phid'),
          'unique' => true,
        ),
        'chronologicalKey' => array(
          'columns' => array('chronologicalKey'),
          'unique' => true,
        ),
      ),
    ) + parent::getConfiguration();
  }

  public function generatePHID() {
    return PhabricatorPHID::generateNewPHID(
      PhabricatorPHIDConstants::PHID_TYPE_STRY);
  }

  public function getEpoch() {
    if (PHP_INT_SIZE < 8) {
      // We're on a 32-bit machine.
      if (function_exists('bcadd')) {
        // Try to use the 'bc' extension.
        return bcdiv($this->chronologicalKey, bcpow(2, 32));
      } else {
        // Do the math in MySQL. TODO: If we formalize a bc dependency, get
        // rid of this.
        // See: PhabricatorFeedStoryPublisher::generateChronologicalKey()
        $conn_r = id($this->establishConnection('r'));
        $result = queryfx_one(
          $conn_r,
          // Insert the chronologicalKey as a string since longs don't seem to
          // be supported by qsprintf and ints get maxed on 32 bit machines.
          'SELECT (%s >> 32) as N',
          $this->chronologicalKey);
        return $result['N'];
      }
    } else {
      return $this->chronologicalKey >> 32;
    }
  }

  public function getValue($key, $default = null) {
    return idx($this->storyData, $key, $default);
  }


/* -(  PhabricatorDestructibleInterface  )----------------------------------- */


  public function destroyObjectPermanently(
    PhabricatorDestructionEngine $engine) {

    $this->openTransaction();
      $conn = $this->establishConnection('w');

      queryfx(
        $conn,
        'DELETE FROM %T WHERE chronologicalKey = %s',
        id(new PhabricatorFeedStoryNotification())->getTableName(),
        $this->getChronologicalKey());

      queryfx(
        $conn,
        'DELETE FROM %T WHERE chronologicalKey = %s',
        id(new PhabricatorFeedStoryReference())->getTableName(),
        $this->getChronologicalKey());

      $this->delete();
    $this->saveTransaction();
  }

}
