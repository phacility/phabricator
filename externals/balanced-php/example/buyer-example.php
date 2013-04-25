<?php

require(__DIR__ . '/vendor/autoload.php');

Httpful\Bootstrap::init();
RESTful\Bootstrap::init();
Balanced\Bootstrap::init();

$API_KEY_SECRET = '5f4db668a5ec11e1b908026ba7e239a9';
$page = $_SERVER['REQUEST_URI'];
Balanced\Settings::$api_key = $API_KEY_SECRET;
$marketplace = Balanced\Marketplace::mine();

if ($page == '/') {
    // do nothing
} elseif ($page == '/buyer') {
    if (isset($_POST['uri']) and isset($_POST['email_address'])) {
        // create in balanced
        $email_address = $_POST['email_address'];
        $card_uri = $_POST['uri'];
        try {
            echo create_buyer($email_address, $card_uri)->uri;  
            return;
        } catch (Balanced\Errors\Error $e) {
            echo $e->getMessage();
            return;
        }
    }
}
  
function create_buyer($email_address, $card_uri) {
    $marketplace = Balanced\Marketplace::mine();
    try {
        # new buyer
        $buyer = $marketplace->createBuyer(
            $email_address,
            $card_uri);
    }
    catch (Balanced\Errors\DuplicateAccountEmailAddress $e) {
        # oops, account for $email_address already exists so just add the card
        $buyer = Balanced\Account::get($e->extras->account_uri);
        $buyer->addCard($card_uri);
    }
    return $buyer;
}

?>
<html>
<head>
    <link rel="stylesheet" href="http://twitter.github.com/bootstrap/assets/css/bootstrap.css" type="text/css">
    <style type="text/css">
    [name="marketplace_eid"] {
        width: 300px;
    }
    [name^="expiration"] {
        width: 50px;
    }
    [name="security_code"] {
        width: 50px;
    }
    code { display: block; }
    pre { color: green; }
    </style>
</head>
<body>
<h1>Balanced Sample - Collect Credit Card Information</h1>
<div class="row">
    <div class="span6">
        <form id="payment">
            <div>
                <label>Email Address</label>
                <input name="email_address" value="bob@example.com">
            </div>
            <div>
                <label>Card Number</label>
                <input name="card_number" value="4111111111111111" autocomplete="off">
            </div>
            <div>
                <label>Expiration</label>
                <input name="expiration_month" value="1"> / <input name="expiration_year" value="2020">
            </div>
            <div>
                <label>Security Code</label>
                <input name="security_code" value="123" autocomplete="off">
            </div>
            <button>Submit Payment Data</button>
        </form>
    </div>
</div>
<div id="result"></div>
<script type="text/javascript" src="https://js.balancedpayments.com/v1/balanced.js"></script>
<script type="text/javascript" src="//ajax.googleapis.com/ajax/libs/jquery/1.7.2/jquery.min.js"></script>
<script type="text/javascript">
    var marketplaceUri = '<?php echo $marketplace->uri; ?>';

    var debug = function (tag, content) {
        $('<' + tag + '>' + content + '</' + tag + '>').appendTo('#result');
    };

    try {
        balanced.init(marketplaceUri);
    } catch (e) {
        debug('code', 'You need to set the marketplaceUri variable');
    }

    function accountCreated(response) {
        debug('code', 'account create result: ' + response);
    }

    function balancedCallback(response) {
        var tag = (response.status < 300) ? 'pre' : 'code';
        debug(tag, JSON.stringify(response));
        switch (response.status) {
            case 201:
                // response.data.uri == uri of the card resource, submit to your server
                $.post('/buyer', {
                    uri: response.data.uri,
                    email_address: $('[name="email_address"]').val()
                }, accountCreated);
            case 400:
            case 403:
                // missing/malformed data - check response.error for details
                break;
            case 402:
                // we couldn't authorize the buyer's credit card - check response.error for details
                break;
            case 404:
                // your marketplace URI is incorrect
                break;
            default:
                // we did something unexpected - check response.error for details
                break;
        }
    }

    var tokenizeCard = function(e) {
        e.preventDefault();

        var $form = $('form#payment');
        var cardData = {
            card_number: $form.find('[name="card_number"]').val(),
            expiration_month: $form.find('[name="expiration_month"]').val(),
            expiration_year: $form.find('[name="expiration_year"]').val(),
            security_code: $form.find('[name="security_code"]').val()
        };

        balanced.card.create(cardData, balancedCallback);
    };

    $('#payment').submit(tokenizeCard);

    if (window.location.protocol === 'file:') {
        alert("balanced.js does not work when included in pages served over file:// URLs. Try serving this page over a webserver. Contact support@balancedpayments.com if you need assistance.");
    }
</script>
</body>
</html>
