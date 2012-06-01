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
 * @group conduit
 */
final class ConduitException extends Exception {

  private $errorDescription;

  /**
   * Set a detailed error description. If omitted, the generic error description
   * will be used instead. This is useful to provide specific information about
   * an exception (e.g., which values were wrong in an invalid request).
   *
   * @param string Detailed error description.
   * @return this
   */
  public function setErrorDescription($error_description) {
    $this->errorDescription = $error_description;
    return $this;
  }

  /**
   * Get a detailed error description, if available.
   *
   * @return string|null Error description, if one is available.
   */
  public function getErrorDescription() {
    return $this->errorDescription;
  }

}
