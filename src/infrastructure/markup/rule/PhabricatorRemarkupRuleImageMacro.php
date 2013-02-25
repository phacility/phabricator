<?php

/**
 * @group markup
 */
final class PhabricatorRemarkupRuleImageMacro
  extends PhutilRemarkupRule {

  private $images;

  public function apply($text) {
    return preg_replace_callback(
      '@^([a-zA-Z0-9:_\-]+)$@m',
      array($this, 'markupImageMacro'),
      $text);
  }

  public function markupImageMacro($matches) {
    if ($this->images === null) {
      $this->images = array();
      $rows = id(new PhabricatorFileImageMacro())->loadAllWhere(
        'isDisabled = 0');
      foreach ($rows as $row) {
        $this->images[$row->getName()] = $row->getFilePHID();
      }
    }

    $name = (string)$matches[1];

    if (array_key_exists($name, $this->images)) {
      $phid = $this->images[$name];

      $file = id(new PhabricatorFile())->loadOneWhere('phid = %s', $phid);
      $style = null;
      $src_uri = null;
      if ($file) {
        $src_uri = $file->getBestURI();
        $file_data = $file->getMetadata();
        $height = idx($file_data, PhabricatorFile::METADATA_IMAGE_HEIGHT);
        $width = idx($file_data, PhabricatorFile::METADATA_IMAGE_WIDTH);
        if ($height && $width) {
          $style = sprintf(
            'height: %dpx; width: %dpx;',
            $height,
            $width);
        }
      }

      $img = phutil_tag(
        'img',
        array(
          'src'   => $src_uri,
          'alt'   => $matches[1],
          'title' => $matches[1],
          'style' => $style,
        ));
      return $this->getEngine()->storeText($img);
    } else {
      return $matches[1];
    }
  }

}
