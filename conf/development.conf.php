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

  'darkconsole.enabled'             => true,
  'celerity.force-disk-reads'       => true,
  'phabricator.show-stack-traces'   => true,
  'phabricator.show-error-callout'  => true,
  'celerity.minify'                 => false,

) + phabricator_read_config_file('default');
