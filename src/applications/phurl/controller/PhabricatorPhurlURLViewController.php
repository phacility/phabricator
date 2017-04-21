<?php

final class PhabricatorPhurlURLViewController
  extends PhabricatorPhurlController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $timeline = null;

    $url = id(new PhabricatorPhurlURLQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$url) {
      return new Aphront404Response();
    }

    $title = $url->getMonogram();
    $page_title = $title.' '.$url->getName();
    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb($title);
    $crumbs->setBorder(true);

    $timeline = $this->buildTransactionTimeline(
      $url,
      new PhabricatorPhurlURLTransactionQuery());
    $timeline->setQuoteRef($url->getMonogram());

    $header = $this->buildHeaderView($url);
    $curtain = $this->buildCurtain($url);
    $details = $this->buildPropertySectionView($url);

    $url_error = id(new PHUIInfoView())
      ->setErrors(array(pht('This URL is invalid due to a bad protocol.')))
      ->setIsHidden($url->isValid());

    $add_comment_form = $this->buildCommentForm($url, $timeline);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->setMainColumn(array(
        $url_error,
        $details,
        $timeline,
        $add_comment_form,
      ));

    return $this->newPage()
      ->setTitle($page_title)
      ->setCrumbs($crumbs)
      ->setPageObjectPHIDs(array($url->getPHID()))
      ->appendChild(
        array(
          $view,
      ));
  }

  private function buildCommentForm(PhabricatorPhurlURL $url, $timeline) {
    $viewer = $this->getViewer();
    $box = id(new PhabricatorPhurlURLEditEngine())
      ->setViewer($viewer)
      ->buildEditEngineCommentView($url)
      ->setTransactionTimeline($timeline);

    return $box;
  }

  private function buildHeaderView(PhabricatorPhurlURL $url) {
    $viewer = $this->getViewer();
    $icon = 'fa-check';
    $color = 'bluegrey';
    $status = pht('Active');
    $id = $url->getID();

    $visit = id(new PHUIButtonView())
      ->setTag('a')
      ->setText(pht('Visit URL'))
      ->setIcon('fa-external-link')
      ->setHref($url->getRedirectURI())
      ->setDisabled(!$url->isValid());

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setHeader($url->getDisplayName())
      ->setStatus($icon, $color, $status)
      ->setPolicyObject($url)
      ->setHeaderIcon('fa-compress')
      ->addActionLink($visit);

    return $header;
  }

  private function buildCurtain(PhabricatorPhurlURL $url) {
    $viewer = $this->getViewer();
    $id = $url->getID();

    $curtain = $this->newCurtainView($url);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $url,
      PhabricatorPolicyCapability::CAN_EDIT);

    $curtain
      ->addAction(
        id(new PhabricatorActionView())
          ->setName(pht('Edit Phurl'))
          ->setIcon('fa-pencil')
          ->setHref($this->getApplicationURI("url/edit/{$id}/"))
          ->setDisabled(!$can_edit)
          ->setWorkflow(!$can_edit));

    return $curtain;
  }

  private function buildPropertySectionView(PhabricatorPhurlURL $url) {
    $viewer = $this->getViewer();

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer);

    $properties->addProperty(
      pht('Short URL'),
      $url->getRedirectURI());

    $properties->addProperty(
      pht('Original URL'),
      $url->getLongURL());

    $properties->addProperty(
      pht('Alias'),
      $url->getAlias());

    $description = $url->getDescription();
    if (strlen($description)) {
      $description = new PHUIRemarkupView($viewer, $description);
      $properties->addSectionHeader(pht('Description'));
      $properties->addTextContent($description);
    }

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Details'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($properties);
  }

}
