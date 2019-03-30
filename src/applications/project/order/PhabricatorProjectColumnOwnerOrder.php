<?php

final class PhabricatorProjectColumnOwnerOrder
  extends PhabricatorProjectColumnOrder {

  const ORDERKEY = 'owner';

  public function getDisplayName() {
    return pht('Group by Owner');
  }

  protected function newMenuIconIcon() {
    return 'fa-users';
  }

  public function getHasHeaders() {
    return true;
  }

  public function getCanReorder() {
    return true;
  }

  public function getMenuOrder() {
    return 2000;
  }

  protected function newHeaderKeyForObject($object) {
    return $this->newHeaderKeyForOwnerPHID($object->getOwnerPHID());
  }

  private function newHeaderKeyForOwnerPHID($owner_phid) {
    if ($owner_phid === null) {
      $owner_phid = '<null>';
    }

    return sprintf('owner(%s)', $owner_phid);
  }

  protected function newSortVectorsForObjects(array $objects) {
    $owner_phids = mpull($objects, null, 'getOwnerPHID');
    $owner_phids = array_keys($owner_phids);
    $owner_phids = array_filter($owner_phids);

    if ($owner_phids) {
      $owner_users = id(new PhabricatorPeopleQuery())
        ->setViewer($this->getViewer())
        ->withPHIDs($owner_phids)
        ->execute();
      $owner_users = mpull($owner_users, null, 'getPHID');
    } else {
      $owner_users = array();
    }

    $vectors = array();
    foreach ($objects as $vector_key => $object) {
      $owner_phid = $object->getOwnerPHID();
      if (!$owner_phid) {
        $vector = $this->newSortVectorForUnowned();
      } else {
        $owner = idx($owner_users, $owner_phid);
        if ($owner) {
          $vector = $this->newSortVectorForOwner($owner);
        } else {
          $vector = $this->newSortVectorForOwnerPHID($owner_phid);
        }
      }

      $vectors[$vector_key] = $vector;
    }

    return $vectors;
  }

  private function newSortVectorForUnowned() {
    // Always put unasssigned tasks at the top.
    return array(
      0,
    );
  }

  private function newSortVectorForOwner(PhabricatorUser $user) {
    // Put assigned tasks with a valid owner after "Unassigned", but above
    // assigned tasks with an invalid owner. Sort these tasks by the owner's
    // username.
    return array(
      1,
      $user->getUsername(),
    );
  }

  private function newSortVectorForOwnerPHID($owner_phid) {
    // If we have tasks with a nonempty owner but can't load the associated
    // "User" object, move them to the bottom. We can only sort these by the
    // PHID.
    return array(
      2,
      $owner_phid,
    );
  }

  protected function newHeadersForObjects(array $objects) {
    $owner_phids = mpull($objects, null, 'getOwnerPHID');
    $owner_phids = array_keys($owner_phids);
    $owner_phids = array_filter($owner_phids);

    if ($owner_phids) {
      $owner_users = id(new PhabricatorPeopleQuery())
        ->setViewer($this->getViewer())
        ->withPHIDs($owner_phids)
        ->needProfileImage(true)
        ->execute();
      $owner_users = mpull($owner_users, null, 'getPHID');
    } else {
      $owner_users = array();
    }

    array_unshift($owner_phids, null);

    $headers = array();
    foreach ($owner_phids as $owner_phid) {
      $header_key = $this->newHeaderKeyForOwnerPHID($owner_phid);

      $owner_image = null;
      $effect_content = null;
      if ($owner_phid === null) {
        $owner = null;
        $sort_vector = $this->newSortVectorForUnowned();
        $owner_name = pht('Not Assigned');

        $effect_content = pht('Remove task assignee.');
      } else {
        $owner = idx($owner_users, $owner_phid);
        if ($owner) {
          $sort_vector = $this->newSortVectorForOwner($owner);
          $owner_name = $owner->getUsername();
          $owner_image = $owner->getProfileImageURI();

          $effect_content = pht(
            'Assign task to %s.',
            phutil_tag('strong', array(), $owner_name));
        } else {
          $sort_vector = $this->newSortVectorForOwnerPHID($owner_phid);
          $owner_name = pht('Unknown User ("%s")', $owner_phid);
        }
      }

      $owner_icon = 'fa-user';
      $owner_color = 'bluegrey';

      $icon_view = id(new PHUIIconView());

      if ($owner_image) {
        $icon_view->setImage($owner_image);
      } else {
        $icon_view->setIcon($owner_icon, $owner_color);
      }

      $header = $this->newHeader()
        ->setHeaderKey($header_key)
        ->setSortVector($sort_vector)
        ->setName($owner_name)
        ->setIcon($icon_view)
        ->setEditProperties(
          array(
            'value' => $owner_phid,
          ));

      if ($effect_content !== null) {
        $header->addDropEffect(
          $this->newEffect()
            ->setIcon($owner_icon)
            ->setColor($owner_color)
            ->addCondition('owner', '!=', $owner_phid)
            ->setContent($effect_content));
      }

      $headers[] = $header;
    }

    return $headers;
  }

  protected function newColumnTransactions($object, array $header) {
    $new_owner = idx($header, 'value');

    if ($object->getOwnerPHID() === $new_owner) {
      return null;
    }

    $xactions = array();
    $xactions[] = $this->newTransaction($object)
      ->setTransactionType(ManiphestTaskOwnerTransaction::TRANSACTIONTYPE)
      ->setNewValue($new_owner);

    return $xactions;
  }

}
