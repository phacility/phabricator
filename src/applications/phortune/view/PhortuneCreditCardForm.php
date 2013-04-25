<?php

final class PhortuneCreditCardForm {

  private $formID;
  private $scripts = array();
  private $user;

  private $cardNumberError;
  private $cardCVCError;
  private $cardExpirationError;

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function setCardExpirationError($card_expiration_error) {
    $this->cardExpirationError = $card_expiration_error;
    return $this;
  }

  public function setCardCVCError($card_cvc_error) {
    $this->cardCVCError = $card_cvc_error;
    return $this;
  }

  public function setCardNumberError($card_number_error) {
    $this->cardNumberError = $card_number_error;
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

    $form
      ->setID($form_id)
      ->appendChild(
        id(new AphrontFormMarkupControl())
        ->setLabel('')
        ->setValue(
          javelin_tag(
            'div',
            array(
              'class' => 'credit-card-logos',
              'sigil' => 'has-tooltip',
              'meta' => array(
                'tip'  => 'We support Visa, Mastercard, American Express, '.
                          'Discover, JCB, and Diners Club.',
                'size' => 440,
              )
            ))))
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setLabel('Card Number')
        ->setDisableAutocomplete(true)
        ->setSigil('number-input')
        ->setError($this->cardNumberError))
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setLabel('CVC')
        ->setDisableAutocomplete(true)
        ->setSigil('cvc-input')
        ->setError($this->cardCVCError))
      ->appendChild(
        id(new PhortuneMonthYearExpiryControl())
        ->setLabel('Expiration')
        ->setUser($this->user)
        ->setError($this->cardExpirationError));

    return $form;
  }
}
