<?php

final class PhabricatorSubscriptionsListController
  extends PhabricatorController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getUser();
    $object = id(new PhabricatorObjectQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($request->getURIData('phid')))
      ->executeOne();
    if (!$object) {
      return new Aphront404Response();
    }

    if (!($object instanceof PhabricatorSubscribableInterface)) {
      return new Aphront404Response();
    }

    $phid = $object->getPHID();

    $handle_phids = PhabricatorSubscribersQuery::loadSubscribersForPHID($phid);
    $handle_phids[] = $phid;

    $handles = id(new PhabricatorHandleQuery())
      ->setViewer($viewer)
      ->withPHIDs($handle_phids)
      ->execute();
    $object_handle = $handles[$phid];

    $dialog = id(new SubscriptionListDialogBuilder())
      ->setViewer($viewer)
      ->setTitle(pht('Subscribers'))
      ->setObjectPHID($phid)
      ->setHandles($handles)
      ->buildDialog();

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
