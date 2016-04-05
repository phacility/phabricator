<?php

final class PhabricatorBadgesRecipientsListView extends AphrontView {

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

  public function render() {
    $viewer = $this->getViewer();

    $badge = $this->badge;
    $handles = $this->handles;
    $awards = mpull($badge->getAwards(), null, 'getRecipientPHID');

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $badge,
      PhabricatorPolicyCapability::CAN_EDIT);

    $list = id(new PHUIObjectItemListView())
      ->setNoDataString(pht('This badge does not have any recipients.'))
      ->setFlush(true);

    foreach ($handles as $handle) {
      $remove_uri = '/badges/recipients/'.
        $badge->getID().'/remove/?phid='.$handle->getPHID();

      $award = $awards[$handle->getPHID()];
      $awarder_handle = $viewer->renderHandle($award->getAwarderPHID());
      $award_date = phabricator_date($award->getDateCreated(), $viewer);
      $awarder_info = pht(
        'Awarded by %s on %s',
        $awarder_handle->render(),
        $award_date);

      $item = id(new PHUIObjectItemView())
        ->setHeader($handle->getFullName())
        ->setSubhead($awarder_info)
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
      ->setHeaderText(pht('RECIPIENTS'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setObjectList($list);

    return $box;
  }

}
