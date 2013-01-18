<?php

echo "Cleaning up old Herald rule applied rows...\n";
$table = new HeraldRule();
$table->openTransaction();
$table->beginReadLocking();

$rules = $table->loadAll();
foreach ($rules as $key => $rule) {
  $first_policy = HeraldRepetitionPolicyConfig::toInt(
    HeraldRepetitionPolicyConfig::FIRST);
  if ($rule->getRepetitionPolicy() != $first_policy) {
    unset($rules[$key]);
  }
}

$conn_w = $table->establishConnection('w');

$clause = '';
if ($rules) {
  $clause = qsprintf(
    $conn_w,
    'WHERE ruleID NOT IN (%Ld)',
    mpull($rules, 'getID'));
}

echo "This may take a moment";
do {
  queryfx(
    $conn_w,
    'DELETE FROM %T %Q LIMIT 1000',
    HeraldRule::TABLE_RULE_APPLIED,
    $clause);
  echo ".";
} while ($conn_w->getAffectedRows());

$table->endReadLocking();
$table->saveTransaction();
echo "\nDone.\n";
