<?php

final class PhabricatorFaviconRef extends Phobject {

  private $viewer;
  private $width;
  private $height;
  private $emblems;
  private $uri;
  private $cacheKey;

  public function __construct() {
    $this->emblems = array(null, null, null, null);
  }

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function setWidth($width) {
    $this->width = $width;
    return $this;
  }

  public function getWidth() {
    return $this->width;
  }

  public function setHeight($height) {
    $this->height = $height;
    return $this;
  }

  public function getHeight() {
    return $this->height;
  }

  public function setEmblems(array $emblems) {
    if (count($emblems) !== 4) {
      throw new Exception(
        pht(
          'Expected four elements in icon emblem list. To omit an emblem, '.
          'pass "null".'));
    }

    $this->emblems = $emblems;
    return $this;
  }

  public function getEmblems() {
    return $this->emblems;
  }

  public function setURI($uri) {
    $this->uri = $uri;
    return $this;
  }

  public function getURI() {
    return $this->uri;
  }

  public function setCacheKey($cache_key) {
    $this->cacheKey = $cache_key;
    return $this;
  }

  public function getCacheKey() {
    return $this->cacheKey;
  }

  public function newDigest() {
    return PhabricatorHash::digestForIndex(serialize($this->toDictionary()));
  }

  public function toDictionary() {
    return array(
      'width' => $this->width,
      'height' => $this->height,
      'emblems' => $this->emblems,
    );
  }

  public static function newConfigurationDigest() {
    $all_resources = self::getAllResources();

    // Because we need to access this cache on every page, it's very sticky.
    // Try to dirty it automatically if any relevant configuration changes.
    $inputs = array(
      'resources' => $all_resources,
      'prod' => PhabricatorEnv::getProductionURI('/'),
      'cdn' => PhabricatorEnv::getEnvConfig('security.alternate-file-domain'),
      'havepng' => function_exists('imagepng'),
    );

    return PhabricatorHash::digestForIndex(serialize($inputs));
  }

  private static function getAllResources() {
    $custom_resources = PhabricatorEnv::getEnvConfig('ui.favicons');

    foreach ($custom_resources as $key => $custom_resource) {
      $custom_resources[$key] = array(
        'source-type' => 'file',
        'default' => false,
      ) + $custom_resource;
    }

    $builtin_resources = self::getBuiltinResources();

    return array_merge($builtin_resources, $custom_resources);
  }

  private static function getBuiltinResources() {
    return array(
      array(
        'source-type' => 'builtin',
        'source' => 'favicon/default-76x76.png',
        'version' => 1,
        'width' => 76,
        'height' => 76,
        'default' => true,
      ),
      array(
        'source-type' => 'builtin',
        'source' => 'favicon/default-120x120.png',
        'version' => 1,
        'width' => 120,
        'height' => 120,
        'default' => true,
      ),
      array(
        'source-type' => 'builtin',
        'source' => 'favicon/default-128x128.png',
        'version' => 1,
        'width' => 128,
        'height' => 128,
        'default' => true,
      ),
      array(
        'source-type' => 'builtin',
        'source' => 'favicon/default-152x152.png',
        'version' => 1,
        'width' => 152,
        'height' => 152,
        'default' => true,
      ),
      array(
        'source-type' => 'builtin',
        'source' => 'favicon/dot-pink-64x64.png',
        'version' => 1,
        'width' => 64,
        'height' => 64,
        'emblem' => 'dot-pink',
        'default' => true,
      ),
      array(
        'source-type' => 'builtin',
        'source' => 'favicon/dot-red-64x64.png',
        'version' => 1,
        'width' => 64,
        'height' => 64,
        'emblem' => 'dot-red',
        'default' => true,
      ),
    );
  }

  public function newURI() {
    $dst_w = $this->getWidth();
    $dst_h = $this->getHeight();

    $template = $this->newTemplateFile(null, $dst_w, $dst_h);
    $template_file = $template['file'];

    $cache = $this->loadCachedFile($template_file);
    if ($cache) {
      return $cache->getViewURI();
    }

    $data = $this->newCompositedFavicon($template);

    $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();

    $caught = null;
    try {
      $favicon_file = $this->newFaviconFile($data);

      $xform = id(new PhabricatorTransformedFile())
        ->setOriginalPHID($template_file->getPHID())
        ->setTransformedPHID($favicon_file->getPHID())
        ->setTransform($this->getCacheKey());

      try {
        $xform->save();
      } catch (AphrontDuplicateKeyQueryException $ex) {
        unset($unguarded);

        $cache = $this->loadCachedFile($template_file);
        if (!$cache) {
          throw $ex;
        }

        id(new PhabricatorDestructionEngine())
          ->destroyObject($favicon_file);

        return $cache->getViewURI();
      }
    } catch (Exception $ex) {
      $caught = $ex;
    }

    unset($unguarded);

    if ($caught) {
      throw $caught;
    }

    return $favicon_file->getViewURI();
  }

  private function loadCachedFile(PhabricatorFile $template_file) {
    $viewer = $this->getViewer();

    $xform = id(new PhabricatorTransformedFile())->loadOneWhere(
      'originalPHID = %s AND transform = %s',
      $template_file->getPHID(),
      $this->getCacheKey());
    if (!$xform) {
      return null;
    }

    return id(new PhabricatorFileQuery())
      ->setViewer($viewer)
      ->withPHIDs(array($xform->getTransformedPHID()))
      ->executeOne();
  }

