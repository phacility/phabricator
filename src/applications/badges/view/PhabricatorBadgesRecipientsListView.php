<?php

final class PhabricatorBadgesRecipientsListView extends AphrontView {

  private $badge;
  private $awards;
  private $handles;

  public function setBadge(PhabricatorBadgesBadge $badge) {
    $this->badge = $badge;
    return $this;
  }

  public function setAwards(array $awards) {
    $this->awards = $awards;
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
    $awards = mpull($this->awards, null, 'getRecipientPHID');

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $badge,
      PhabricatorPolicyCapability::CAN_EDIT);

    $award_button = id(new PHUIButtonView())
      ->setTag('a')
      ->setIcon('fa-plus')
      ->setText(pht('Add Recipents'))
      ->setWorkflow(true)
      ->setDisabled(!$can_edit)
      ->setHref('/badges/recipients/'.$badge->getID().'/add/');

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Recipients'))
      ->addActionLink($award_button);

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
      ->setHeader($header)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setObjectList($list);

    return $box;
  }

}
