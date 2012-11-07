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

return array(

  // This will be the base domain for your install, and must be configured.
  // Use "https://" if you have SSL. See below for some notes.
  'phabricator.base-uri' => 'http://phab1.pushlabs.net/',
  'phabricator.timezone' => 'America/Los_Angeles',

  // Connection information for MySQL.
  'mysql.host' => 'localhost',
  'mysql.user' => 'root',
  'mysql.pass' => 'strawberry30',

  // Basic email domain configuration.
  'metamta.default-address' => 'noreply@phab1.pushlabs.net',
  'metamta.domain'          => 'phab1.pushlabs.net',

  // NOTE: Check default.conf.php for detailed explanations of all the
  // configuration options, including these.

) + phabricator_read_config_file('development');
