<?php

final class PhortuneSubscriptionEditController extends PhortuneController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();
    $added = $request->getBool('added');

    $subscription = id(new PhortuneSubscriptionQuery())
      ->setViewer($viewer)
      ->withIDs(array($request->getURIData('id')))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->executeOne();
    if (!$subscription) {
      return new Aphront404Response();
    }

    id(new PhabricatorAuthSessionEngine())->requireHighSecuritySession(
      $viewer,
      $request,
      $subscription->getURI());
    $merchant = $subscription->getMerchant();
    $account = $subscription->getAccount();

    $title = pht('Subscription: %s', $subscription->getSubscriptionName());

    $header = id(new PHUIHeaderView())
      ->setHeader($subscription->getSubscriptionName());

    $view_uri = $subscription->getURI();

    $valid_methods = id(new PhortunePaymentMethodQuery())
      ->setViewer($viewer)
      ->withAccountPHIDs(array($account->getPHID()))
      ->withStatuses(
        array(
          PhortunePaymentMethod::STATUS_ACTIVE,
        ))
      ->withMerchantPHIDs(array($merchant->getPHID()))
      ->requireCapabilities(
        array(
          PhabricatorPolicyCapability::CAN_VIEW,
          PhabricatorPolicyCapability::CAN_EDIT,
        ))
      ->execute();
    $valid_methods = mpull($valid_methods, null, 'getPHID');

    $current_phid = $subscription->getDefaultPaymentMethodPHID();

    $e_method = null;
    if ($current_phid && empty($valid_methods[$current_phid])) {
      $e_method = pht('Needs Update');
    }

    $errors = array();
    if ($request->isFormPost()) {

      $default_method_phid = $request->getStr('defaultPaymentMethodPHID');
      if (!$default_method_phid) {
        $default_method_phid = null;
        $e_method = null;
      } else if (empty($valid_methods[$default_method_phid])) {
        $e_method = pht('Invalid');
        if ($default_method_phid == $current_phid) {
          $errors[] = pht(
            'This subscription is configured to autopay with a payment method '.
            'that has been deleted. Choose a valid payment method or disable '.
            'autopay.');
        } else {
          $errors[] = pht('You must select a valid default payment method.');
        }
      }

      // TODO: We should use transactions here, and move the validation logic
      // inside the Editor.

      if (!$errors) {
        $subscription->setDefaultPaymentMethodPHID($default_method_phid);
        $subscription->save();

        return id(new AphrontRedirectResponse())
          ->setURI($view_uri);
      }
    }

    // Add the option to disable autopay.
    $disable_options = array(
      '' => pht('(Disable Autopay)'),
    );

    // Don't require the user to make a valid selection if the current method
    // has become invalid.
    if ($current_phid && empty($valid_methods[$current_phid])) {
      $current_options = array(
        $current_phid => pht('<Deleted Payment Method>'),
      );
    } else {
      $current_options = array();
    }

    // Add any available options.
    $valid_options = mpull($valid_methods, 'getFullDisplayName', 'getPHID');

    $options = $disable_options + $current_options + $valid_options;

    $crumbs = $this->buildApplicationCrumbs();
    $this->addAccountCrumb($crumbs, $account);
    $crumbs->addTextCrumb(
      pht('Subscription %d', $subscription->getID()),
      $view_uri);
    $crumbs->addTextCrumb(pht('Edit'));
    $crumbs->setBorder(true);


    $uri = $this->getApplicationURI($account->getID().'/card/new/');
    $uri = new PhutilURI($uri);
    $uri->setQueryParam('merchantID', $merchant->getID());
    $uri->setQueryParam('subscriptionID', $subscription->getID());

    $add_method_button = phutil_tag(
      'a',
      array(
        'href' => $uri,
        'class' => 'button button-grey',
      ),
      pht('Add Payment Method...'));

    $radio = id(new AphrontFormRadioButtonControl())
      ->setName('defaultPaymentMethodPHID')
      ->setLabel(pht('Autopay With'))
      ->setValue($current_phid)
      ->setError($e_method);

    foreach ($options as $key => $value) {
      $radio->addButton($key, $value, null);
    }

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild($radio)
      ->appendChild(
        id(new AphrontFormMarkupControl())
          ->setValue($add_method_button))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Save Changes'))
          ->addCancelButton($view_uri));

    $box = id(new PHUIObjectBoxView())
      ->setUser($viewer)
      ->setHeaderText(pht('Subscription'))
      ->setBackground(PHUIObjectBoxView::BLUE_PROPERTY)
      ->setFormErrors($errors)
      ->appendChild($form);

    if ($added) {
      $info_view = id(new PHUIInfoView())
        ->setSeverity(PHUIInfoView::SEVERITY_SUCCESS)
        ->appendChild(pht('Payment method has been successfully added.'));
      $box->setInfoView($info_view);
    }

    $header = id(new PHUIHeaderView())
      ->setHeader(pht('Edit %s', $subscription->getSubscriptionName()))
      ->setHeaderIcon('fa-pencil');

    $view = id(new PHUITwoColumnView())
      ->setHeader($header)
      ->setFooter(array(
        $box,
      ));

    return $this->newPage()
      ->setTitle($title)
      ->setCrumbs($crumbs)
      ->appendChild($view);

  }


}
