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

final class AphrontFilePreviewView extends AphrontView {

  private $file;

  public function setFile(PhabricatorFile $file) {
    $this->file = $file;
    return $this;
  }

  public function render() {
    require_celerity_resource('aphront-attached-file-view-css');

    $file = $this->file;

    $img = phutil_render_tag(
      'img',
      array(
        'src'     => $file->getThumb160x120URI(),
        'width'   => 160,
        'height'  => 120,
        'title'   => $file->getName(),
      ));
    $link = phutil_render_tag(
      'a',
      array(
        'href'    => $file->getBestURI(),
        'target'  => '_blank',
      ),
      $img);

    $display_name = $file->getName();
    if (strlen($display_name) > 22) {
      $display_name =
        substr($display_name, 0, 11).
        "\xE2\x80\xA6".
        substr($display_name, -9);
    }

    return
      '<div class="aphront-file-preview-view">
        <div class="aphront-file-preview-thumb">'.
          $link.
        '</div>'.
        phutil_render_tag(
          'span',
          array(
            'title' => $file->getName(),
          ),
          phutil_escape_html($display_name)).
      '</div>';
  }

}
