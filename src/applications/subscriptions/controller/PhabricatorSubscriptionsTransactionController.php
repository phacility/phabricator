<?php

final class PhabricatorSubscriptionsTransactionController
  extends PhabricatorController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $phid = $request->getURIData('phid');
    $type = $request->getURIData('type');

    $xaction = id(new PhabricatorObjectQuery())
      ->withPHIDs(array($phid))
      ->setViewer($viewer)
      ->executeOne();
    if (!$xaction) {
      return new Aphront404Response();
    }

    $old = $xaction->getOldValue();
    $new = $xaction->getNewValue();
    switch ($type) {
      case 'add':
        $subscriber_phids = array_diff($new, $old);
        break;
      case 'rem':
        $subscriber_phids = array_diff($old, $new);
        break;
      default:
        return id(new Aphront404Response());
    }

    $object_phid = $xaction->getObjectPHID();
    $author_phid = $xaction->getAuthorPHID();
    $handle_phids = $subscriber_phids;
    $handle_phids[] = $object_phid;
    $handle_phids[] = $author_phid;

    $handles = id(new PhabricatorHandleQuery())
      ->setViewer($viewer)
      ->withPHIDs($handle_phids)
      ->execute();
    $author_handle = $handles[$author_phid];
    if (!in_array($author_phid, $subscriber_phids)) {
      unset($handles[$author_phid]);
    }

    switch ($type) {
      case 'add':
        $title = pht(
          'All %d subscribers added by %s',
          count($subscriber_phids),
          $author_handle->renderLink());
        break;
      case 'rem':
        $title = pht(
          'All %d subscribers removed by %s',
          count($subscriber_phids),
          $author_handle->renderLink());
        break;
    }

    $dialog = id(new SubscriptionListDialogBuilder())
      ->setViewer($viewer)
      ->setTitle($title)
      ->setObjectPHID($object_phid)
      ->setHandles($handles)
      ->buildDialog();

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }

}
