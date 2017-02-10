/**
 * @provides javelin-behavior-stripe-payment-form
 * @requires javelin-behavior
 *           javelin-dom
 *           phortune-credit-card-form
 */

JX.behavior('stripe-payment-form', function(config) {

  function onsubmit(card_data) {
    var errors = [];

    Stripe.setPublishableKey(config.stripePublishableKey);

    if (!Stripe.validateCardNumber(card_data.number)) {
      errors.push('cc:invalid:number');
    }

    if (!Stripe.validateCVC(card_data.cvc)) {
      errors.push('cc:invalid:cvc');
    }

    if (!Stripe.validateExpiry(card_data.month, card_data.year)) {
      errors.push('cc:invalid:expiry');
    }

    if (errors.length) {
      ccform.submitForm(errors);
      return;
    }

    var data = {
      number: card_data.number,
      cvc: card_data.cvc,
      exp_month: card_data.month,
      exp_year: card_data.year
    };

    Stripe.createToken(data, onresponse);
  }

  function onresponse(status, response) {
    var errors = [];
    var token = null;
    if (status != 200) {
      errors.push('cc:stripe:http:' + status);
    } else if (response.error) {
      errors.push('cc:stripe:error:' + response.error.type);
    } else {
      token = response.id;
    }

    ccform.submitForm(errors, {stripeCardToken: token});
  }

  var ccform = new JX.PhortuneCreditCardForm(JX.$(config.formID), onsubmit);
});
