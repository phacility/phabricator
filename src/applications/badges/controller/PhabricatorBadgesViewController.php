<?php

final class PhabricatorBadgesViewController
  extends PhabricatorBadgesController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $badge = id(new PhabricatorBadgesQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->needRecipients(true)
      ->executeOne();
    if (!$badge) {
      return new Aphront404Response();
    }

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($badge->getName());
    $title = $badge->getName();

    if ($badge->isClosed()) {
      $status_icon = 'fa-ban';
      $status_color = 'dark';
    } else {
      $status_icon = 'fa-check';
      $status_color = 'bluegrey';
    }
    $status_name = idx(
      PhabricatorBadgesBadge::getStatusNameMap(),
      $badge->getStatus());

    $header = id(new PHUIHeaderView())
      ->setHeader($badge->getName())
      ->setUser($viewer)
      ->setPolicyObject($badge)
      ->setStatus($status_icon, $status_color, $status_name);

    $properties = $this->buildPropertyListView($badge);
    $actions = $this->buildActionListView($badge);
    $properties->setActionList($actions);

    $box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    $timeline = $this->buildTransactionTimeline(
      $badge,
      new PhabricatorBadgesTransactionQuery());
    $timeline
      ->setShouldTerminate(true);

    $recipient_phids = $badge->getRecipientPHIDs();
    $recipient_phids = array_reverse($recipient_phids);
    $handles = $this->loadViewerHandles($recipient_phids);

    $recipient_list = id(new PhabricatorBadgesRecipientsListView())
      ->setBadge($badge)
      ->setHandles($handles)
      ->setUser($viewer);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
        $recipient_list,
        $timeline,
      ),
      array(
        'title' => $title,
        'pageObjects' => array($badge->getPHID()),
      ));
  }

  private function buildPropertyListView(PhabricatorBadgesBadge $badge) {
    $viewer = $this->getViewer();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($badge);

    $quality = idx($badge->getQualityNameMap(), $badge->getQuality());
    $icon = idx($badge->getIconNameMap(), $badge->getIcon());

    $view->addProperty(
      pht('Quality'),
      $quality);

    $view->addProperty(
      pht('Icon'),
      $icon);

    $view->addProperty(
      pht('Flavor'),
      $badge->getFlavor());

    $view->invokeWillRenderEvent();

    $description = $badge->getDescription();
    if (strlen($description)) {
      $description = PhabricatorMarkupEngine::renderOneObject(
        id(new PhabricatorMarkupOneOff())->setContent($description),
        'default',
        $viewer);

      $view->addSectionHeader(pht('Description'));
      $view->addTextContent($description);
    }

    $badge = id(new PHUIBadgeView())
      ->setIcon($badge->getIcon())
      ->setHeader($badge->getName())
      ->setSubhead($badge->getFlavor())
      ->setQuality($badge->getQuality());

    $view->addTextContent($badge);

    return $view;
  }

  private function buildActionListView(PhabricatorBadgesBadge $badge) {
    $viewer = $this->getViewer();
    $id = $badge->getID();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $badge,
      PhabricatorPolicyCapability::CAN_EDIT);

    $view = id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->setObject($badge);

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Badge'))
        ->setIcon('fa-pencil')
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit)
        ->setHref($this->getApplicationURI("/edit/{$id}/")));

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName('Manage Recipients')
        ->setIcon('fa-users')
        ->setDisabled(!$can_edit)
        ->setHref($this->getApplicationURI("/recipients/{$id}/")));

    return $view;
  }

}
