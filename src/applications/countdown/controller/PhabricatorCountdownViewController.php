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
      ->setCountdown($countdown);

    $id = $countdown->getID();
    $title = $countdown->getTitle();

    $crumbs = $this
      ->buildApplicationCrumbs()
      ->addTextCrumb("C{$id}")
      ->setBorder(true);

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
      ->setStatus($icon, $color, $status)
      ->setHeaderIcon('fa-rocket');

    $actions = $this->buildActionListView($countdown);
    $properties = $this->buildPropertyListView($countdown);
    $subheader = $this->buildSubheaderView($countdown);

    $timeline = $this->buildTransactionTimeline(
      $countdown,
      new PhabricatorCountdownTransactionQuery());
    $add_comment = $this->buildCommentForm($countdown);

    $content = array(
      $countdown_view,
      $timeline,
      $add_comment,
    );

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setSubheader($subheader)
      ->setMainColumn($content)
      ->setPropertyList($properties)
      ->setActionList($actions);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->setPageObjectPHIDs(
        array(
          $countdown->getPHID(),
        ))
      ->appendChild(
        array(
          $view,
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
    PhabricatorCountdown $countdown) {
    $viewer = $this->getViewer();
    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($countdown);
    $view->invokeWillRenderEvent();
    return $view;
  }

  private function buildSubheaderView(
    PhabricatorCountdown $countdown) {
    $viewer = $this->getViewer();

    $author = $viewer->renderHandle($countdown->getAuthorPHID())->render();
    $date = phabricator_datetime($countdown->getDateCreated(), $viewer);
    $author = phutil_tag('strong', array(), $author);

    $person = id(new PhabricatorPeopleQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($countdown->getAuthorPHID()))
      ->needProfileImage(true)
      ->executeOne();

    $image_uri = $person->getProfileImageURI();
    $image_href = '/p/'.$person->getUsername();

    $content = pht('Authored by %s on %s.', $author, $date);

    return id(new PHUIHeadThingView())
      ->setImage($image_uri)
      ->setImageHref($image_href)
      ->setContent($content);
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
