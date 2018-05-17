<?php

abstract class PhabricatorTransactionFactEngine
  extends PhabricatorFactEngine {

  public function newTransactionGroupsForObject(PhabricatorLiskDAO $object) {
    $viewer = $this->getViewer();

    $xaction_query = PhabricatorApplicationTransactionQuery::newQueryForObject(
      $object);
    $xactions = $xaction_query
      ->setViewer($viewer)
      ->withObjectPHIDs(array($object->getPHID()))
      ->execute();

    $xactions = msortv($xactions, 'newChronologicalSortVector');

    return $this->groupTransactions($xactions);
  }

  protected function groupTransactions(array $xactions) {
    // These grouping rules are generally much looser than the display grouping
    // rules. As long as the same user is editing the task and they don't leave
    // it alone for a particularly long time, we'll group things together.

    $breaks = array();

    $touch_window = phutil_units('15 minutes in seconds');
    $user_type = PhabricatorPeopleUserPHIDType::TYPECONST;

    $last_actor = null;
    $last_epoch = null;

    foreach ($xactions as $key => $xaction) {
      $this_actor = $xaction->getAuthorPHID();
      if (phid_get_type($this_actor) != $user_type) {
        $this_actor = null;
      }

      if ($this_actor && $last_actor && ($this_actor != $last_actor)) {
        $breaks[$key] = true;
      }

      // If too much time passed between changes, group them separately.
      $this_epoch = $xaction->getDateCreated();
      if ($last_epoch) {
        if (($this_epoch - $last_epoch) > $touch_window) {
          $breaks[$key] = true;
        }
      }

      // The clock gets reset every time the same real user touches the
      // task, but does not reset if an automated actor touches things.
      if (!$last_actor || ($this_actor == $last_actor)) {
        $last_epoch = $this_epoch;
      }

      if ($this_actor && ($last_actor != $this_actor)) {
        $last_actor = $this_actor;
        $last_epoch = $this_epoch;
      }
    }

    $groups = array();
    $group = array();
    foreach ($xactions as $key => $xaction) {
      if (isset($breaks[$key])) {
        if ($group) {
          $groups[] = $group;
          $group = array();
        }
      }

      $group[] = $xaction;
    }

    if ($group) {
      $groups[] = $group;
    }

    return $groups;
  }

}
