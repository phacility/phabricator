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
      ->setHeader($rule->getName());

    $actions = $this->buildActionView($rule);
    $properties = $this->buildPropertyView($rule);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('Rule %d', $rule->getID())));

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setActionList($actions)
      ->setPropertyList($properties);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $object_box,
      ),
      array(
        'title' => $rule->getName(),
        'device' => true,
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
        ->setIcon('edit')
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    return $view;
  }

  private function buildPropertyView(HeraldRule $rule) {
    $viewer = $this->getRequest()->getUser();

    $this->loadHandles(HeraldAdapter::getHandlePHIDs($rule));

    $view = id(new PhabricatorPropertyListView())
      ->setUser($viewer)
      ->setObject($rule);

    $view->addProperty(
      pht('Rule Type'),
      idx(HeraldRuleTypeConfig::getRuleTypeMap(), $rule->getRuleType()));

    if ($rule->isPersonalRule()) {
      $view->addProperty(
        pht('Author'),
        $this->getHandle($rule->getAuthorPHID())->renderLink());
    }

    $adapter = HeraldAdapter::getAdapterForContentType($rule->getContentType());
    if ($adapter) {
      $view->addProperty(
        pht('Applies To'),
        idx(
          HeraldAdapter::getEnabledAdapterMap($viewer),
          $rule->getContentType()));

      $view->invokeWillRenderEvent();

      $view->addSectionHeader(pht('Rule Description'));
      $view->addTextContent(
        phutil_tag(
          'div',
          array(
            'style' => 'white-space: pre-wrap;',
          ),
          $adapter->renderRuleAsText($rule, $this->getLoadedHandles())));
    }

    return $view;
  }

}
