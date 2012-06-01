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

final class PhabricatorAphlictTestPageController
  extends PhabricatorNotificationsController {

  public function processRequest() {

    $instructions = '<h1>Check Your Javascript Console!</h1>';

    $object_id = 'aphlictswfobject';

    $content = phutil_render_tag(
      'object',
      array(
        'classid' => 'clsid:d27cdb6e-ae6d-11cf-96b8-444553540000',
      ),
      '<param name="movie" value="/rsrc/swf/aphlict.swf" />'.
      '<param name="allowScriptAccess" value="always" />'.
      '<embed src="/rsrc/swf/aphlict.swf" id="'.$object_id.'"></embed>');

    Javelin::initBehavior(
      'aphlict-listen',
      array(
        'id'      => $object_id,
        'server'  => '127.0.0.1',
        'port'    => 2600,
      ));

    return $this->buildStandardPageResponse(
      array(
        $instructions,
        $content,
      ),
      array(
        'title' => 'Aphlict Test Page',
      ));
  }


}
