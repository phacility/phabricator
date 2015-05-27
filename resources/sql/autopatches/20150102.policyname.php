<?php

$policies = array(
  'Administrators',
  'LegalpadSignature',
  'LunarPhase',
  'Projects',
  'Users',
);
$map = array();

foreach ($policies as $policy) {
  $old_name = "PhabricatorPolicyRule{$policy}";
  $new_name = "Phabricator{$policy}PolicyRule";
  $map[$old_name] = $new_name;
}

echo pht('Migrating policies...')."\n";
$table = new PhabricatorPolicy();
$conn_w = $table->establishConnection('w');

foreach (new LiskMigrationIterator($table) as $policy) {
  $old_rules = $policy->getRules();
  $new_rules = array();

  foreach ($old_rules as $rule) {
    $existing_rule = $rule['rule'];
    $rule['rule'] = idx($map, $existing_rule, $existing_rule);
    $new_rules[] = $rule;
  }

  queryfx(
    $conn_w,
    'UPDATE %T SET rules = %s WHERE id = %d',
    $table->getTableName(),
    json_encode($new_rules),
    $policy->getID());
}
