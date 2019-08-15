<?php

$edge_type = PhortuneAccountHasMerchantEdgeType::EDGECONST;

$table = new PhortuneSubscription();
foreach (new LiskMigrationIterator($table) as $sub) {
  id(new PhabricatorEdgeEditor())
    ->addEdge($sub->getAccountPHID(), $edge_type, $sub->getMerchantPHID())
    ->save();
}
