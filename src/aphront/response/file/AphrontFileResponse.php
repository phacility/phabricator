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

/**
 * @group aphront
 */
class AphrontFileResponse extends AphrontResponse {

  private $content;
  private $mimeType;
  private $download;

  public function setDownload($download) {
    $download = preg_replace('/[^A-Za-z0-9_.-]/', '_', $download);
    if (!strlen($download)) {
      $download = 'untitled_document.txt';
    }
    $this->download = $download;
    return $this;
  }

  public function getDownload() {
    return $this->download;
  }

  public function setMimeType($mime_type) {
    $this->mimeType = $mime_type;
    return $this;
  }

  public function getMimeType() {
    return $this->mimeType;
  }

  public function setContent($content) {
    $this->content = $content;
    return $this;
  }

  public function buildResponseString() {
    return $this->content;
  }

  public function getHeaders() {
    $headers = array(
      array('Content-Type', $this->getMimeType()),
      // Without this, IE can decide that we surely meant "text/html" when
      // delivering another content type since, you know, it looks like it's
      // probably an HTML document. This closes the security hole that policy
      // creates.
      array('X-Content-Type-Options', 'nosniff'),
    );

    if (strlen($this->getDownload())) {
      $headers[] = array('X-Download-Options', 'noopen');

      $filename = $this->getDownload();
      $headers[] = array(
        'Content-Disposition',
        'attachment; filename='.$filename,
      );
    }

    $headers = array_merge(parent::getHeaders(), $headers);
    return $headers;
  }

}
