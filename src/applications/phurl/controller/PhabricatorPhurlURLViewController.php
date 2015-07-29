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
    $crumbs->addTextCrumb($title, $url->getURI());

    $timeline = $this->buildTransactionTimeline(
      $url,
      new PhabricatorPhurlURLTransactionQuery());

    $header = $this->buildHeaderView($url);
    $actions = $this->buildActionView($url);
    $properties = $this->buildPropertyView($url);

    $properties->setActionList($actions);
    $box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    $is_serious = PhabricatorEnv::getEnvConfig('phabricator.serious-business');
    $add_comment_header = $is_serious
      ? pht('Add Comment')
      : pht('More Cowbell');
    $draft = PhabricatorDraft::newFromUserAndKey($viewer, $url->getPHID());
    $comment_uri = $this->getApplicationURI(
      '/phurl/comment/'.$url->getID().'/');
    $add_comment_form = id(new PhabricatorApplicationTransactionCommentView())
      ->setUser($viewer)
      ->setObjectPHID($url->getPHID())
      ->setDraft($draft)
      ->setHeaderText($add_comment_header)
      ->setAction($comment_uri)
      ->setSubmitButtonName(pht('Add Comment'));

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
        $timeline,
        $add_comment_form,
      ),
      array(
        'title' => $page_title,
        'pageObjects' => array($url->getPHID()),
      ));
  }

  private function buildHeaderView(PhabricatorPhurlURL $url) {
    $viewer = $this->getViewer();
    $icon = 'fa-compress';
    $color = 'green';
    $status = pht('Active');

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setHeader($url->getName())
      ->setStatus($icon, $color, $status)
      ->setPolicyObject($url);

    return $header;
  }

  private function buildActionView(PhabricatorPhurlURL $url) {
    $viewer = $this->getViewer();
    $id = $url->getID();

    $actions = id(new PhabricatorActionListView())
      ->setObjectURI($url->getURI())
      ->setUser($viewer)
      ->setObject($url);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $url,
      PhabricatorPolicyCapability::CAN_EDIT);

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit'))
        ->setIcon('fa-pencil')
        ->setHref($this->getApplicationURI("url/edit/{$id}/"))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    return $actions;
  }

  private function buildPropertyView(PhabricatorPhurlURL $url) {
    $viewer = $this->getViewer();

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($url);

    $properties->addProperty(
      pht('Original URL'),
      $url->getLongURL());

    $properties->invokeWillRenderEvent();

    if (strlen($url->getDescription())) {
      $description = PhabricatorMarkupEngine::renderOneObject(
        id(new PhabricatorMarkupOneOff())->setContent($url->getDescription()),
        'default',
        $viewer);

      $properties->addSectionHeader(
        pht('Description'),
        PHUIPropertyListView::ICON_SUMMARY);

      $properties->addTextContent($description);
    }

    return $properties;
  }

}
