<?php

final class PhortuneSubscriptionEditController extends PhortuneController {

  public function handleRequest(AphrontRequest $request) {
    $viewer = $this->getViewer();

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
      $this->getApplicationURI($subscription->getEditURI()));
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

    $errors = array();
    if ($request->isFormPost()) {

      $default_method_phid = $request->getStr('defaultPaymentMethodPHID');
      if (!$default_method_phid) {
        $default_method_phid = null;
        $e_method = null;
      } else if ($default_method_phid == $current_phid) {
        // If you have an invalid setting already, it's OK to retain it.
        $e_method = null;
      } else {
        if (empty($valid_methods[$default_method_phid])) {
          $e_method = pht('Invalid');
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
    // TODO: This should probably have a note about why this is bogus.
    if ($current_phid && empty($valid_methods[$current_phid])) {
      $handles = $this->loadViewerHandles(array($current_phid));
      $current_options = array(
        $current_phid => $handles[$current_phid]->getName(),
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


    $uri = $this->getApplicationURI($account->getID().'/card/new/');
    $uri = new PhutilURI($uri);
    $uri->setQueryParam('merchantID', $merchant->getID());
    $uri->setQueryParam('subscriptionID', $subscription->getID());

    $add_method_button = phutil_tag(
      'a',
      array(
        'href' => $uri,
        'class' => 'button grey',
      ),
      pht('Add Payment Method...'));

    $form = id(new AphrontFormView())
      ->setUser($viewer)
      ->appendChild(
        id(new AphrontFormSelectControl())
          ->setName('defaultPaymentMethodPHID')
          ->setLabel(pht('Autopay With'))
          ->setValue($current_phid)
          ->setOptions($options))
      ->appendChild(
        id(new AphrontFormMarkupControl())
          ->setValue($add_method_button))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue(pht('Save Changes'))
          ->addCancelButton($view_uri));

    $box = id(new PHUIObjectBoxView())
      ->setUser($viewer)
      ->setHeaderText(pht('Edit %s', $subscription->getSubscriptionName()))
      ->setFormErrors($errors)
      ->appendChild($form);

    return $this->buildApplicationPage(
      array(
        $crumbs,
        $box,
      ),
      array(
        'title' => $title,
      ));
  }


}
