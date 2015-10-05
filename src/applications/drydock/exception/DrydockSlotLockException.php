<?php

final class DrydockSlotLockException extends Exception {

  private $lockMap;

  public function __construct(array $locks) {
    $this->lockMap = $locks;

    if ($locks) {
      $lock_list = array();
      foreach ($locks as $lock => $owner_phid) {
        $lock_list[] = pht('"%s" (owned by "%s")', $lock, $owner_phid);
      }
      $message = pht(
        'Unable to acquire slot locks: %s.',
        implode(', ', $lock_list));
    } else {
      $message = pht('Unable to acquire slot locks.');
    }

    parent::__construct($message);
  }

  public function getLockMap() {
    return $this->lockMap;
  }

}
