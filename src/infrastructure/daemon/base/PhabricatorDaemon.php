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

abstract class PhabricatorDaemon extends PhutilDaemon {

  protected function willRun() {
    parent::willRun();

    // This stores unbounded amounts of log data; make it discard instead so
    // that daemons do not require unbounded amounts of memory.
    DarkConsoleErrorLogPluginAPI::enableDiscardMode();

    // Also accumulates potentially unlimited amounts of data.
    DarkConsoleEventPluginAPI::enableDiscardMode();

    $phabricator = phutil_get_library_root('phabricator');
    $root = dirname($phabricator);
    require_once $root.'/scripts/__init_script__.php';
  }
}
