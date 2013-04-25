/**
 * @provides javelin-behavior-balanced-payment-form
 * @requires javelin-behavior
 *           javelin-dom
 *           phortune-credit-card-form
 */

JX.behavior('balanced-payment-form', function(config) {
  balanced.init(config.balancedMarketplaceURI);

  var ccform = new JX.PhortuneCreditCardForm(JX.$(config.formID), onsubmit);

  function onsubmit(card_data) {
    var errors = [];

    if (!balanced.card.isCardNumberValid(card_data.number)) {
      errors.push('cc:invalid:number');
    }

    if (!balanced.card.isSecurityCodeValid(card_data.number, card_data.cvc)) {
      errors.push('cc:invalid:cvc');
    }

    if (!balanced.card.isExpiryValid(card_data.month, card_data.year)) {
      errors.push('cc:invalid:expiry');
    }

    if (errors.length) {
      ccform.submitForm(errors);
      return;
    }

    var data = {
      card_number: card_data.number,
      security_code: card_data.cvc,
      expiration_month: card_data.month,
      expiration_year: card_data.year
    };

    balanced.card.create(data, onresponse);
  }

  function onresponse(response) {
    var token = null;
    var errors = [];

    if (response.error) {
      errors = ['cc:balanced:error:' + response.error.type];
    } else if (response.status != 201) {
      errors = ['cc:balanced:http:' + response.status];
    } else {
      token = response.data.uri;
    }

    ccform.submitForm(errors, {balancedMarketplaceURI: token});
  }
});
