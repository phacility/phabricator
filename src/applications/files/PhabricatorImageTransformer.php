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

final class PhabricatorImageTransformer {

  public function executeThumbTransform(
    PhabricatorFile $file,
    $x,
    $y) {

    $image = $this->crudelyScaleTo($file, $x, $y);

    return PhabricatorFile::newFromFileData(
      $image,
      array(
        'name' => 'thumb-'.$file->getName(),
      ));
  }

  public function executeProfileTransform(
    PhabricatorFile $file,
    $x,
    $min_y,
    $max_y) {

    $image = $this->crudelyCropTo($file, $x, $min_y, $max_y);

    return PhabricatorFile::newFromFileData(
      $image,
      array(
        'name' => 'profile-'.$file->getName(),
      ));
  }

  public function executePreviewTransform(
    PhabricatorFile $file,
    $size) {

    $image = $this->generatePreview($file, $size);

    return PhabricatorFile::newFromFileData(
      $image,
      array(
        'name' => 'preview-'.$file->getName(),
      ));
  }


  private function crudelyCropTo(PhabricatorFile $file, $x, $min_y, $max_y) {
    $data = $file->loadFileData();
    $img = imagecreatefromstring($data);
    $sx = imagesx($img);
    $sy = imagesy($img);

    $scaled_y = ($x / $sx) * $sy;
    if ($scaled_y > $max_y) {
      // This image is very tall and thin.
      $scaled_y = $max_y;
    } else if ($scaled_y < $min_y) {
      // This image is very short and wide.
      $scaled_y = $min_y;
    }

    $img = $this->applyScaleTo(
      $img,
      $x,
      $scaled_y);

    return $this->saveImageDataInAnyFormat($img, $file->getMimeType());
  }

  /**
   * Very crudely scale an image up or down to an exact size.
   */
  private function crudelyScaleTo(PhabricatorFile $file, $dx, $dy) {
    $data = $file->loadFileData();
    $src = imagecreatefromstring($data);

    $dst = $this->applyScaleTo($src, $dx, $dy);

    return $this->saveImageDataInAnyFormat($dst, $file->getMimeType());
  }

  private function applyScaleTo($src, $dx, $dy) {
    $x = imagesx($src);
    $y = imagesy($src);

    $scale = min(($dx / $x), ($dy / $y), 1);

    $dst = imagecreatetruecolor($dx, $dy);
    imagesavealpha($dst, true);
    imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 255, 255, 255, 127));

    $sdx = $scale * $x;
    $sdy = $scale * $y;

    imagecopyresampled(
      $dst,
      $src,
      ($dx - $sdx) / 2,  ($dy - $sdy) / 2,
      0, 0,
      $sdx, $sdy,
      $x, $y);

    return $dst;
  }

  private function generatePreview(PhabricatorFile $file, $size) {
    $data = $file->loadFileData();
    $src = imagecreatefromstring($data);

    $x = imagesx($src);
    $y = imagesy($src);

    $scale = min($size / $x, $size / $y, 1);

    $dx = max($size / 4, $scale * $x);
    $dy = max($size / 4, $scale * $y);

    $dst = imagecreatetruecolor($dx, $dy);
    imagesavealpha($dst, true);
    imagefill($dst, 0, 0, imagecolorallocatealpha($dst, 255, 255, 255, 127));

    $sdx = $scale * $x;
    $sdy = $scale * $y;

    imagecopyresampled(
      $dst,
      $src,
      ($dx - $sdx) / 2, ($dy - $sdy) / 2,
      0, 0,
      $sdx, $sdy,
      $x, $y);

    return $this->saveImageDataInAnyFormat($dst, $file->getMimeType());
  }

  private function saveImageDataInAnyFormat($data, $preferred_mime = '') {
    switch ($preferred_mime) {
      case 'image/gif': // GIF doesn't support true color.
      case 'image/png':
        if (function_exists('imagepng')) {
          ob_start();
          imagepng($data);
          return ob_get_clean();
        }
        break;
    }

    $img = null;

    if (function_exists('imagejpeg')) {
      ob_start();
      imagejpeg($data);
      $img = ob_get_clean();
    } else if (function_exists('imagepng')) {
      ob_start();
      imagepng($data);
      $img = ob_get_clean();
    } else if (function_exists('imagegif')) {
      ob_start();
      imagegif($data);
      $img = ob_get_clean();
    } else {
      throw new Exception("No image generation functions exist!");
    }

    return $img;
  }

}
