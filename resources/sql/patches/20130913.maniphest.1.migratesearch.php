<?php

// NOTE: If you need to make any significant updates to this to deal with
// future changes to objects, it's probably better to just wipe the whole
// migration. This feature doesn't see overwhelming amounts of use, and users
// who do use it can recreate their queries fairly easily with the new
// interface. By the time this needs to be updated, the vast majority of
// users who it impacts will likely have migrated their data already.

$table = new ManiphestTask();
$conn_w = $table->establishConnection('w');

$search_table = new PhabricatorSearchQuery();
$search_conn_w = $search_table->establishConnection('w');

// See T1812. This is an old status constant from the time of this migration.
$old_open_status = 0;

echo "Updating saved Maniphest queries...\n";
$rows = new LiskRawMigrationIterator($conn_w, 'maniphest_savedquery');
foreach ($rows as $row) {
  $id = $row['id'];
  echo "Updating query {$id}...\n";

  $data = queryfx_one(
    $search_conn_w,
    'SELECT parameters FROM %T WHERE queryKey = %s',
    $search_table->getTableName(),
    $row['queryKey']);
  if (!$data) {
    echo "Unable to locate query data.\n";
    continue;
  }

  $data = json_decode($data['parameters'], true);
  if (!is_array($data)) {
    echo "Unable to decode query data.\n";
    continue;
  }

  if (idx($data, 'view') != 'custom') {
    echo "Query is not a custom query.\n";
    continue;
  }

  $new_data = array(
    'limit' => 1000,
  );

  if (isset($data['lowPriority']) || isset($data['highPriority'])) {
    $lo = idx($data, 'lowPriority');
    $hi = idx($data, 'highPriority');

    $priorities = array();
    $all = ManiphestTaskPriority::getTaskPriorityMap();
    foreach ($all as $pri => $name) {
      if (($lo !== null) && ($pri < $lo)) {
        continue;
      }
      if (($hi !== null) && ($pri > $hi)) {
        continue;
      }
      $priorities[] = $pri;
    }

    if (count($priorities) != count($all)) {
      $new_data['priorities'] = $priorities;
    }
  }

  foreach ($data as $key => $value) {
    switch ($key) {
      case 'fullTextSearch':
        if (strlen($value)) {
          $new_data['fulltext'] = $value;
        }
        break;
      case 'userPHIDs':
        // This was (I think?) one-off data provied to specific hard-coded
        // queries.
        break;
      case 'projectPHIDs':
        foreach ($value as $k => $v) {
          if ($v === null || $v === ManiphestTaskOwner::PROJECT_NO_PROJECT) {
            $new_data['withNoProject'] = true;
            unset($value[$k]);
            break;
          }
        }
        if ($value) {
          $new_data['allProjectPHIDs'] = $value;
        }
        break;
      case 'anyProjectPHIDs':
        if ($value) {
          $new_data['anyProjectPHIDs'] = $value;
        }
        break;
      case 'anyUserProjectPHIDs':
        if ($value) {
          $new_data['userProjectPHIDs'] = $value;
        }
        break;
      case 'excludeProjectPHIDs':
        if ($value) {
          $new_data['excludeProjectPHIDs'] = $value;
        }
        break;
      case 'ownerPHIDs':
        foreach ($value as $k => $v) {
          if ($v === null || $v === ManiphestTaskOwner::OWNER_UP_FOR_GRABS) {
            $new_data['withUnassigned'] = true;
            unset($value[$k]);
            break;
          }
        }
        if ($value) {
          $new_data['assignedPHIDs'] = $value;
        }
        break;
      case 'authorPHIDs':
        if ($value) {
          $new_data['authorPHIDs'] = $value;
        }
        break;
      case 'taskIDs':
        if ($value) {
          $new_data['ids'] = $value;
        }
        break;
      case 'status':
        $include_open = !empty($value['open']);
        $include_closed = !empty($value['closed']);

        if ($include_open xor $include_closed) {
          if ($include_open) {
            $new_data['statuses'] = array(
              $old_open_status,
            );
          } else {
            $statuses = array();
            foreach (ManiphestTaskStatus::getTaskStatusMap() as $status => $n) {
              if ($status != $old_open_status) {
                $statuses[] = $status;
              }
            }
            $new_data['statuses'] = $statuses;
          }
        }
        break;
      case 'order':
        $map = array(
          'priority' => 'priority',
          'updated' => 'updated',
          'created' => 'created',
          'title' => 'title',
        );
        if (isset($map[$value])) {
          $new_data['order'] = $map[$value];
        } else {
          $new_data['order'] = 'priority';
        }
        break;
      case 'group':
        $map = array(
          'priority' => 'priority',
          'owner' => 'assigned',
          'status' => 'status',
          'project' => 'project',
          'none' => 'none',
        );
        if (isset($map[$value])) {
          $new_data['group'] = $map[$value];
        } else {
          $new_data['group'] = 'priority';
        }
        break;
    }
  }

  $saved = id(new PhabricatorSavedQuery())
    ->setEngineClassName('ManiphestTaskSearchEngine');

  foreach ($new_data as $key => $value) {
    $saved->setParameter($key, $value);
  }

  try {
    $saved->save();
  } catch (AphrontDuplicateKeyQueryException $ex) {
    // Ignore this, we just have duplicate saved queries.
  }

  $named = id(new PhabricatorNamedQuery())
    ->setEngineClassName('ManiphestTaskSearchEngine')
    ->setQueryKey($saved->getQueryKey())
    ->setQueryName($row['name'])
    ->setUserPHID($row['userPHID']);

  try {
    $named->save();
  } catch (Exception $ex) {
    // The user already has this query under another name. This can occur if
    // the migration runs twice.
    echo "Failed to save named query.\n";
    continue;
  }

  echo "OK.\n";
}

echo "Done.\n";
