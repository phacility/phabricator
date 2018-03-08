<?php

/**
 * @task enormous Detecting Enormous Images
 * @task save     Saving Image Data
 */
final class PhabricatorImageTransformer extends Phobject {


/* -(  Saving Image Data  )-------------------------------------------------- */


  /**
   * Save an image resource to a string representation suitable for storage or
   * transmission as an image file.
   *
   * Optionally, you can specify a preferred MIME type like `"image/png"`.
   * Generally, you should specify the MIME type of the original file if you're
   * applying file transformations. The MIME type may not be honored if
   * Phabricator can not encode images in the given format (based on available
   * extensions), but can save images in another format.
   *
   * @param   resource  GD image resource.
   * @param   string?   Optionally, preferred mime type.
   * @return  string    Bytes of an image file.
   * @task save
   */
  public static function saveImageDataInAnyFormat($data, $preferred_mime = '') {
    $preferred = null;
    switch ($preferred_mime) {
      case 'image/gif':
        $preferred = self::saveImageDataAsGIF($data);
        break;
      case 'image/png':
        $preferred = self::saveImageDataAsPNG($data);
        break;
    }

    if ($preferred !== null) {
      return $preferred;
    }

    $data = self::saveImageDataAsJPG($data);
    if ($data !== null) {
      return $data;
    }

    $data = self::saveImageDataAsPNG($data);
    if ($data !== null) {
      return $data;
    }

    $data = self::saveImageDataAsGIF($data);
    if ($data !== null) {
      return $data;
    }

    throw new Exception(pht('Failed to save image data into any format.'));
  }


  /**
   * Save an image in PNG format, returning the file data as a string.
   *
   * @param resource      GD image resource.
   * @return string|null  PNG file as a string, or null on failure.
   * @task save
   */
  private static function saveImageDataAsPNG($image) {
    if (!function_exists('imagepng')) {
      return null;
    }

    // NOTE: Empirically, the highest compression level (9) seems to take
    // up to twice as long as the default compression level (6) but produce
    // only slightly smaller files (10% on avatars, 3% on screenshots).

    ob_start();
    $result = imagepng($image, null, 6);
    $output = ob_get_clean();

    if (!$result) {
      return null;
    }

    return $output;
  }


  /**
   * Save an image in GIF format, returning the file data as a string.
   *
   * @param resource      GD image resource.
   * @return string|null  GIF file as a string, or null on failure.
   * @task save
   */
  private static function saveImageDataAsGIF($image) {
    if (!function_exists('imagegif')) {
      return null;
    }

    ob_start();
    $result = imagegif($image);
    $output = ob_get_clean();

    if (!$result) {
      return null;
    }

    return $output;
  }


  /**
   * Save an image in JPG format, returning the file data as a string.
   *
   * @param resource      GD image resource.
   * @return string|null  JPG file as a string, or null on failure.
   * @task save
   */
  private static function saveImageDataAsJPG($image) {
    if (!function_exists('imagejpeg')) {
      return null;
    }

    ob_start();
    $result = imagejpeg($image);
    $output = ob_get_clean();

    if (!$result) {
      return null;
    }

    return $output;
  }


}
