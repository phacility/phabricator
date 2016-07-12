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

    $curtain = $this->buildCurtain($countdown);
    $subheader = $this->buildSubheaderView($countdown);

    $timeline = $this->buildTransactionTimeline(
      $countdown,
      new PhabricatorCountdownTransactionQuery());

    $comment_view = id(new PhabricatorCountdownEditEngine())
      ->setViewer($viewer)
      ->buildEditEngineCommentView($countdown);

    $content = array(
      $countdown_view,
      $timeline,
      $comment_view,
    );

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setSubheader($subheader)
      ->setCurtain($curtain)
      ->setMainColumn($content);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->setPageObjectPHIDs(
        array(
          $countdown->getPHID(),
        ))
      ->appendChild($view);
  }

  private function buildCurtain(PhabricatorCountdown $countdown) {
    $viewer = $this->getViewer();

    $id = $countdown->getID();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $countdown,
      PhabricatorPolicyCapability::CAN_EDIT);

    $curtain = $this->newCurtainView($countdown);

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-pencil')
        ->setName(pht('Edit Countdown'))
        ->setHref($this->getApplicationURI("edit/{$id}/"))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setIcon('fa-times')
        ->setName(pht('Delete Countdown'))
        ->setHref($this->getApplicationURI("delete/{$id}/"))
        ->setDisabled(!$can_edit)
        ->setWorkflow(true));

    return $curtain;
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

}
