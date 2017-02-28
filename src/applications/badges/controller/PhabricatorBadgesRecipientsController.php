<?php

final class PhabricatorBadgesRecipientsController
  extends PhabricatorBadgesProfileController {

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

    $this->setBadge($badge);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb(pht('Recipients'));
    $crumbs->setBorder(true);
    $title = $badge->getName();

    $header = $this->buildHeaderView();

    $awards = $badge->getAwards();
    $recipient_phids = mpull($awards, 'getRecipientPHID');
    $recipient_phids = array_reverse($recipient_phids);
    $handles = $this->loadViewerHandles($recipient_phids);

    $recipient_list = id(new PhabricatorBadgesRecipientsListView())
      ->setBadge($badge)
      ->setHandles($handles)
      ->setUser($viewer);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(array(
          $recipient_list,
        ));

    $navigation = $this->buildSideNavView('recipients');

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->setPageObjectPHIDs(array($badge->getPHID()))
      ->setNavigation($navigation)
      ->appendChild($view);
  }

}
