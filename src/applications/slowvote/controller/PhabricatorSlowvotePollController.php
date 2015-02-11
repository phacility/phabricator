<?php

final class PhabricatorSlowvotePollController
  extends PhabricatorSlowvoteController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $poll = id(new PhabricatorSlowvoteQuery())
      ->setViewer($user)
      ->withIDs(array($this->id))
      ->needOptions(true)
      ->needChoices(true)
      ->needViewerChoices(true)
      ->executeOne();
    if (!$poll) {
      return new Aphront404Response();
    }

    $poll_view = id(new SlowvoteEmbedView())
      ->setHeadless(true)
      ->setUser($user)
      ->setPoll($poll);

    if ($request->isAjax()) {
      return id(new AphrontAjaxResponse())
        ->setContent(
          array(
            'pollID' => $poll->getID(),
            'contentHTML' => $poll_view->render(),
          ));
    }

    $header_icon = $poll->getIsClosed() ? 'fa-ban' : 'fa-circle-o';
    $header_name = $poll->getIsClosed() ? pht('Closed') : pht('Open');
    $header_color = $poll->getIsClosed() ? 'dark' : 'bluegrey';

    $header = id(new PHUIHeaderView())
      ->setHeader($poll->getQuestion())
      ->setUser($user)
      ->setStatus($header_icon, $header_color, $header_name)
      ->setPolicyObject($poll);

    $actions = $this->buildActionView($poll);
    $properties = $this->buildPropertyView($poll, $actions);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb('V'.$poll->getID());

    $timeline = $this->buildTransactionTimeline(
      $poll,
      new PhabricatorSlowvoteTransactionQuery());
    $add_comment = $this->buildCommentForm($poll);

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $object_box,
        phutil_tag(
          'div',
          array(
            'class' => 'mlt mml mmr',
          ),
          $poll_view),
        $timeline,
        $add_comment,
      ),
      array(
        'title' => 'V'.$poll->getID().' '.$poll->getQuestion(),
        'pageObjects' => array($poll->getPHID()),
      ));
  }

  private function buildActionView(PhabricatorSlowvotePoll $poll) {
    $viewer = $this->getRequest()->getUser();

    $view = id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->setObject($poll);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $poll,
      PhabricatorPolicyCapability::CAN_EDIT);

    $is_closed = $poll->getIsClosed();
    $close_poll_text = $is_closed ? pht('Reopen Poll') : pht('Close Poll');
    $close_poll_icon = $is_closed ? 'fa-play-circle-o' : 'fa-ban';

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Poll'))
        ->setIcon('fa-pencil')
        ->setHref($this->getApplicationURI('edit/'.$poll->getID().'/'))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName($close_poll_text)
        ->setIcon($close_poll_icon)
        ->setHref($this->getApplicationURI('close/'.$poll->getID().'/'))
        ->setDisabled(!$can_edit)
        ->setWorkflow(true));

    return $view;
  }

  private function buildPropertyView(
    PhabricatorSlowvotePoll $poll,
    PhabricatorActionListView $actions) {

    $viewer = $this->getRequest()->getUser();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($poll)
      ->setActionList($actions);

    $view->invokeWillRenderEvent();

    if (strlen($poll->getDescription())) {
      $view->addTextContent(
        $output = PhabricatorMarkupEngine::renderOneObject(
          id(new PhabricatorMarkupOneOff())->setContent(
            $poll->getDescription()),
          'default',
          $viewer));
    }

    return $view;
  }

  private function buildCommentForm(PhabricatorSlowvotePoll $poll) {
    $viewer = $this->getRequest()->getUser();

    $is_serious = PhabricatorEnv::getEnvConfig('phabricator.serious-business');

    $add_comment_header = $is_serious
      ? pht('Add Comment')
      : pht('Enter Deliberations');

    $draft = PhabricatorDraft::newFromUserAndKey($viewer, $poll->getPHID());

    return id(new PhabricatorApplicationTransactionCommentView())
      ->setUser($viewer)
      ->setObjectPHID($poll->getPHID())
      ->setDraft($draft)
      ->setHeaderText($add_comment_header)
      ->setAction($this->getApplicationURI('/comment/'.$poll->getID().'/'))
      ->setSubmitButtonName(pht('Add Comment'));
  }

}
