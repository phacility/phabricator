/**
 * @provides javelin-behavior-stripe-payment-form
 * @requires javelin-behavior
 *           javelin-dom
 *           javelin-json
 *           javelin-workflow
 *           stripe-core
 */

JX.behavior('stripe-payment-form', function(config) {
  Stripe.setPublishableKey(config.stripePublishKey);

  var root        = JX.$(config.root);
  var cardErrors  = JX.DOM.find(root, 'input', 'card-errors-input');
  var stripeToken = JX.DOM.find(root, 'input', 'stripe-token-input');

  var getCardData = function() {
    return {
      number : JX.DOM.find(root, 'input',  'number-input').value,
      cvc    : JX.DOM.find(root, 'input',  'cvc-input'   ).value,
      month  : JX.DOM.find(root, 'select', 'month-input' ).value,
      year   : JX.DOM.find(root, 'select', 'year-input'  ).value
    };
  }

  var stripeErrorObject = function(type) {
    var errorPre  = 'Stripe (our payments provider) has detected your card ';
    var errorPost = ' is invalid.';
    var msg       = '';
    var result    = {};

    switch (type) {
      case 'number':
        msg = errorPre + 'number' + errorPost;
        break;
      case 'cvc':
        msg = errorPre + 'CVC' + errorPost;
        break;
      case 'expiry':
        msg = errorPre + 'expiration date' + errorPost;
        break;
      case 'stripe':
        msg = 'Stripe (our payments provider) is experiencing issues. ' +
              'Please try again.';
        break;
      case 'invalid_request':
      default:
        msg = 'Unknown error.';
              // TODO - how best report bugs? would be good to get
              // user feedback since this shouldn't happen!
        break;
    }

    result[type] = msg;
    return result;
  }

  var onsubmit = function(e) {
    e.kill();

    // validate the card data with Stripe client API and submit the form
    // with any detected errors
    var cardData = getCardData();
    var errors   = [];
    if (!Stripe.validateCardNumber(cardData.number)) {
      errors.push(stripeErrorObject('number'));
    }
    if (!Stripe.validateCVC(cardData.cvc)) {
      errors.push(stripeErrorObject('cvc'));
    }
    if (!Stripe.validateExpiry(cardData.month,
                               cardData.year)) {
      errors.push(stripeErrorObject('expiry'));
    }
    if (errors.length != 0) {
      cardErrors.value = JX.JSON.stringify(errors);

      JX.Workflow.newFromForm(root)
        .start();

      return;
    }

    // no errors detected so contact Stripe asynchronously
    var submitData = {
      number    : cardData.number,
      cvc       : cardData.cvc,
      exp_month : cardData.month,
      exp_year  : cardData.year
    };
    Stripe.createToken(submitData, stripeResponseHandler);
    return false;
  }

  var stripeResponseHandler = function(status, response) {
    if (response.error) {
      var errors = [];
      switch (response.error.type) {
        case 'card_error':
          var error = {};
          error[response.error.code] = response.error.message;
          errors.push(error);
          break;
        case 'invalid_request_error':
          errors.push(stripeErrorObject('invalid_request'));
          break;
        case 'api_error':
        default:
          errors.push(stripeErrorObject('stripe'));
          break;
      }
      cardErrors.value = JX.JSON.stringify(errors);
    } else {
      // success - we can use the token to create a customer object with
      // Stripe and let the billing commence!
      var token = response['id'];
      cardErrors.value = '[]';
      stripeToken.value = token;
    }

    JX.Workflow.newFromForm(root)
      .start();
  }

  JX.DOM.listen(root, 'submit', null, onsubmit);
});
