#!/usr/bin/env php
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

$root = dirname(dirname(dirname(__FILE__)));
require_once $root.'/scripts/__init_script__.php';

phutil_require_module('phutil', 'console');

$revision = new DifferentialRevision();

$empty_revisions = queryfx_all(
  $revision->establishConnection('r'),
  'select distinct r.id from differential_revision r left join '.
  'differential_diff d on r.id=d.revisionID where d.revisionID is NULL');

$empty_revisions = ipull($empty_revisions, 'id');

if (!$empty_revisions) {
  echo "No empty revisions found.\n";
  exit(0);
}

echo phutil_console_wrap(
  "The following revision don't contain any diff:\n".
    implode(', ', $empty_revisions));

if (!phutil_console_confirm('Do you want to delete them?')) {
  echo "Cancelled.\n";
  exit(1);
}

foreach ($empty_revisions as $revision_id) {
  $revision = id(new DifferentialRevision())->load($revision_id);
  $revision->delete();
}

echo "Done.\n";
