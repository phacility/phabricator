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

    $recipient_phids = $badge->getRecipientPHIDs();
    $recipient_phids = array_reverse($recipient_phids);
    $handles = $this->loadViewerHandles($recipient_phids);

    $recipient_list = id(new PhabricatorBadgesRecipientsListView())
      ->setBadge($badge)
      ->setHandles($handles)
      ->setUser($viewer);

    $add_comment = $this->buildCommentForm($badge);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->setMainColumn(array(
          $recipient_list,
          $timeline,
          $add_comment,
        ))
      ->addPropertySection(pht('BADGE DETAILS'), $details);

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

    $quality = idx($badge->getQualityNameMap(), $badge->getQuality());

    $view->addProperty(
      pht('Quality'),
      $quality);

    $view->addProperty(
      pht('Icon'),
      id(new PhabricatorBadgesIconSet())
        ->getIconLabel($badge->getIcon()));

    $view->addProperty(
      pht('Flavor'),
      $badge->getFlavor());

    $description = $badge->getDescription();
    if (strlen($description)) {
      $view->addSectionHeader(
        pht('Description'), PHUIPropertyListView::ICON_SUMMARY);
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

  private function buildCommentForm(PhabricatorBadgesBadge $badge) {
    $viewer = $this->getViewer();

    $is_serious = PhabricatorEnv::getEnvConfig('phabricator.serious-business');

    $add_comment_header = $is_serious
      ? pht('Add Comment')
      : pht('Render Honors');

    $draft = PhabricatorDraft::newFromUserAndKey($viewer, $badge->getPHID());

    return id(new PhabricatorApplicationTransactionCommentView())
      ->setUser($viewer)
      ->setObjectPHID($badge->getPHID())
      ->setDraft($draft)
      ->setHeaderText($add_comment_header)
      ->setAction($this->getApplicationURI('/comment/'.$badge->getID().'/'))
      ->setSubmitButtonName(pht('Add Comment'));
  }

}
