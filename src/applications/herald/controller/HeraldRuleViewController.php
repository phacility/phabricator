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
        'oh-open',
        'red',
        pht('Disabled'));
    } else {
      $header->setStatus(
        'oh-open',
        null,
        pht('Active'));
    }

    $actions = $this->buildActionView($rule);
    $properties = $this->buildPropertyView($rule, $actions);

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addCrumb(
      id(new PhabricatorCrumbView())
        ->setName(pht('Rule %d', $rule->getID())));

    $object_box = id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->addPropertyList($properties);

    $timeline = $this->buildTimeline($rule);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $object_box,
        $timeline,
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

    if ($rule->getIsDisabled()) {
      $disable_uri = "disable/{$id}/enable/";
      $disable_icon = 'enable';
      $disable_name = pht('Enable Rule');
    } else {
      $disable_uri = "disable/{$id}/disable/";
      $disable_icon = 'disable';
      $disable_name = pht('Disable Rule');
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

    $this->loadHandles(HeraldAdapter::getHandlePHIDs($rule));

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

  private function buildTimeline(HeraldRule $rule) {
    $viewer = $this->getRequest()->getUser();

    $xactions = id(new HeraldTransactionQuery())
      ->setViewer($viewer)
      ->withObjectPHIDs(array($rule->getPHID()))
      ->needComments(true)
      ->execute();

    $engine = id(new PhabricatorMarkupEngine())
      ->setViewer($viewer);
    foreach ($xactions as $xaction) {
      if ($xaction->getComment()) {
        $engine->addObject(
          $xaction->getComment(),
          PhabricatorApplicationTransactionComment::MARKUP_FIELD_COMMENT);
      }
    }
    $engine->process();

    return id(new PhabricatorApplicationTransactionView())
      ->setUser($viewer)
      ->setObjectPHID($rule->getPHID())
      ->setTransactions($xactions)
      ->setMarkupEngine($engine);
  }

}
