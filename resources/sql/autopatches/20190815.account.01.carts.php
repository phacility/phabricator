<?php

$edge_type = PhortuneAccountHasMerchantEdgeType::EDGECONST;

$table = new PhortuneCart();
foreach (new LiskMigrationIterator($table) as $cart) {
  id(new PhabricatorEdgeEditor())
    ->addEdge($cart->getAccountPHID(), $edge_type, $cart->getMerchantPHID())
    ->save();
}
