<?php

final class PhabricatorOwnersPackageOwnersTransaction
  extends PhabricatorOwnersPackageTransactionType {

  const TRANSACTIONTYPE = 'owners.owners';

  public function generateOldValue($object) {
    $phids = mpull($object->getOwners(), 'getUserPHID');
    $phids = array_values($phids);
    return $phids;
  }

  public function generateNewValue($object, $value) {
    $phids = array_unique($value);
    $phids = array_values($phids);
    return $phids;
  }

  public function applyExternalEffects($object, $value) {
    $old = $this->generateOldValue($object);
    $new = $value;

    $owners = $object->getOwners();
    $owners = mpull($owners, null, 'getUserPHID');

    $rem = array_diff($old, $new);
    foreach ($rem as $phid) {
      if (isset($owners[$phid])) {
        $owners[$phid]->delete();
        unset($owners[$phid]);
      }
    }

    $add = array_diff($new, $old);
    foreach ($add as $phid) {
      $owners[$phid] = id(new PhabricatorOwnersOwner())
        ->setPackageID($object->getID())
        ->setUserPHID($phid)
        ->save();
    }

    // TODO: Attach owners here
  }

  public function getTitle() {
    $old = $this->getOldValue();
    $new = $this->getNewValue();

    $add = array_diff($new, $old);
    $rem = array_diff($old, $new);
    if ($add && !$rem) {
      return pht(
        '%s added %s owner(s): %s.',
        $this->renderAuthor(),
        count($add),
        $this->renderHandleList($add));
    } else if ($rem && !$add) {
      return pht(
        '%s removed %s owner(s): %s.',
        $this->renderAuthor(),
        count($rem),
        $this->renderHandleList($rem));
    } else {
      return pht(
        '%s changed %s package owner(s), added %s: %s; removed %s: %s.',
        $this->renderAuthor(),
        count($add) + count($rem),
        count($add),
        $this->renderHandleList($add),
        count($rem),
        $this->renderHandleList($rem));
    }
  }

}
