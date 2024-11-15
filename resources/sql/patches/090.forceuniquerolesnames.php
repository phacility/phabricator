<?php

echo pht('Ensuring role names are unique enough...')."\n";
$table = new PhabricatorRole();
$table->openTransaction();
$table->beginReadLocking();

$roles = $table->loadAll();

$slug_map = array();

foreach ($roles as $role) {
  $slug = PhabricatorSlug::normalizeProjectSlug($role->getName());

  if (!strlen($slug)) {
    $role_id = $role->getID();
    echo pht("Role #%d doesn't have a meaningful name...", $role_id)."\n";
    $role->setName(trim(pht('Unnamed Role %s', $role->getName())));
  }

  $slug_map[$slug][] = $role->getID();
}


foreach ($slug_map as $slug => $similar) {
  if (count($similar) <= 1) {
    continue;
  }
  echo pht("Too many roles are similar to '%s'...", $slug)."\n";

  foreach (array_slice($similar, 1, null, true) as $key => $role_id) {
    $role = $roles[$role_id];
    $old_name = $role->getName();
    $new_name = rename_role($role, $roles);

    echo pht(
      "Renaming role #%d from '%s' to '%s'.\n",
      $role_id,
      $old_name,
      $new_name);
    $role->setName($new_name);
  }
}

$update = $roles;
while ($update) {
  $size = count($update);
  foreach ($update as $key => $role) {
    $id = $role->getID();
    $name = $role->getName();

    $slug = PhabricatorSlug::normalizeProjectSlug($name).'/';

    echo pht("Updating role #%d '%s' (%s)... ", $id, $name, $slug);
    try {
      queryfx(
        $role->establishConnection('w'),
        'UPDATE %T SET name = %s, phrictionSlug = %s WHERE id = %d',
        $role->getTableName(),
        $name,
        $slug,
        $role->getID());
      unset($update[$key]);
      echo pht('OKAY')."\n";
    } catch (AphrontDuplicateKeyQueryException $ex) {
      echo pht('Failed, will retry.')."\n";
    }
  }
  if (count($update) == $size) {
    throw new Exception(
      pht(
        'Failed to make any progress while updating roles. Schema upgrade '.
        'has failed. Go manually fix your role names to be unique '.
        '(they are probably ridiculous?) and then try again.'));
  }
}

$table->endReadLocking();
$table->saveTransaction();
echo pht('Done.')."\n";


/**
 * Rename the role so that it has a unique slug, by appending (2), (3), etc.
 * to its name.
 */
function rename_role($role, $roles) {
  $suffix = 2;
  while (true) {
    $new_name = $role->getName().' ('.$suffix.')';

    $new_slug = PhabricatorSlug::normalizeProjectSlug($new_name).'/';

    $okay = true;
    foreach ($roles as $other) {
      if ($other->getID() == $role->getID()) {
        continue;
      }

      $other_slug = PhabricatorSlug::normalizeProjectSlug($other->getName());
      if ($other_slug == $new_slug) {
        $okay = false;
        break;
      }
    }
    if ($okay) {
      break;
    } else {
      $suffix++;
    }
  }

  return $new_name;
}
