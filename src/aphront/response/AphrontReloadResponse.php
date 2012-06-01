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
 * When actions happen over a JX.Workflow, we may want to reload the page
 * if the action is javascript-driven but redirect if it isn't. This preserves
 * query parameters in the javascript case. A reload response behaves like
 * a redirect response but causes a page reload when received via workflow.
 *
 * @group aphront
 */
final class AphrontReloadResponse extends AphrontRedirectResponse {

  public function getURI() {
    if ($this->getRequest()->isAjax()) {
      return null;
    } else {
      return parent::getURI();
    }
  }

}
