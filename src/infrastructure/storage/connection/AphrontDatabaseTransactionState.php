<?php

/**
 * Represents current transaction state of a connection.
 */
final class AphrontDatabaseTransactionState extends Phobject {

  private $depth           = 0;
  private $readLockLevel   = 0;
  private $writeLockLevel  = 0;

  public function getDepth() {
    return $this->depth;
  }

  public function increaseDepth() {
    return ++$this->depth;
  }

  public function decreaseDepth() {
    if ($this->depth == 0) {
      throw new Exception(
        pht(
          'Too many calls to %s or %s!',
          'saveTransaction()',
          'killTransaction()'));
    }

    return --$this->depth;
  }

  public function getSavepointName() {
    return 'Aphront_Savepoint_'.$this->depth;
  }

  public function beginReadLocking() {
    $this->readLockLevel++;
    return $this;
  }

  public function endReadLocking() {
    if ($this->readLockLevel == 0) {
      throw new Exception(
        pht(
          'Too many calls to %s!',
          __FUNCTION__.'()'));
    }
    $this->readLockLevel--;
    return $this;
  }

  public function isReadLocking() {
    return ($this->readLockLevel > 0);
  }

  public function beginWriteLocking() {
    $this->writeLockLevel++;
    return $this;
  }

  public function endWriteLocking() {
    if ($this->writeLockLevel == 0) {
      throw new Exception(
        pht(
          'Too many calls to %s!',
          __FUNCTION__.'()'));
    }
    $this->writeLockLevel--;
    return $this;
  }

  public function isWriteLocking() {
    return ($this->writeLockLevel > 0);
  }

  public function __destruct() {
    if ($this->depth) {
      throw new Exception(
        pht(
          'Process exited with an open transaction! The transaction '.
          'will be implicitly rolled back. Calls to %s must always be '.
          'paired with a call to %s or %s.',
          'openTransaction()',
          'saveTransaction()',
          'killTransaction()'));
    }
    if ($this->readLockLevel) {
      throw new Exception(
        pht(
          'Process exited with an open read lock! Call to %s '.
          'must always be paired with a call to %s.',
          'beginReadLocking()',
          'endReadLocking()'));
    }
    if ($this->writeLockLevel) {
      throw new Exception(
        pht(
          'Process exited with an open write lock! Call to %s '.
          'must always be paired with a call to %s.',
          'beginWriteLocking()',
          'endWriteLocking()'));
    }
  }

}
