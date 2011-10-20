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

final class PhabricatorInfrastructureTestCase
  extends PhabricatorTestCase {

  /**
   * This is more of an acceptance test case instead of a unittest. It verifies
   * that all symbols can be loaded correctly. It can catch problem like missing
   * methods in descendants of abstract base classes.
   */
  public function testEverythingImplemented() {
    // Note that we don't have a try catch block around the following because,
    // when it fails, it will cause a HPHP or PHP fatal which won't be caught
    // by try catch.
    $every_class = id(new PhutilSymbolLoader())->selectAndLoadSymbols();
  }
}

