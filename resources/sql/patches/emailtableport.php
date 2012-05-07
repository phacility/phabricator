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

echo "Migrating user emails...\n";

$table  = new PhabricatorUser();
$conn   = $table->establishConnection('r');

$emails = queryfx_all(
  $conn,
  'SELECT phid, email FROM %T',
  $table->getTableName());
$emails = ipull($emails, 'email', 'phid');

$etable = new PhabricatorUserEmail();
$econn  = $etable->establishConnection('w');

foreach ($emails as $phid => $email) {

  // NOTE: Grandfather all existing email in as primary / verified. We generate
  // verification codes because they are used for password resets, etc.

  echo "Migrating '{$phid}'...\n";
  queryfx(
    $econn,
    'INSERT INTO %T (userPHID, address, verificationCode, isVerified, isPrimary)
      VALUES (%s, %s, %s, 1, 1)',
    $etable->getTableName(),
    $phid,
    $email,
    Filesystem::readRandomCharacters(24));
}

echo "Done.\n";
