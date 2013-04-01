<?php

final class PhortuneStripePaymentFormView extends AphrontView {
  private $stripeKey;
  private $cardNumberError;
  private $cardCVCError;
  private $cardExpirationError;

  public function setStripeKey($key) {
    $this->stripeKey = $key;
    return $this;
  }
  private function getStripeKey() {
    return $this->stripeKey;
  }

  public function setCardNumberError($error) {
    $this->cardNumberError = $error;
    return $this;
  }
  private function getCardNumberError() {
    return $this->cardNumberError;
  }

  public function setCardCVCError($error) {
    $this->cardCVCError = $error;
    return $this;
  }
  private function getCardCVCError() {
    return $this->cardCVCError;
  }

  public function setCardExpirationError($error) {
    $this->cardExpirationError = $error;
    return $this;
  }
  private function getCardExpirationError() {
    return $this->cardExpirationError;
  }

  public function render() {
    $form_id = celerity_generate_unique_node_id();
    require_celerity_resource('stripe-payment-form-css');
    require_celerity_resource('aphront-tooltip-css');
    Javelin::initBehavior('phabricator-tooltips');

    $form = id(new AphrontFormView())
      ->setID($form_id)
      ->setUser($this->getUser())
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
        ->setError($this->getCardNumberError()))
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setLabel('CVC')
        ->setDisableAutocomplete(true)
        ->setSigil('cvc-input')
        ->setError($this->getCardCVCError()))
      ->appendChild(
        id(new PhortuneMonthYearExpiryControl())
        ->setLabel('Expiration')
        ->setUser($this->getUser())
        ->setError($this->getCardExpirationError()))
      ->appendChild(
        javelin_tag(
          'input',
          array(
            'hidden' => true,
            'name'   => 'stripeToken',
            'sigil'  => 'stripe-token-input',
          )))
      ->appendChild(
        javelin_tag(
          'input',
          array(
            'hidden' => true,
            'name'   => 'cardErrors',
            'sigil'  => 'card-errors-input'
          )))
      ->appendChild(
        phutil_tag(
          'input',
          array(
            'hidden' => true,
            'name'   => 'stripeKey',
            'value'  => $this->getStripeKey(),
          )))
      ->appendChild(
        id(new AphrontFormSubmitControl())
        ->setValue('Add Payment Method'));

    Javelin::initBehavior(
      'stripe-payment-form',
      array(
        'stripePublishKey' => $this->getStripeKey(),
        'root'             => $form_id,
      ));

    return $form->render();
  }
}
