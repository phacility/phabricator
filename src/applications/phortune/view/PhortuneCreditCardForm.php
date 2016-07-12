<?php

final class PhortuneCreditCardForm extends Phobject {

  private $formID;
  private $scripts = array();
  private $user;
  private $errors = array();

  private $cardNumberError;
  private $cardCVCError;
  private $cardExpirationError;
  private $securityAssurance;

  public function setSecurityAssurance($security_assurance) {
    $this->securityAssurance = $security_assurance;
    return $this;
  }

  public function getSecurityAssurance() {
    return $this->securityAssurance;
  }

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function setErrors(array $errors) {
    $this->errors = $errors;
    return $this;
  }

  public function addScript($script_uri) {
    $this->scripts[] = $script_uri;
    return $this;
  }

  public function getFormID() {
    if (!$this->formID) {
      $this->formID = celerity_generate_unique_node_id();
    }
    return $this->formID;
  }

  public function buildForm() {
    $form_id = $this->getFormID();

    require_celerity_resource('phortune-credit-card-form-css');
    require_celerity_resource('phortune-credit-card-form');

    require_celerity_resource('aphront-tooltip-css');
    Javelin::initBehavior('phabricator-tooltips');

    $form = new AphrontFormView();

    foreach ($this->scripts as $script) {
      $form->appendChild(
        phutil_tag(
          'script',
          array(
            'type' => 'text/javascript',
            'src'  => $script,
          )));
    }

    $errors = $this->errors;
    $e_number = isset($errors[PhortuneErrCode::ERR_CC_INVALID_NUMBER])
      ? pht('Invalid')
      : null;

    $e_cvc = isset($errors[PhortuneErrCode::ERR_CC_INVALID_CVC])
      ? pht('Invalid')
      : null;

    $e_expiry = isset($errors[PhortuneErrCode::ERR_CC_INVALID_EXPIRY])
      ? pht('Invalid')
      : null;

    $form
      ->setID($form_id)
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('Card Number'))
          ->setDisableAutocomplete(true)
          ->setSigil('number-input')
          ->setError($e_number))
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel(pht('CVC'))
          ->setDisableAutocomplete(true)
          ->addClass('aphront-form-cvc-input')
          ->setSigil('cvc-input')
          ->setError($e_cvc))
      ->appendChild(
        id(new PhortuneMonthYearExpiryControl())
          ->setLabel(pht('Expiration'))
          ->setUser($this->user)
          ->setError($e_expiry));

    $assurance = $this->getSecurityAssurance();
    if ($assurance) {
      $assurance = phutil_tag(
        'div',
        array(
          'class' => 'phortune-security-assurance',
        ),
        array(
          id(new PHUIIconView())
            ->setIcon('fa-lock grey'),
          ' ',
          $assurance,
        ));

      $form->appendChild(
        id(new AphrontFormMarkupControl())
          ->setValue($assurance));
    }

    return $form;
  }
}