  private function newCompositedFavicon($template) {
    $dst_w = $this->getWidth();
    $dst_h = $this->getHeight();
    $src_w = $template['width'];
    $src_h = $template['height'];

    try {
      $template_data = $template['file']->loadFileData();
    } catch (Exception $ex) {
      // In rare cases, we can end up with a corrupted or inaccessible file.
      // If we do, just give up: otherwise, it's impossible to get pages to
      // generate and not obvious how to fix it.
      return null;
    }

    if (!function_exists('imagecreatefromstring')) {
      return $template_data;
    }

    $src = @imagecreatefromstring($template_data);
    if (!$src) {
      return $template_data;
    }

    $dst = imagecreatetruecolor($dst_w, $dst_h);
    imagesavealpha($dst, true);

    $transparent = imagecolorallocatealpha($dst, 0, 255, 0, 127);
    imagefill($dst, 0, 0, $transparent);

    imagecopyresampled(
      $dst,
      $src,
      0,
      0,
      0,
      0,
      $dst_w,
      $dst_h,
      $src_w,
      $src_h);

    // Now, copy any icon emblems on top of the image. These are dots or other
    // marks used to indicate status information.
    $emblem_w = (int)floor(min($dst_w, $dst_h) / 2);
    $emblem_h = $emblem_w;
    foreach ($this->emblems as $key => $emblem) {
      if ($emblem === null) {
        continue;
      }

      $emblem_template = $this->newTemplateFile(
        $emblem,
        $emblem_w,
        $emblem_h);

      switch ($key) {
        case 0:
          $emblem_x = $dst_w - $emblem_w;
          $emblem_y = 0;
          break;
        case 1:
          $emblem_x = $dst_w - $emblem_w;
          $emblem_y = $dst_h - $emblem_h;
          break;
        case 2:
          $emblem_x = 0;
          $emblem_y = $dst_h - $emblem_h;
          break;
        case 3:
          $emblem_x = 0;
          $emblem_y = 0;
          break;
      }

      $emblem_data = $emblem_template['file']->loadFileData();

      $src = @imagecreatefromstring($emblem_data);
      if (!$src) {
        continue;
      }

      imagecopyresampled(
        $dst,
        $src,
        $emblem_x,
        $emblem_y,
        0,
        0,
        $emblem_w,
        $emblem_h,
        $emblem_template['width'],
        $emblem_template['height']);
    }

    return PhabricatorImageTransformer::saveImageDataInAnyFormat(
      $dst,
      'image/png');
  }

  private function newTemplateFile($emblem, $width, $height) {
    $all_resources = self::getAllResources();

    $scores = array();
    $ratio = $width / $height;
    foreach ($all_resources as $key => $resource) {
      // We can't use an emblem resource for a different emblem, nor for an
      // icon base. We also can't use an icon base as an emblem. That is, if
      // we're looking for a picture of a red dot, we have to actually find
      // a red dot, not just any image which happens to have a similar size.
      if (idx($resource, 'emblem') !== $emblem) {
        continue;
      }

      $resource_width = $resource['width'];
      $resource_height = $resource['height'];

      // Never use a resource with a different aspect ratio.
      if (($resource_width / $resource_height) !== $ratio) {
        continue;
      }

      // Try to use custom resources instead of default resources.
      if ($resource['default']) {
        $default_score = 1;
      } else {
        $default_score = 0;
      }

      $width_diff = ($resource_width - $width);

      // If we have to resize an image, we'd rather scale a larger image down
      // than scale a smaller image up.
      if ($width_diff < 0) {
        $scale_score = 1;
      } else {
        $scale_score = 0;
      }

      // Otherwise, we'd rather scale an image a little bit (ideally, zero)
      // than scale an image a lot.
      $width_score = abs($width_diff);

      $scores[$key] = id(new PhutilSortVector())
        ->addInt($default_score)
        ->addInt($scale_score)
        ->addInt($width_score);
    }

    if (!$scores) {
      if ($emblem === null) {
        throw new Exception(
          pht(
            'Found no background template resource for dimensions %dx%d.',
            $width,
            $height));
      } else {
        throw new Exception(
          pht(
            'Found no template resource (for emblem "%s") with dimensions '.
            '%dx%d.',
            $emblem,
            $width,
            $height));
      }
    }

    $scores = msortv($scores, 'getSelf');
    $best_score = head_key($scores);

    $viewer = $this->getViewer();

    $resource = $all_resources[$best_score];
    if ($resource['source-type'] === 'builtin') {
      $file = PhabricatorFile::loadBuiltin($viewer, $resource['source']);
      if (!$file) {
        throw new Exception(
          pht(
            'Failed to load favicon template builtin "%s".',
            $resource['source']));
      }
    } else {
      $file = id(new PhabricatorFileQuery())
        ->setViewer($viewer)
        ->withPHIDs(array($resource['source']))
        ->executeOne();
      if (!$file) {
        throw new Exception(
          pht(
            'Failed to load favicon template with PHID "%s".',
            $resource['source']));
      }
    }

    return array(
      'width' => $resource['width'],
      'height' => $resource['height'],
      'file' => $file,
    );
  }

  private function newFaviconFile($data) {
    return PhabricatorFile::newFromFileData(
      $data,
      array(
        'name' => 'favicon',
        'canCDN' => true,
      ));
  }

}
