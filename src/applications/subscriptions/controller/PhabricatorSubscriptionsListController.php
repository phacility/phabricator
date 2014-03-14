<?php

final class PhabricatorSubscriptionsListController
  extends PhabricatorController {

  private $phid;

  public function willProcessRequest(array $data) {
    $this->phid = idx($data, 'phid');
  }

  public function processRequest() {
    $request = $this->getRequest();

    $viewer = $request->getUser();
    $phid = $this->phid;

    $object = id(new PhabricatorObjectQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($phid))
      ->executeOne();

    if ($object instanceof PhabricatorSubscribableInterface) {
      $subscriber_phids = PhabricatorSubscribersQuery::loadSubscribersForPHID(
        $phid);
    } else if ($object instanceof ManiphestTask) {
      $subscriber_phids = $object->getCCPHIDs();
    }

    $handle_phids = $subscriber_phids;
    $handle_phids[] = $phid;

    $handles = id(new PhabricatorHandleQuery())
      ->setViewer($viewer)
      ->withPHIDs($handle_phids)
      ->execute();
    $object_handle = $handles[$phid];

    $dialog = id(new SubscriptionListDialogBuilder())
      ->setViewer($viewer)
      ->setTitle(pht('Subscribers for %s', $object_handle->getFullName()))
      ->setObjectPHID($phid)
      ->setHandles($handles)
      ->buildDialog();

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
