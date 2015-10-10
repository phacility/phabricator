<?php

final class PhabricatorStandardCustomFieldBlueprints
  extends PhabricatorStandardCustomFieldTokenizer {

  public function getFieldType() {
    return 'blueprints';
  }

  public function getDatasource() {
    return new DrydockBlueprintDatasource();
  }

  public function applyApplicationTransactionExternalEffects(
    PhabricatorApplicationTransaction $xaction) {

    $object_phid = $xaction->getObjectPHID();

    $old = $this->decodeValue($xaction->getOldValue());
    $new = $this->decodeValue($xaction->getNewValue());

    $old_phids = array_fuse($old);
    $new_phids = array_fuse($new);

    $rem_phids = array_diff_key($old_phids, $new_phids);
    $add_phids = array_diff_key($new_phids, $old_phids);

    $altered_phids = $rem_phids + $add_phids;

    if (!$altered_phids) {
      return;
    }

    $authorizations = id(new DrydockAuthorizationQuery())
      ->setViewer(PhabricatorUser::getOmnipotentUser())
      ->withObjectPHIDs(array($object_phid))
      ->withBlueprintPHIDs($altered_phids)
      ->execute();
    $authorizations = mpull($authorizations, null, 'getBlueprintPHID');

    $state_active = DrydockAuthorization::OBJECTAUTH_ACTIVE;
    $state_inactive = DrydockAuthorization::OBJECTAUTH_INACTIVE;

    $state_requested = DrydockAuthorization::BLUEPRINTAUTH_REQUESTED;

    // Disable the object side of the authorization for any existing
    // authorizations.
    foreach ($rem_phids as $rem_phid) {
      $authorization = idx($authorizations, $rem_phid);
      if (!$authorization) {
        continue;
      }

      $authorization
        ->setObjectAuthorizationState($state_inactive)
        ->save();
    }

    // For new authorizations, either add them or reactivate them depending
    // on the current state.
    foreach ($add_phids as $add_phid) {
      $needs_update = false;

      $authorization = idx($authorizations, $add_phid);
      if (!$authorization) {
        $authorization = id(new DrydockAuthorization())
          ->setObjectPHID($object_phid)
          ->setObjectAuthorizationState($state_active)
          ->setBlueprintPHID($add_phid)
          ->setBlueprintAuthorizationState($state_requested);

        $needs_update = true;
      } else {
        $current_state = $authorization->getObjectAuthorizationState();
        if ($current_state != $state_active) {
          $authorization->setObjectAuthorizationState($state_active);
          $needs_update = true;
        }
      }

      if ($needs_update) {
        $authorization->save();
      }
    }

  }

  public function renderPropertyViewValue(array $handles) {
    $value = $this->getFieldValue();
    if (!$value) {
      return phutil_tag('em', array(), pht('No authorized blueprints.'));
    }

    $object = $this->getObject();
    $object_phid = $object->getPHID();

    // NOTE: We're intentionally letting you see the authorization state on
    // blueprints you can't see because this has a tremendous potential to
    // be extremely confusing otherwise. You still can't see the blueprints
    // themselves, but you can know if the object is authorized on something.

    if ($value) {
      $handles = $this->getViewer()->loadHandles($value);

      $authorizations = id(new DrydockAuthorizationQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->withObjectPHIDs(array($object_phid))
        ->withBlueprintPHIDs($value)
        ->execute();
      $authorizations = mpull($authorizations, null, 'getBlueprintPHID');
    } else {
      $handles = array();
      $authorizations = array();
    }

    $items = array();
    foreach ($value as $phid) {
      $authorization = idx($authorizations, $phid);
      if (!$authorization) {
        continue;
      }

      $handle = $handles[$phid];

      $item = id(new PHUIStatusItemView())
        ->setTarget($handle->renderLink());

      $state = $authorization->getBlueprintAuthorizationState();
      $item->setIcon(
        DrydockAuthorization::getBlueprintStateIcon($state),
        null,
        DrydockAuthorization::getBlueprintStateName($state));

      $items[] = $item;
    }

    $status = new PHUIStatusListView();
    foreach ($items as $item) {
      $status->addItem($item);
    }

    return $status;
  }



}
