<?php

echo pht('Migrating Maniphest custom field configuration...')."\n";

$old_key = 'maniphest.custom-fields';
$new_key = 'maniphest.custom-field-definitions';

if (PhabricatorEnv::getEnvConfig($new_key)) {
  echo pht('Skipping migration, new data is already set.')."\n";
  return;
}

$old = PhabricatorEnv::getEnvConfigIfExists($old_key);
if (!$old) {
  echo pht('Skipping migration, old data does not exist.')."\n";
  return;
}

$new = array();
foreach ($old as $field_key => $spec) {
  $new_spec = array();

  foreach ($spec as $key => $value) {
    switch ($key) {
      case 'label':
        $new_spec['name'] = $value;
        break;
      case 'required':
      case 'default':
      case 'caption':
      case 'options':
        $new_spec[$key] = $value;
        break;
      case 'checkbox-label':
        $new_spec['strings']['edit.checkbox'] = $value;
        break;
      case 'checkbox-value':
        $new_spec['strings']['view.yes'] = $value;
        break;
      case 'type':
        switch ($value) {
          case 'string':
            $value = 'text';
            break;
          case 'user':
            $value = 'users';
            $new_spec['limit'] = 1;
            break;
        }
        $new_spec['type'] = $value;
        break;
      case 'copy':
        $new_spec['copy'] = $value;
        break;
    }
  }

  $new[$field_key] = $new_spec;
}

PhabricatorConfigEntry::loadConfigEntry($new_key)
  ->setIsDeleted(0)
  ->setValue($new)
  ->save();

echo pht('Done.')."\n";
