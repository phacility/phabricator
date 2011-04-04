<?php

/*
 * Copyright 2011 Facebook, Inc.
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

$include_path = ini_get('include_path');
ini_set('include_path', $include_path.':'.dirname(__FILE__).'/../../');
@require_once 'libphutil/src/__phutil_library_init__.php';
if (!@constant('__LIBPHUTIL__')) {
  echo "ERROR: Unable to load libphutil. Update your PHP 'include_path' to ".
       "include the parent directory of libphutil/.\n";
  exit(1);
}

phutil_load_library(dirname(__FILE__).'/../src/');
