<?php

final class HeraldRuleViewController extends HeraldController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $request->getViewer();
    $id = $request->getURIData('id');

    $rule = id(new HeraldRuleQuery())
      ->setViewer($viewer)
      ->withIDs(array($id))
      ->needConditionsAndActions(true)
      ->needValidateAuthors(true)
      ->executeOne();
    if (!$rule) {
      return new Aphront404Response();
    }

    $header = id(new PHUIHeaderView())
      ->setUser($viewer)
      ->setHeader($rule->getName())
      ->setPolicyObject($rule)
      ->setHeaderIcon('fa-bullhorn');

    if ($rule->getIsDisabled()) {
      $header->setStatus('fa-ban', 'red', pht('Disabled'));
    } else if (!$rule->hasValidAuthor()) {
      $header->setStatus('fa-user', 'red', pht('Author Not Active'));
    } else {
      $header->setStatus('fa-check', 'bluegrey', pht('Active'));
    }

    $curtain = $this->buildCurtain($rule);
    $details = $this->buildPropertySectionView($rule);
    $description = $this->buildDescriptionView($rule);

    $id = $rule->getID();

    $crumbs = $this->buildApplicationCrumbs();
    $crumbs->addTextCrumb("H{$id}");
    $crumbs->setBorder(true);

    $timeline = $this->buildTransactionTimeline(
      $rule,
      new HeraldTransactionQuery());
    $timeline->setShouldTerminate(true);

    $title = $rule->getName();

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->setMainColumn($timeline)
      ->addPropertySection(pht('Details'), $details)
      ->addPropertySection(pht('Description'), $description);

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

  private function buildCurtain(HeraldRule $rule) {
    $viewer = $this->getViewer();

    $id = $rule->getID();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $rule,
      PhabricatorPolicyCapability::CAN_EDIT);

    $curtain = $this->newCurtainView($rule);

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Rule'))
        ->setHref($this->getApplicationURI("edit/{$id}/"))
        ->setIcon('fa-pencil')
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    if ($rule->getIsDisabled()) {
      $disable_uri = "disable/{$id}/enable/";
      $disable_icon = 'fa-check';
      $disable_name = pht('Enable Rule');
    } else {
      $disable_uri = "disable/{$id}/disable/";
      $disable_icon = 'fa-ban';
      $disable_name = pht('Disable Rule');
    }

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setHref($this->getApplicationURI($disable_uri))
        ->setIcon($disable_icon)
        ->setName($disable_name)
        ->setDisabled(!$can_edit)
        ->setWorkflow(true));

    return $curtain;
  }

  private function buildPropertySectionView(
    HeraldRule $rule) {

    $viewer = $this->getRequest()->getUser();
    $view = id(new PHUIPropertyListView())
      ->setUser($viewer);

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
    }

    return $view;
  }

  private function buildDescriptionView(HeraldRule $rule) {
    $viewer = $this->getRequest()->getUser();
    $view = id(new PHUIPropertyListView())
      ->setViewer($viewer);

    $adapter = HeraldAdapter::getAdapterForContentType($rule->getContentType());
    if ($adapter) {
      $rule_text = $adapter->renderRuleAsText($rule, $viewer);
      $view->addTextContent($rule_text);
      return $view;
    }
    return null;
  }

}
