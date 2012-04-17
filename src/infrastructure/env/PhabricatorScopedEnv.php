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
 * Scope guard to hold a temporary environment. See @{class:PhabricatorEnv} for
 * instructions on use.
 *
 * @task internal Internals
 * @task override Overriding Environment Configuration
 */
final class PhabricatorScopedEnv {

  private $key;

/* -(  Overriding Environment Configuration  )------------------------------- */

  /**
   * Override a configuration key in this scope, setting it to a new value.
   *
   * @param  string Key to override.
   * @param  wild   New value.
   * @return this
   *
   * @task override
   */
  public function overrideEnvConfig($key, $value) {
    PhabricatorEnv::overrideEnvConfig(
      $this->key,
      $key,
      $value);
    return $this;
  }


/* -(  Internals  )---------------------------------------------------------- */


  /**
   *
   * @task internal
   */
  public function __construct($stack_key) {
    $this->key = $stack_key;
  }


  /**
   * Release the scoped environment.
   *
   * @return void
   * @task internal
   */
  public function __destruct() {
    PhabricatorEnv::popEnvironment($this->key);
  }

}
