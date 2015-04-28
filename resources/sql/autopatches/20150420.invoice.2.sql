UPDATE {$NAMESPACE}_phortune.phortune_cart
  SET isInvoice = 1 WHERE subscriptionPHID IS NOT NULL;
