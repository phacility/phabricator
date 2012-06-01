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

/**
 * This is just a test table that unit tests can use if they need to test
 * generic database operations. It won't change and break tests and stuff, and
 * mistakes in test construction or isolation won't impact the application in
 * any way.
 */
final class HarbormasterScratchTable extends HarbormasterDAO {

  protected $data;

}
