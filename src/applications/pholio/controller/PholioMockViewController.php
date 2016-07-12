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
      ->setPolicyObject($mock)
      ->setHeaderIcon('fa-camera-retro');

    $timeline = $this->buildTransactionTimeline(
      $mock,
      new PholioTransactionQuery(),
      $engine);
    $timeline->setMock($mock);

    $curtain = $this->buildCurtainView($mock);
    $details = $this->buildDescriptionView($mock, $engine);

    require_celerity_resource('pholio-css');
    require_celerity_resource('pholio-inline-comments-css');

    $comment_form_id = celerity_generate_unique_node_id();
    $mock_view = id(new PholioMockImagesView())
      ->setRequestURI($request->getRequestURI())
      ->setCommentFormID($comment_form_id)
      ->setUser($viewer)
      ->setMock($mock)
      ->setImageID($image_id);

    $output = id(new PHUIObjectBoxView())
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($mock_view);

    $add_comment = $this->buildAddCommentView($mock, $comment_form_id);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb('M'.$mock->getID(), '/M'.$mock->getID());
    $crumbs->setBorder(true);

    $thumb_grid = id(new PholioMockThumbGridView())
      ->setUser($viewer)
      ->setMock($mock);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->setMainColumn(array(
        $output,
        $thumb_grid,
        $details,
        $timeline,
        $add_comment,
      ));

    return $this->newPage()
      ->setTitle('M'.$mock->getID().' '.$title)
      ->setCrumbs($crumbs)
      ->setPageObjectPHIDs(array($mock->getPHID()))
      ->addQuicksandConfig(
        array('mockViewConfig' => $mock_view->getBehaviorConfig()))
      ->appendChild($view);
  }

  private function buildCurtainView(PholioMock $mock) {
    $viewer = $this->getViewer();

    $curtain = $this->newCurtainView($mock);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $mock,
      PhabricatorPolicyCapability::CAN_EDIT);

    $curtain->addAction(
      id(new PhabricatorActionView())
      ->setIcon('fa-pencil')
      ->setName(pht('Edit Mock'))
      ->setHref($this->getApplicationURI('/edit/'.$mock->getID().'/'))
      ->setDisabled(!$can_edit)
      ->setWorkflow(!$can_edit));

    if ($mock->isClosed()) {
      $curtain->addAction(
        id(new PhabricatorActionView())
        ->setIcon('fa-check')
        ->setName(pht('Open Mock'))
        ->setHref($this->getApplicationURI('/archive/'.$mock->getID().'/'))
        ->setDisabled(!$can_edit)
        ->setWorkflow(true));
    } else {
      $curtain->addAction(
        id(new PhabricatorActionView())
        ->setIcon('fa-ban')
        ->setName(pht('Close Mock'))
        ->setHref($this->getApplicationURI('/archive/'.$mock->getID().'/'))
        ->setDisabled(!$can_edit)
        ->setWorkflow(true));
    }

    $relationship_list = PhabricatorObjectRelationshipList::newForObject(
      $viewer,
      $mock);

    $relationship_submenu = $relationship_list->newActionMenu();
    if ($relationship_submenu) {
      $curtain->addAction($relationship_submenu);
    }

    if ($this->getManiphestTaskPHIDs()) {
      $curtain->newPanel()
        ->setHeaderText(pht('Maniphest Tasks'))
        ->appendChild(
          $viewer->renderHandleList($this->getManiphestTaskPHIDs()));
    }

    $curtain->newPanel()
      ->setHeaderText(pht('Authored By'))
      ->appendChild($this->buildAuthorPanel($mock));

    return $curtain;
  }

  private function buildDescriptionView(PholioMock $mock) {
    $viewer = $this->getViewer();

    $properties = id(new PHUIPropertyListView())
      ->setUser($viewer);
    $description = $mock->getDescription();

    if (strlen($description)) {
      $properties->addTextContent(
        new PHUIRemarkupView($viewer, $description));
      return id(new PHUIObjectBoxView())
        ->setHeaderText(pht('Mock Description'))
        ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
        ->appendChild($properties);
    }

    return null;
  }

  private function buildAuthorPanel(PholioMock $mock) {
    $viewer = $this->getViewer();
    $author_phid = $mock->getAuthorPHID();
    $handles = $viewer->loadHandles(array($author_phid));

    $author_uri = $handles[$author_phid]->getImageURI();
    $author_href = $handles[$author_phid]->getURI();
    $author = $viewer->renderHandle($author_phid)->render();

    $content = phutil_tag('strong', array(), $author);
    $date = phabricator_date($mock->getDateCreated(), $viewer);
    $content = pht('%s, %s', $content, $date);
    $authored_by = id(new PHUIHeadThingView())
      ->setImage($author_uri)
      ->setImageHref($author_href)
      ->setContent($content);

    return $authored_by;
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
