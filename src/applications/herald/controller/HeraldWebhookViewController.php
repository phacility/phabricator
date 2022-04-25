<?php

final class HeraldWebhookViewController
  extends HeraldWebhookController {

  public function shouldAllowPublic() {
    return true;
  }

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

    $hook = id(new HeraldWebhookQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getURIData('id')))
      ->executeOne();
    if (!$hook) {
      return new Aphront404Response();
    }

    $header = $this->buildHeaderView($hook);

    $warnings = null;
    if ($hook->isInErrorBackoff($viewer)) {
      $message = pht(
        'Many requests to this webhook have failed recently (at least %s '.
        'errors in the last %s seconds). New requests are temporarily paused.',
        $hook->getErrorBackoffThreshold(),
        $hook->getErrorBackoffWindow());

      $warnings = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_WARNING)
        ->setErrors(
          array(
            $message,
          ));
    }

    $curtain = $this->buildCurtain($hook);
    $properties_view = $this->buildPropertiesView($hook);

    $timeline = $this->buildTransactionTimeline(
      $hook,
      new HeraldWebhookTransactionQuery());
    $timeline->setShouldTerminate(true);

    $requests = id(new HeraldWebhookRequestQuery())
      ->setViewer($viewer)
      ->withWebhookPHIDs(array($hook->getPHID()))
      ->setLimit(20)
      ->execute();

    $warnings = array();
    if (PhabricatorEnv::getEnvConfig('phabricator.silent')) {
      $message = pht(
        'This server is running in silent mode, so it will not '.
        'publish webhooks. To adjust this setting, see '.
        '@{config:phabricator.silent} in Config.');

      $warnings[] = id(new PHUIInfoView())
        ->setTitle(pht('Silent Mode'))
        ->setSeverity(PHUIInfoView::SEVERITY_WARNING)
        ->appendChild(new PHUIRemarkupView($viewer, $message));
    }

    $requests_table = id(new HeraldWebhookRequestListView())
      ->setViewer($viewer)
      ->setRequests($requests)
      ->setHighlightID($request->getURIData('requestID'));

    $requests_view = id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Recent Requests'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setTable($requests_table);

    $rules_view = $this->newRulesView($hook);

    $hook_view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setMainColumn(
        array(
          $warnings,
          $properties_view,
          $rules_view,
          $requests_view,
          $timeline,
        ))
      ->setCurtain($curtain);

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb(pht('Webhook %d', $hook->getID()))
      ->setBorder(true);

    return $this->newPage()
      ->setTitle(
        array(
          pht('Webhook %d', $hook->getID()),
          $hook->getName(),
        ))
      ->setCrumbs($crumbs)
      ->setPageObjectPHIDs(
        array(
          $hook->getPHID(),
        ))
      ->appendChild($hook_view);
  }

  private function buildHeaderView(HeraldWebhook $hook) {
    $viewer = $this->getViewer();

    $title = $hook->getName();

    $status_icon = $hook->getStatusIcon();
    $status_color = $hook->getStatusColor();
    $status_name = $hook->getStatusDisplayName();

    $header = id(new PHUIHeaderView())
      ->setHeader($title)
      ->setViewer($viewer)
      ->setPolicyObject($hook)
      ->setStatus($status_icon, $status_color, $status_name)
      ->setHeaderIcon('fa-cloud-upload');

    return $header;
  }


  private function buildCurtain(HeraldWebhook $hook) {
    $viewer = $this->getViewer();
    $curtain = $this->newCurtainView($hook);

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $hook,
      PhabricatorPolicyCapability::CAN_EDIT);

    $id = $hook->getID();
    $edit_uri = $this->getApplicationURI("webhook/edit/{$id}/");
    $test_uri = $this->getApplicationURI("webhook/test/{$id}/");

    $key_view_uri = $this->getApplicationURI("webhook/key/view/{$id}/");
    $key_cycle_uri = $this->getApplicationURI("webhook/key/cycle/{$id}/");

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Webhook'))
        ->setIcon('fa-pencil')
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit)
        ->setHref($edit_uri));

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('New Test Request'))
        ->setIcon('fa-cloud-upload')
        ->setDisabled(!$can_edit)
        ->setWorkflow(true)
        ->setHref($test_uri));

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('View HMAC Key'))
        ->setIcon('fa-key')
        ->setDisabled(!$can_edit)
        ->setWorkflow(true)
        ->setHref($key_view_uri));

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Regenerate HMAC Key'))
        ->setIcon('fa-refresh')
        ->setDisabled(!$can_edit)
        ->setWorkflow(true)
        ->setHref($key_cycle_uri));

    return $curtain;
  }


  private function buildPropertiesView(HeraldWebhook $hook) {
    $viewer = $this->getViewer();

    $properties = id(new PHUIPropertyListView())
      ->setViewer($viewer);

    $properties->addProperty(
      pht('URI'),
      $hook->getWebhookURI());

    $properties->addProperty(
      pht('Status'),
      $hook->getStatusDisplayName());

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Details'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($properties);
  }

  private function newRulesView(HeraldWebhook $hook) {
    $viewer = $this->getViewer();

    $rules = id(new HeraldRuleQuery())
      ->setViewer($viewer)
      ->withDisabled(false)
      ->withAffectedObjectPHIDs(array($hook->getPHID()))
      ->needValidateAuthors(true)
      ->setLimit(10)
      ->execute();

    $list = id(new HeraldRuleListView())
      ->setViewer($viewer)
      ->setRules($rules)
      ->newObjectList();

    $list->setNoDataString(pht('No active Herald rules call this webhook.'));

    $more_href = new PhutilURI(
      '/herald/',
      array('affectedPHID' => $hook->getPHID()));

    $more_link = id(new PHUIButtonView())
      ->setTag('a')
      ->setIcon('fa-list-ul')
      ->setText(pht('View All Rules'))
      ->setHref($more_href);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Called By Herald Rules'))
      ->addActionLink($more_link);

    return id(new PHUIObjectBoxView())
      ->setHeader($header)
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->appendChild($list);
  }

}
