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

$args = new PhutilArgumentParser($argv);
$args->setTagline('manually discover working copies');
$args->setSynopsis(<<<EOHELP
**discover.php** [__options__] __repository-callsign-or-phid ...__
    Manually discover commits in working copies for the named repositories.
EOHELP
);
$args->parseStandardArguments();
$args->parse(
  array(
    array(
      'name'      => 'repositories',
      'wildcard'  => true,
    ),
  ));

$repo_names = $args->getArg('repositories');
if (!$repo_names) {
  echo "Specify one or more repositories to pull, by callsign or PHID.\n";
  exit(1);
}

$repos = PhabricatorRepository::loadAllByPHIDOrCallsign($repo_names);
foreach ($repos as $repo) {
  $callsign = $repo->getCallsign();
  echo "Discovering '{$callsign}'...\n";
  PhabricatorRepositoryPullLocalDaemon::discoverRepository($repo);
}
echo "Done.\n";
