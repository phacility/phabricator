<?php

final class PhabricatorCountdownViewController
  extends PhabricatorCountdownController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $countdown = id(new PhabricatorCountdownQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->executeOne();
    if (!$countdown) {
      return new Aphront404Response();
    }

    $countdown_view = id(new PhabricatorCountdownView())
      ->setUser($viewer)
      ->setCountdown($countdown)
      ->setHeadless(true);

    $id = $countdown->getID();
    $title = $countdown->getTitle();

    $crumbs = $this
      ->buildApplicationCrumbs()
      ->addTextCrumb("C{$id}");

    $epoch = $countdown->getEpoch();
    if ($epoch >= PhabricatorTime::getNow()) {
      $icon = 'fa-clock-o';
      $color = '';
      $status = pht('Running');
    } else {
      $icon = 'fa-check-square-o';
      $color = 'dark';
      $status = pht('Launched');
    }

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setUser($viewer)
      ->setPolicyObject($countdown)
      ->setStatus($icon, $color, $status);

    $actions = $this->buildActionListView($countdown);
    $properties = $this->buildPropertyListView($countdown, $actions);

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    $timeline = $this->buildTransactionTimeline(
      $countdown,
      new PhabricatorCountdownTransactionQuery());

    $add_comment = $this->buildCommentForm($countdown);

    $content = array(
      $crumbs,
      $object_box,
      $countdown_view,
      $timeline,
      $add_comment,
    );

    return $this->buildApplicationPage(
      $content,
      array(
        'title' => $title,
        'pageObjects' => array($countdown->getPHID()),
      ));
  }

  private function buildActionListView(PhabricatorCountdown $countdown) {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $id = $countdown->getID();

    $view = id(new PhabricatorActionListView())
      ->setObject($countdown)
      ->setUser($viewer);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $countdown,
      PhabricatorPolicyCapability::CAN_EDIT);

    $view->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-pencil')
        ->setName(pht('Edit Countdown'))
        ->setHref($this->getApplicationURI("edit/{$id}/"))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    $view->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-times')
        ->setName(pht('Delete Countdown'))
        ->setHref($this->getApplicationURI("delete/{$id}/"))
        ->setDisabled(!$can_edit)
        ->setWorkflow(true));

    return $view;
  }

  private function buildPropertyListView(
    PhabricatorCountdown $countdown,
    PhabricatorActionListView $actions) {

    $viewer = $this->getViewer();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($countdown)
      ->setActionList($actions);

    $view->addProperty(
      pht('Author'),
      $viewer->renderHandle($countdown->getAuthorPHID()));

        $view->invokeWillRenderEvent();

    $description = $countdown->getDescription();
    if (strlen($description)) {
      $description = PhabricatorMarkupEngine::renderOneObject(
        id(new PhabricatorMarkupOneOff())->setContent($description),
        'default',
        $viewer);

      $view->addSectionHeader(
        pht('Description'),
        PHUIPropertyListView::ICON_SUMMARY);
      $view->addTextContent($description);
    }

    return $view;
  }

  private function buildCommentForm(PhabricatorCountdown $countdown) {
    $viewer = $this->getViewer();

    $is_serious = PhabricatorEnv::getEnvConfig('phabricator.serious-business');

    $add_comment_header = $is_serious
      ? pht('Add Comment')
      : pht('Last Words');

    $draft = PhabricatorDraft::newFromUserAndKey(
      $viewer, $countdown->getPHID());

    return id(new PhabricatorApplicationTransactionCommentView())
      ->setUser($viewer)
      ->setObjectPHID($countdown->getPHID())
      ->setDraft($draft)
      ->setHeaderText($add_comment_header)
      ->setAction($this->getApplicationURI('/comment/'.$countdown->getID().'/'))
      ->setSubmitButtonName(pht('Add Comment'));
  }

}
