<?php

final class PholioMockViewController extends PholioController {

  private $maniphestTaskPHIDs = array();

  private function setManiphestTaskPHIDs($maniphest_task_phids) {
    $this->maniphestTaskPHIDs = $maniphest_task_phids;
    return $this;
  }
  private function getManiphestTaskPHIDs() {
    return $this->maniphestTaskPHIDs;
  }

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');
    $image_id = $request->getURIData('imageID');

    $mock = id(new PholioMockQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->needImages(true)
      ->needInlineComments(true)
      ->executeOne();

    if (!$mock) {
      return new Aphront404Response();
    }

    $phids = PhabricatorEdgeQuery::loadDestinationPHIDs(
      $mock->getPHID(),
      PholioMockHasTaskEdgeType::EDGECONST);
    $this->setManiphestTaskPHIDs($phids);

    $engine = id(new PhabricatorMarkupEngine())
      ->setViewer($viewer);
    $engine->addObject($mock, PholioMock::MARKUP_FIELD_DESCRIPTION);

    $title = $mock->getName();

    if ($mock->isClosed()) {
      $header_icon = 'fa-ban';
      $header_name = pht('Closed');
      $header_color = 'dark';
    } else {
      $header_icon = 'fa-square-o';
      $header_name = pht('Open');
      $header_color = 'bluegrey';
    }

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setUser($viewer)
      ->setStatus($header_icon, $header_color, $header_name)
      ->setPolicyObject($mock);

    $timeline = $this->buildTransactionTimeline(
      $mock,
      new PholioTransactionQuery(),
      $engine);
    $timeline->setMock($mock);

    $actions = $this->buildActionView($mock);
    $properties = $this->buildPropertyView($mock, $engine, $actions);

    require_celerity_resource('pholio-css');
    require_celerity_resource('pholio-inline-comments-css');

    $comment_form_id = celerity_generate_unique_node_id();
    $mock_view = id(new PholioMockImagesView())
      ->setRequestURI($request->getRequestURI())
      ->setCommentFormID($comment_form_id)
      ->setUser($viewer)
      ->setMock($mock)
      ->setImageID($image_id);
    $this->addExtraQuicksandConfig(
      array('mockViewConfig' => $mock_view->getBehaviorConfig()));

    $output = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Image'))
      ->appendChild($mock_view);

    $add_comment = $this->buildAddCommentView($mock, $comment_form_id);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb('M'.$mock->getID(), '/M'.$mock->getID());

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    $thumb_grid = id(new PholioMockThumbGridView())
      ->setUser($viewer)
      ->setMock($mock);

    $content = array(
      $crumbs,
      $object_box,
      $output,
      $thumb_grid,
      $timeline,
      $add_comment,
    );

    return $this->buildApplicationPage(
      $content,
      array(
        'title' => 'M'.$mock->getID().' '.$title,
        'pageObjects' => array($mock->getPHID()),
      ));
  }

  private function buildActionView(PholioMock $mock) {
    $viewer = $this->getViewer();

    $actions = id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->setObjectURI($this->getRequest()->getRequestURI())
      ->setObject($mock);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $mock,
      PhabricatorPolicyCapability::CAN_EDIT);

    $actions->addAction(
      id(new PhabricatorActionView())
      ->setIcon('fa-pencil')
      ->setName(pht('Edit Mock'))
      ->setHref($this->getApplicationURI('/edit/'.$mock->getID().'/'))
      ->setDisabled(!$can_edit)
      ->setWorkflow(!$can_edit));

    $actions->addAction(
      id(new PhabricatorActionView())
      ->setIcon('fa-anchor')
      ->setName(pht('Edit Maniphest Tasks'))
      ->setHref("/search/attach/{$mock->getPHID()}/TASK/edge/")
      ->setDisabled(!$viewer->isLoggedIn())
      ->setWorkflow(true));

    return $actions;
  }

  private function buildPropertyView(
    PholioMock $mock,
    PhabricatorMarkupEngine $engine,
    PhabricatorActionListView $actions) {

    $viewer = $this->getViewer();

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($mock)
      ->setActionList($actions);

    $properties->addProperty(
      pht('Author'),
      $viewer->renderHandle($mock->getAuthorPHID()));

    $properties->addProperty(
      pht('Created'),
      phabricator_datetime($mock->getDateCreated(), $viewer));

    if ($this->getManiphestTaskPHIDs()) {
      $properties->addProperty(
        pht('Maniphest Tasks'),
        $viewer->renderHandleList($this->getManiphestTaskPHIDs()));
    }

    $properties->invokeWillRenderEvent();

    $properties->addSectionHeader(
        pht('Description'),
        PHUIPropertyListView::ICON_SUMMARY);

    $properties->addImageContent(
        $engine->getOutput($mock, PholioMock::MARKUP_FIELD_DESCRIPTION));

    return $properties;
  }

  private function buildAddCommentView(PholioMock $mock, $comment_form_id) {
    $viewer = $this->getViewer();

    $draft = PhabricatorDraft::newFromUserAndKey($viewer, $mock->getPHID());

    $is_serious = PhabricatorEnv::getEnvConfig('phabricator.serious-business');
    $title = $is_serious
      ? pht('Add Comment')
      : pht('History Beckons');

    $form = id(new PhabricatorApplicationTransactionCommentView())
      ->setUser($viewer)
      ->setObjectPHID($mock->getPHID())
      ->setFormID($comment_form_id)
      ->setDraft($draft)
      ->setHeaderText($title)
      ->setSubmitButtonName(pht('Add Comment'))
      ->setAction($this->getApplicationURI('/comment/'.$mock->getID().'/'))
      ->setRequestURI($this->getRequest()->getRequestURI());

    return $form;
  }

}
