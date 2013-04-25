/**
 * @provides javelin-behavior-stripe-payment-form
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-json
 *           javelin-workflow
 *           phortune-credit-card-form
 */

JX.behavior('stripe-payment-form', function(config) {
  Stripe.setPublishableKey(config.stripePublishableKey);

  var root = JX.$(config.formID);
  var ccform = new JX.PhortuneCreditCardForm(root);

  var onsubmit = function(e) {
    e.kill();

    // validate the card data with Stripe client API and submit the form
    // with any detected errors
    var cardData = ccform.getCardData();
    var errors = [];

    if (!Stripe.validateCardNumber(cardData.number)) {
      errors.push('number');
    }

    if (!Stripe.validateCVC(cardData.cvc)) {
      errors.push('cvc');
    }

    if (!Stripe.validateExpiry(cardData.month, cardData.year)) {
      errors.push('expiry');
    }

    if (errors.length) {
      JX.Workflow
        .newFromForm(root, {cardErrors: JX.JSON.stringify(errors)})
        .start();
      return;
    }

    var data = {
      number: cardData.number,
      cvc: cardData.cvc,
      exp_month: cardData.month,
      exp_year: cardData.year
    };

    Stripe.createToken(data, onresponse);
  }

  var onresponse = function(status, response) {
    var errors = [];
    var token = null;
    if (response.error) {
      errors = [response.error.type];
    } else {
      token = response.id;
    }

    JX.Workflow
      .newFromForm(root, {cardErrors: errors, stripeToken: token})
      .start();
  }

  JX.DOM.listen(root, 'submit', null, onsubmit);
});
