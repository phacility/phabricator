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

final class AphrontAttachedFileView extends AphrontView {

  private $file;

  public function setFile(PhabricatorFile $file) {
    $this->file = $file;
    return $this;
  }

  public function render() {
    require_celerity_resource('aphront-attached-file-view-css');

    $file = $this->file;
    $phid = $file->getPHID();

    $thumb = phutil_render_tag(
      'img',
      array(
        'src'     => $file->getThumb60x45URI(),
        'width'   => 60,
        'height'  => 45,
      ));

    $name = phutil_render_tag(
      'a',
      array(
        'href'    => $file->getViewURI(),
        'target'  => '_blank',
      ),
      phutil_escape_html($file->getName()));
    $size = number_format($file->getByteSize()).' bytes';

    $remove = javelin_render_tag(
      'a',
      array(
        'class' => 'button grey',
        'sigil' => 'aphront-attached-file-view-remove',
        // NOTE: Using 'ref' here instead of 'meta' because the file upload
        // endpoint doesn't receive request metadata and thus can't generate
        // a valid response with node metadata.
        'ref'   => $file->getPHID(),
      ),
      "\xE2\x9C\x96"); // "Heavy Multiplication X"

    return
      '<table class="aphront-attached-file-view">
        <tr>
          <td>'.$thumb.'</td>
          <th><strong>'.$name.'</strong><br />'.$size.'</th>
          <td class="aphront-attached-file-view-remove">'.$remove.'</td>
        </tr>
      </table>';
  }

}
