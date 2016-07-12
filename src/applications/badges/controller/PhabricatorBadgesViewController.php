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
    $crumbs->setBorder(true);
    $title = $badge->getName();

    if ($badge->isArchived()) {
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
      ->setStatus($status_icon, $status_color, $status_name)
      ->setHeaderIcon('fa-trophy');

    $curtain = $this->buildCurtain($badge);
    $details = $this->buildDetailsView($badge);

    $timeline = $this->buildTransactionTimeline(
      $badge,
      new PhabricatorBadgesTransactionQuery());

    $awards = $badge->getAwards();
    $recipient_phids = mpull($awards, 'getRecipientPHID');
    $recipient_phids = array_reverse($recipient_phids);
    $handles = $this->loadViewerHandles($recipient_phids);

    $recipient_list = id(new PhabricatorBadgesRecipientsListView())
      ->setBadge($badge)
      ->setHandles($handles)
      ->setUser($viewer);

    $comment_view = id(new PhabricatorBadgesEditEngine())
      ->setViewer($viewer)
      ->buildEditEngineCommentView($badge);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->setMainColumn(array(
          $recipient_list,
          $timeline,
          $comment_view,
        ))
      ->addPropertySection(pht('Description'), $details);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->setPageObjectPHIDs(array($badge->getPHID()))
      ->appendChild($view);
  }

  private function buildDetailsView(
    PhabricatorBadgesBadge $badge) {
    $viewer = $this->getViewer();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer);

    $description = $badge->getDescription();
    if (strlen($description)) {
      $view->addTextContent(
        new PHUIRemarkupView($viewer, $description));
    }

    $badge = id(new PHUIBadgeView())
      ->setIcon($badge->getIcon())
      ->setHeader($badge->getName())
      ->setSubhead($badge->getFlavor())
      ->setQuality($badge->getQuality());

    $view->addTextContent($badge);

    return $view;
  }

  private function buildCurtain(PhabricatorBadgesBadge $badge) {
    $viewer = $this->getViewer();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $badge,
      PhabricatorPolicyCapability::CAN_EDIT);

    $id = $badge->getID();
    $edit_uri = $this->getApplicationURI("/edit/{$id}/");
    $archive_uri = $this->getApplicationURI("/archive/{$id}/");
    $award_uri = $this->getApplicationURI("/recipients/{$id}/");

    $curtain = $this->newCurtainView($badge);

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Badge'))
        ->setIcon('fa-pencil')
        ->setDisabled(!$can_edit)
        ->setHref($edit_uri));

    if ($badge->isArchived()) {
      $curtain->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Activate Badge'))
          ->setIcon('fa-check')
          ->setDisabled(!$can_edit)
          ->setWorkflow($can_edit)
          ->setHref($archive_uri));
    } else {
      $curtain->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Archive Badge'))
          ->setIcon('fa-ban')
          ->setDisabled(!$can_edit)
          ->setWorkflow($can_edit)
          ->setHref($archive_uri));
    }

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName('Add Recipients')
        ->setIcon('fa-users')
        ->setDisabled(!$can_edit)
        ->setWorkflow(true)
        ->setHref($award_uri));

    return $curtain;
  }

}
