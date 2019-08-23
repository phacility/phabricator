<?php

final class PhortuneAccountEmailViewController
  extends PhortuneAccountController {

  protected function shouldRequireAccountEditCapability() {
    return true;
  }

  protected function handleAccountRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $account = $this->getAccount();

    $address = id(new PhortuneAccountEmailQuery())
      ->setViewer($viewer)
      ->withAccountPHIDs(array($account->getPHID()))
      ->withIDs(array($request->getURIData('addressID')))
      ->executeOne();
    if (!$address) {
      return new Aphront404Response();
    }

    $crumbs = $this->buildApplicationCrumbs()
      ->addTextCrumb(pht('Email Addresses'), $account->getEmailAddressesURI())
      ->addTextCrumb($address->getObjectName())
      ->setBorder(true);

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Account Email: %s', $address->getAddress()));

    $details = $this->newDetailsView($address);

    $timeline = $this->buildTransactionTimeline(
      $address,
      new PhortuneAccountEmailTransactionQuery());
    $timeline->setShouldTerminate(true);

    $curtain = $this->buildCurtainView($address);

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setCurtain($curtain)
      ->setMainColumn(
        array(
          $details,
          $timeline,
        ));

    return $this->newPage()
      ->setTitle($address->getObjectName())
      ->setCrumbs($crumbs)
      ->appendChild($view);
  }

  private function buildCurtainView(PhortuneAccountEmail $address) {
    $viewer = $this->getViewer();
    $account = $address->getAccount();

    $can_edit = PhabricatorPolicyFilter::hasCapability(
      $viewer,
      $address,
      PhabricatorPolicyCapability::CAN_EDIT);

    $edit_uri = $this->getApplicationURI(
      urisprintf(
        'account/%d/addresses/edit/%d/',
        $account->getID(),
        $address->getID()));

    if ($can_edit) {
      $external_uri = $address->getExternalURI();
    } else {
      $external_uri = null;
    }

    $curtain = $this->newCurtainView($account);

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Edit Address'))
        ->setIcon('fa-pencil')
        ->setHref($edit_uri)
        ->setDisabled(!$can_edit)
        ->setWorkflow(!$can_edit));

    switch ($address->getStatus()) {
      case PhortuneAccountEmailStatus::STATUS_ACTIVE:
        $disable_name = pht('Disable Address');
        $disable_icon = 'fa-times';
        $can_disable = true;
        $disable_action = 'disable';
        break;
      case PhortuneAccountEmailStatus::STATUS_DISABLED:
        $disable_name = pht('Enable Address');
        $disable_icon = 'fa-check';
        $can_disable = true;
        $disable_action = 'enable';
        break;
      case PhortuneAccountEmailStatus::STATUS_UNSUBSCRIBED:
        $disable_name = pht('Disable Address');
        $disable_icon = 'fa-times';
        $can_disable = false;
        $disable_action = 'disable';
        break;
    }

    $disable_uri = $this->getApplicationURI(
      urisprintf(
        'account/%d/addresses/%d/%s/',
        $account->getID(),
        $address->getID(),
        $disable_action));

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName($disable_name)
        ->setIcon($disable_icon)
        ->setHref($disable_uri)
        ->setDisabled(!$can_disable)
        ->setWorkflow(true));

    $rotate_uri = $this->getApplicationURI(
      urisprintf(
        'account/%d/addresses/%d/rotate/',
        $account->getID(),
        $address->getID()));

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Rotate Access Key'))
        ->setIcon('fa-refresh')
        ->setHref($rotate_uri)
        ->setDisabled(!$can_edit)
        ->setWorkflow(true));

    $curtain->addAction(
      id(new PhabricatorActionView())
        ->setName(pht('Show External View'))
        ->setIcon('fa-eye')
        ->setHref($external_uri)
        ->setDisabled(!$can_edit)
        ->setOpenInNewWindow(true));

    return $curtain;
  }

  private function newDetailsView(PhortuneAccountEmail $address) {
    $viewer = $this->getViewer();

    $view = id(new PHUIPropertyListView())
      ->setUser($viewer);

    $access_key = $address->getAccessKey();

    // This is not a meaningful security barrier: the full plaintext of the
    // access key is visible on the page in the link target of the "Show
    // External View" action. It's just here to make it clear "Rotate Access
    // Key" actually does something.

    $prefix_length = 4;
    $visible_part = substr($access_key, 0, $prefix_length);
    $masked_part = str_repeat(
      "\xE2\x80\xA2",
      strlen($access_key) - $prefix_length);
    $access_display = $visible_part.$masked_part;
    $access_display = phutil_tag('tt', array(), $access_display);

    $view->addProperty(pht('Email Address'), $address->getAddress());
    $view->addProperty(pht('Access Key'), $access_display);

    return id(new PHUIObjectBoxView())
      ->setHeaderText(pht('Email Address Details'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->addPropertyList($view);
  }
}
