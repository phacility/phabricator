<?php

final class DrydockObjectAuthorizationView extends AphrontView {

  private $objectPHID;
  private $blueprintPHIDs;

  public function setObjectPHID($object_phid) {
    $this->objectPHID = $object_phid;
    return $this;
  }

  public function getObjectPHID() {
    return $this->objectPHID;
  }

  public function setBlueprintPHIDs(array $blueprint_phids) {
    $this->blueprintPHIDs = $blueprint_phids;
    return $this;
  }

  public function getBlueprintPHIDs() {
    return $this->blueprintPHIDs;
  }

  public function render() {
    $viewer = $this->getUser();
    $blueprint_phids = $this->getBlueprintPHIDs();
    $object_phid = $this->getObjectPHID();

    // NOTE: We're intentionally letting you see the authorization state on
    // blueprints you can't see because this has a tremendous potential to
    // be extremely confusing otherwise. You still can't see the blueprints
    // themselves, but you can know if the object is authorized on something.

    if ($blueprint_phids) {
      $handles = $viewer->loadHandles($blueprint_phids);

      $authorizations = id(new DrydockAuthorizationQuery())
        ->setViewer(PhabricatorUser::getOmnipotentUser())
        ->withObjectPHIDs(array($object_phid))
        ->withBlueprintPHIDs($blueprint_phids)
        ->execute();
      $authorizations = mpull($authorizations, null, 'getBlueprintPHID');
    } else {
      $handles = array();
      $authorizations = array();
    }

    $warnings = array();
    $items = array();
    foreach ($blueprint_phids as $phid) {
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

      switch ($state) {
        case DrydockAuthorization::BLUEPRINTAUTH_REQUESTED:
        case DrydockAuthorization::BLUEPRINTAUTH_DECLINED:
          $warnings[] = $authorization;
          break;
      }

      $items[] = $item;
    }

    $status = new PHUIStatusListView();

    if ($warnings) {
      $status->addItem(
        id(new PHUIStatusItemView())
          ->setIcon('fa-exclamation-triangle', 'pink')
          ->setTarget(
            pht(
              'WARNING: There are %s unapproved authorization(s)!',
              new PhutilNumber(count($warnings)))));
    }

    foreach ($items as $item) {
      $status->addItem($item);
    }

    return $status;
  }

}
