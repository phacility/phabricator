<?php

final class PhabricatorBadgesRecipientsListView extends AphrontTagView {

  private $badge;
  private $handles;

  public function setBadge(PhabricatorBadgesBadge $badge) {
    $this->badge = $badge;
    return $this;
  }

  public function setHandles(array $handles) {
    $this->handles = $handles;
    return $this;
  }

  protected function getTagContent() {

    $viewer = $this->user;

    $badge = $this->badge;
    $handles = $this->handles;

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $badge,
      PhabricatorPolicyCapability::CAN_EDIT);

    $list = id(new PHUIObjectItemListView())
      ->setNoDataString(pht('This badge does not have any recipients.'));

    foreach ($handles as $handle) {
      $remove_uri = '/badges/recipients/'.
        $badge->getID().'/remove/?phid='.$handle->getPHID();

      $item = id(new PHUIObjectItemView())
        ->setHeader($handle->getFullName())
        ->setHref($handle->getURI())
        ->setImageURI($handle->getImageURI());

      if ($can_edit) {
        $item->addAction(
          id(new PHUIListItemView())
            ->setIcon('fa-times')
            ->setName(pht('Remove'))
            ->setHref($remove_uri)
            ->setWorkflow(true));
      }

      $list->addItem($item);
    }

    $box = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Recipients'))
      ->setObjectList($list);

    return $box;
  }

}
