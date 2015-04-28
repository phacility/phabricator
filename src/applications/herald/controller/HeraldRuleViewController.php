<?php

final class HeraldRuleViewController extends HeraldController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {
    $request = $this->getRequest();
    $viewer = $request->getUser();

    $rule = id(new HeraldRuleQuery())
      ->setViewer($viewer)
      ->withIDs(array($this->id))
      ->needConditionsAndActions(true)
      ->executeOne();
    if (!$rule) {
      return new Aphront404Response();
    }

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setHeader($rule->getName())
      ->setPolicyObject($rule);

    if ($rule->getIsDisabled()) {
      $header->setStatus(
        'fa-ban',
        'red',
        pht('Archived'));
    } else {
      $header->setStatus(
        'fa-check',
        'bluegrey',
        pht('Active'));
    }

    $actions = $this->buildActionView($rule);
    $properties = $this->buildPropertyView($rule, $actions);

    $id = $rule->getID();

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb("H{$id}");

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    $timeline = $this->buildTransactionTimeline(
      $rule,
      new HeraldTransactionQuery());
    $timeline->setShouldTerminate(true);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $object_box,
        $timeline,
      ),
      array(
        'title' => $rule->getName(),
      ));
  }

  private function buildActionView(HeraldRule $rule) {
    $viewer = $this->getRequest()->getUser();
    $id = $rule->getID();

    $view = id(new PhabricatorActionListView())
      ->setUser($viewer)
      ->setObject($rule)
      ->setObjectURI($this->getApplicationURI("rule/{$id}/"));

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $rule,
      PhabricatorPolicyCapability::CAN_EDIT);

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Rule'))
        ->setHref($this->getApplicationURI("edit/{$id}/"))
        ->setIcon('fa-pencil')
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    if ($rule->getIsDisabled()) {
      $disable_uri = "disable/{$id}/enable/";
      $disable_icon = 'fa-check';
      $disable_name = pht('Activate Rule');
    } else {
      $disable_uri = "disable/{$id}/disable/";
      $disable_icon = 'fa-ban';
      $disable_name = pht('Archive Rule');
    }

    $view->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Disable Rule'))
        ->setHref($this->getApplicationURI($disable_uri))
        ->setIcon($disable_icon)
        ->setName($disable_name)
        ->setDisabled(!$can_edit)
        ->setWorkflow(true));

    return $view;
  }

  private function buildPropertyView(
    HeraldRule $rule,
    PhabricatorActionListView $actions) {

    $viewer = $this->getRequest()->getUser();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer)
      ->setObject($rule)
      ->setActionList($actions);

    $view->addProperty(
      pht('Rule Type'),
      idx(HeraldRuleTypeConfig::getRuleTypeMap(), $rule->getRuleType()));

    if ($rule->isPersonalRule()) {
      $view->addProperty(
        pht('Author'),
        $viewer->renderHandle($rule->getAuthorPHID()));
    }

    $adapter = HeraldAdapter::getAdapterForContentType($rule->getContentType());
    if ($adapter) {
      $view->addProperty(
        pht('Applies To'),
        idx(
          HeraldAdapter::getEnabledAdapterMap($viewer),
          $rule->getContentType()));

      if ($rule->isObjectRule()) {
        $view->addProperty(
          pht('Trigger Object'),
          $viewer->renderHandle($rule->getTriggerObjectPHID()));
      }

      $view->invokeWillRenderEvent();

      $view->addSectionHeader(
        pht('Rule Description'),
        PHUIPropertyListView::ICON_SUMMARY);

      $handles = $viewer->loadHandles(HeraldAdapter::getHandlePHIDs($rule));
      $view->addTextContent($adapter->renderRuleAsText($rule, $handles));
    }

    return $view;
  }

}
