UPDATE {$NAMESPACE}_phortune.phortune_cart
  SET status = 'cart:ready' WHERE status = '';
