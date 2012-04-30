<?php

/*
 * Copyright 2012 Facebook, Inc.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *   http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

$conn = $schema_conn;

$tables = queryfx_all(
  $conn,
  "SELECT TABLE_SCHEMA db, TABLE_NAME tbl
    FROM information_schema.TABLES s
    WHERE s.TABLE_SCHEMA LIKE %>
    AND s.TABLE_NAME != 'search_documentfield'
    AND s.ENGINE != 'InnoDB'",
    '{$NAMESPACE}_');

if (!$tables) {
  return;
}

echo "There are ".count($tables)." tables using the MyISAM engine. These will ".
     "now be converted to InnoDB. This process may take a few minutes, please ".
     "be patient.\n";

foreach ($tables as $table) {
  $name = $table['db'].'.'.$table['tbl'];
  echo "Converting {$name}...\n";
  queryfx(
    $conn,
    "ALTER TABLE %T.%T ENGINE=InnoDB",
    $table['db'],
    $table['tbl']);
}
echo "Done!\n";
