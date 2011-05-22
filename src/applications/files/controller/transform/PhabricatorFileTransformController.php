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

class PhabricatorFileTransformController extends PhabricatorFileController {

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

    if (!$file->isViewableInBrowser()) {
      return new Aphront400Response();
    }

    if (!$file->isTransformableImage()) {
      return new Aphront400Response();
    }

    switch ($this->transform) {
      case 'thumb-160x120':
        $xformed_file = $this->executeThumbTransform($file, 160, 120);
        break;
      case 'thumb-60x45':
        $xformed_file = $this->executeThumbTransform($file, 60, 45);
        break;
      case 'profile-50x50':
        $xformed_file = $this->executeProfile50x50Transform($file);
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

  private function buildTransformedFileResponse(
    PhabricatorTransformedFile $xform) {

    // TODO: We could just delegate to the file view controller instead,
    // which would save the client a roundtrip, but is slightly more complex.
    return id(new AphrontRedirectResponse())->setURI(
      PhabricatorFileURI::getViewURIForPHID($xform->getTransformedPHID()));
  }

  private function executeProfile50x50Transform(PhabricatorFile $file) {
    $data = $file->loadFileData();
    $jpeg = $this->crudelyScaleTo($data, 50, 50);

    return PhabricatorFile::newFromFileData($jpeg, array(
      'name' => 'profile-'.$file->getName(),
    ));
  }

  private function executeThumbTransform(PhabricatorFile $file, $x, $y) {
    $data = $file->loadFileData();
    $jpeg = $this->crudelyScaleTo($data, $x, $y);
    return PhabricatorFile::newFromFileData($jpeg, array(
      'name' => 'thumb-'.$file->getName(),
    ));
  }

  /**
   * Very crudely scale an image up or down to an exact size.
   */
  private function crudelyScaleTo($data, $dx, $dy) {
    $src = imagecreatefromstring($data);
    $x = imagesx($src);
    $y = imagesy($src);

    $scale = min($x / $dx, $y / $dy);
    $dst = imagecreatetruecolor($dx, $dy);

    imagecopyresampled(
      $dst,
      $src,
      0, 0,
      0, 0,
      $dx, $dy,
      $scale * $dx, $scale * $dy);

    ob_start();
    imagejpeg($dst);
    return ob_get_clean();
  }

}
