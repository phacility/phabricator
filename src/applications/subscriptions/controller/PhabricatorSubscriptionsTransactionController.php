<?php

final class PhabricatorSubscriptionsTransactionController
  extends PhabricatorController {

  private $phid;
  private $changeType;

  public function willProcessRequest(array $data) {
    $this->phid = idx($data, 'phid');
    $this->changeType = idx($data, 'type');
  }

  public function processRequest() {
    $request = $this->getRequest();

    $viewer = $request->getUser();
    $xaction_phid = $this->phid;

    $xaction = id(new PhabricatorObjectQuery())
      ->withPHIDs(array($xaction_phid))
      ->setViewer($viewer)
      ->executeOne();
    if (!$xaction) {
      return new Aphront404Response();
    }

    $old = $xaction->getOldValue();
    $new = $xaction->getNewValue();
    switch ($this->changeType) {
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

    switch ($this->changeType) {
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
