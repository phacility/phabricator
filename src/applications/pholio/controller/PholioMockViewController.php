<?php

/**
 * @group pholio
 */
final class PholioMockViewController extends PholioController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $mock = id(new PholioMockQuery())
      ->setViewer($user)
      ->withIDs(array($this->id))
      ->executeOne();

    if (!$mock) {
      return new Aphront404Response();
    }

    $xactions = id(new PholioTransactionQuery())
      ->setViewer($user)
      ->withObjectPHIDs(array($mock->getPHID()))
      ->execute();

    $subscribers = PhabricatorSubscribersQuery::loadSubscribersForPHID(
      $mock->getPHID());

    $phids = array();
    $phids[] = $mock->getAuthorPHID();
    foreach ($subscribers as $subscriber) {
      $phids[] = $subscriber;
    }
    $this->loadHandles($phids);


    $engine = id(new PhabricatorMarkupEngine())
      ->setViewer($user);
    $engine->addObject($mock, PholioMock::MARKUP_FIELD_DESCRIPTION);
    foreach ($xactions as $xaction) {
      if ($xaction->getComment()) {
        $engine->addObject(
          $xaction->getComment(),
          PhabricatorApplicationTransactionComment::MARKUP_FIELD_COMMENT);
      }
    }
    $engine->process();

    $title = 'M'.$mock->getID().' '.$mock->getName();

    $header = id(new PhabricatorHeaderView())
      ->setHeader($title);

    $actions = $this->buildActionView($mock);
    $properties = $this->buildPropertyView($mock, $engine, $subscribers);

    $carousel =
      '<h1 style="margin: 2em; padding: 1em; border: 1px dashed grey;">'.
        'Carousel Goes Here</h1>';

    $xaction_view = id(new PhabricatorApplicationTransactionView())
      ->setViewer($this->getRequest()->getUser())
      ->setTransactions($xactions)
      ->setMarkupEngine($engine);

    $add_comment = $this->buildAddCommentView($mock);

    $content = array(
      $header,
      $actions,
      $properties,
      $carousel,
      $xaction_view,
      $add_comment,
    );

    return $this->buildApplicationPage(
      $content,
      array(
        'title' => $title,
        'device' => true,
      ));
  }

  private function buildActionView(PholioMock $mock) {
    $user = $this->getRequest()->getUser();

    $actions = id(new PhabricatorActionListView())
      ->setUser($user)
      ->setObject($mock);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $user,
      $mock,
      PhabricatorPolicyCapability::CAN_EDIT);

    $actions->addAction(
      id(new PhabricatorActionView())
        ->setIcon('edit')
        ->setName(pht('Edit Mock'))
        ->setHref($this->getApplicationURI('/edit/'.$mock->getID()))
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    return $actions;
  }

  private function buildPropertyView(
    PholioMock $mock,
    PhabricatorMarkupEngine $engine,
    array $subscribers) {

    $user = $this->getRequest()->getUser();

    $properties = new PhabricatorPropertyListView();

    $properties->addProperty(
      pht('Author'),
      $this->getHandle($mock->getAuthorPHID())->renderLink());

    $properties->addProperty(
      pht('Created'),
      phabricator_datetime($mock->getDateCreated(), $user));

    $descriptions = PhabricatorPolicyQuery::renderPolicyDescriptions(
      $user,
      $mock);

    $properties->addProperty(
      pht('Visible To'),
      $descriptions[PhabricatorPolicyCapability::CAN_VIEW]);

    if ($subscribers) {
      $sub_view = array();
      foreach ($subscribers as $subscriber) {
        $sub_view[] = $this->getHandle($subscriber)->renderLink();
      }
      $sub_view = implode(', ', $sub_view);
    } else {
      $sub_view = '<em>'.pht('None').'</em>';
    }

    $properties->addProperty(
      pht('Subscribers'),
      $sub_view);

    $properties->addTextContent(
      $engine->getOutput($mock, PholioMock::MARKUP_FIELD_DESCRIPTION));

    return $properties;
  }

  private function buildAddCommentView(PholioMock $mock) {
    $user = $this->getRequest()->getUser();

    $is_serious = PhabricatorEnv::getEnvConfig('phabricator.serious-business');

    $title = $is_serious
      ? pht('Add Comment')
      : pht('History Beckons');

    $header = id(new PhabricatorHeaderView())
      ->setHeader($title);

    $action = $is_serious
      ? pht('Add Comment')
      : pht('Answer The Call');

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->addSigil('transaction-append')
      ->setAction($this->getApplicationURI('/comment/'.$mock->getID().'/'))
      ->setWorkflow(true)
      ->setFlexible(true)
      ->appendChild(
        id(new PhabricatorRemarkupControl())
          ->setName('comment')
          ->setLabel(pht('Comment'))
          ->setUser($user))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue($action));

    return array(
      $header,
      $form,
    );
  }

}
