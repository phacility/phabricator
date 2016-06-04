<?php


$table = new PhabricatorUserPreferences();
$conn_w = $table->establishConnection('w');

// Convert "Mail Format", "Re Prefix" and "Vary Subjects" mail settings to
// string constants to avoid weird stuff where we store "true" and "false" as
// strings in the database.

// Each of these keys will be converted to the first value if present and
// truthy, or the second value if present and falsey.
$remap = array(
  'html-emails' => array('html', 'text'),
  're-prefix' => array('re', 'none'),
  'vary-subject' => array('vary', 'static'),
);

foreach (new LiskMigrationIterator($table) as $row) {
  $dict = $row->getPreferences();

  $should_update = false;
  foreach ($remap as $key => $value) {
    if (isset($dict[$key])) {
      if ($dict[$key]) {
        $dict[$key] = $value[0];
      } else {
        $dict[$key] = $value[1];
      }
      $should_update = true;
    }
  }

  if (!$should_update) {
    continue;
  }

  queryfx(
    $conn_w,
    'UPDATE %T SET preferences = %s WHERE id = %d',
    $table->getTableName(),
    phutil_json_encode($dict),
    $row->getID());
}

$prefs_key = PhabricatorUserPreferencesCacheType::KEY_PREFERENCES;
PhabricatorUserCache::clearCacheForAllUsers($prefs_key);
