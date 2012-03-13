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
 * @group aphront
 */
final class Aphront304Response extends AphrontResponse {

  public function getHTTPResponseCode() {
    return 304;
  }

  public function buildResponseString() {
    // IMPORTANT! According to the HTTP/1.1 spec (RFC 2616) a 304 response
    // "MUST NOT" have any content. Apache + Safari strongly agree, and
    // completely flip out and you start getting 304s for no-cache pages.
    return null;
  }

}
