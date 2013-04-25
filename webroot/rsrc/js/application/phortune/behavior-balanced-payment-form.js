/**
 * @provides javelin-behavior-balanced-payment-form
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-json
 *           javelin-workflow
 *           phortune-credit-card-form
 */

JX.behavior('balanced-payment-form', function(config) {
  balanced.init(config.balancedMarketplaceURI);

  var root = JX.$(config.formID);
  var ccform = new JX.PhortuneCreditCardForm(root);

  var onsubmit = function(e) {
    e.kill();

    var cardData = ccform.getCardData();
    var errors = [];

    if (!balanced.card.isCardNumberValid(cardData.number)) {
      errors.push('number');
    }

    if (!balanced.card.isSecurityCodeValid(cardData.number, cardData.cvc)) {
      errors.push('cvc');
    }

    if (!balanced.card.isExpiryValid(cardData.month, cardData.year)) {
      errors.push('expiry');
    }

    if (errors.length) {
      JX.Workflow
        .newFromForm(root, {cardErrors: JX.JSON.stringify(errors)})
        .start();
      return;
    }

    var data = {
      card_number: cardData.number,
      security_code: cardData.cvc,
      expiration_month: cardData.month,
      expiration_year: cardData.year
    };

    balanced.card.create(data, onresponse);
  }

  var onresponse = function(response) {

    var errors = [];
    if (response.error) {
      errors = [response.error.type];
    } else if (response.status != 201) {
      errors = ['balanced:' + response.status];
    }

    var params = {
      cardErrors: JX.JSON.stringify(errors),
      balancedCardData: JX.JSON.stringify(response.data)
    };

    JX.Workflow
      .newFromForm(root, params)
      .start();
  }

  JX.DOM.listen(root, 'submit', null, onsubmit);
});
