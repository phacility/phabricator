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

final class PhabricatorFileTransformController
  extends PhabricatorFileController {

  private $transform;
  private $phid;

  public function willProcessRequest(array $data) {
    $this->transform = $data['transform'];
    $this->phid = $data['phid'];
  }

  public function processRequest() {

    $xform = id(new PhabricatorTransformedFile())
      ->loadOneWhere(
        'originalPHID = %s AND transform = %s',
        $this->phid,
        $this->transform);

    if ($xform) {
      return $this->buildTransformedFileResponse($xform);
    }

    $file = id(new PhabricatorFile())->loadOneWhere('phid = %s', $this->phid);
    if (!$file) {
      return new Aphront404Response();
    }

    $type = $file->getMimeType();

    if (!$file->isViewableInBrowser() || !$file->isTransformableImage()) {
      return $this->buildDefaultTransformation($file);
    }

    // We're essentially just building a cache here and don't need CSRF
    // protection.
    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();

    switch ($this->transform) {
      case 'thumb-220x165':
        $xformed_file = $this->executeThumbTransform($file, 220, 165);
        break;
      case 'preview-220':
        $xformed_file = $this->executePreviewTransform($file, 220);
        break;
      case 'thumb-160x120':
        $xformed_file = $this->executeThumbTransform($file, 160, 120);
        break;
      case 'thumb-60x45':
        $xformed_file = $this->executeThumbTransform($file, 60, 45);
        break;
      default:
        return new Aphront400Response();
    }

    if (!$xformed_file) {
      return new Aphront400Response();
    }

    $xform = new PhabricatorTransformedFile();
    $xform->setOriginalPHID($this->phid);
    $xform->setTransform($this->transform);
    $xform->setTransformedPHID($xformed_file->getPHID());
    $xform->save();

    return $this->buildTransformedFileResponse($xform);
  }

  private function buildDefaultTransformation(PhabricatorFile $file) {
    static $regexps = array(
      '@application/zip@'     => 'zip',
      '@image/@'              => 'image',
      '@application/pdf@'     => 'pdf',
      '@.*@'                  => 'default',
    );

    $type = $file->getMimeType();
    $prefix = 'default';
    foreach ($regexps as $regexp => $implied_prefix) {
      if (preg_match($regexp, $type)) {
        $prefix = $implied_prefix;
        break;
      }
    }

    switch ($this->transform) {
      case 'thumb-160x120':
        $suffix = '160x120';
        break;
      case 'thumb-60x45':
        $suffix = '60x45';
        break;
      default:
        throw new Exception("Unsupported transformation type!");
    }

    $path = celerity_get_resource_uri(
      "/rsrc/image/icon/fatcow/thumbnails/{$prefix}{$suffix}.png");

    return id(new AphrontRedirectResponse())
      ->setURI($path);
  }

  private function buildTransformedFileResponse(
    PhabricatorTransformedFile $xform) {

    $file = id(new PhabricatorFile())->loadOneWhere(
      'phid = %s',
      $xform->getTransformedPHID());
    if ($file) {
      $uri = $file->getBestURI();
    } else {
      $bad_phid = $xform->getTransformedPHID();
      throw new Exception(
        "Unable to load file with phid {$bad_phid}."
      );
    }

    // TODO: We could just delegate to the file view controller instead,
    // which would save the client a roundtrip, but is slightly more complex.
    return id(new AphrontRedirectResponse())->setURI($uri);
  }

  private function executePreviewTransform(PhabricatorFile $file, $size) {
    $xformer = new PhabricatorImageTransformer();
    return $xformer->executePreviewTransform($file, $size);
  }

  private function executeThumbTransform(PhabricatorFile $file, $x, $y) {
    $xformer = new PhabricatorImageTransformer();
    return $xformer->executeThumbTransform($file, $x, $y);
  }

}
