<?php

/**
 * @group markup
 */
final class PhabricatorRemarkupRuleEmbedFile
  extends PhutilRemarkupRule {

  const KEY_RULE_EMBED_FILE = 'rule.embed.file';
  const KEY_EMBED_FILE_PHIDS = 'phabricator.embedded-file-phids';

  public function apply($text) {
    return preg_replace_callback(
      "@{F(\d+)([^}]+?)?}@",
      array($this, 'markupEmbedFile'),
      $text);
  }

  public function markupEmbedFile($matches) {

    $file = null;
    if ($matches[1]) {
      // TODO: This is pretty inefficient if there are a bunch of files.
      $file = id(new PhabricatorFile())->load($matches[1]);
    }

    if (!$file) {
      return $matches[0];
    }

    $engine = $this->getEngine();

    if ($engine->isTextMode()) {
      return $engine->storeText($file->getBestURI());
    }

    $phid = $file->getPHID();

    $token = $engine->storeText('');
    $metadata_key = self::KEY_RULE_EMBED_FILE;
    $metadata = $engine->getTextMetadata($metadata_key, array());
    $bundle = array('token' => $token);

    $options = array(
      'size'    => 'thumb',
      'layout'  => 'left',
      'float'   => false,
      'name'    => null,
    );

    if (!empty($matches[2])) {
      $matches[2] = trim($matches[2], ', ');
      $parser = new PhutilSimpleOptions();
      $options = $parser->parse($matches[2]) + $options;
    }
    $file_name = coalesce($options['name'], $file->getName());
    $options['name'] = $file_name;

    $is_viewable_image = $file->isViewableImage();

    $attrs = array();
    if ($is_viewable_image) {
      switch ((string)$options['size']) {
        case 'full':
          $attrs['src'] = $file->getBestURI();
          $options['image_class'] = null;
          $file_data = $file->getMetadata();
          $height = idx($file_data, PhabricatorFile::METADATA_IMAGE_HEIGHT);
          if ($height) {
            $attrs['height'] = $height;
          }
          $width = idx($file_data, PhabricatorFile::METADATA_IMAGE_WIDTH);
          if ($width) {
            $attrs['width'] = $width;
          }
          break;
        case 'thumb':
        default:
          $attrs['src'] = $file->getPreview220URI();
          $dimensions =
            PhabricatorImageTransformer::getPreviewDimensions($file, 220);
          $attrs['width'] = $dimensions['sdx'];
          $attrs['height'] = $dimensions['sdy'];
          $options['image_class'] = 'phabricator-remarkup-embed-image';
          break;
      }
    }
    $bundle['attrs'] = $attrs;
    $bundle['options'] = $options;

    $bundle['meta'] = array(
      'phid'     => $file->getPHID(),
      'viewable' => $is_viewable_image,
      'uri'      => $file->getBestURI(),
      'dUri'     => $file->getDownloadURI(),
      'name'     => $options['name'],
    );
    $metadata[$phid][] = $bundle;
    $engine->setTextMetadata($metadata_key, $metadata);

    return $token;
  }

  public function didMarkupText() {
    $engine = $this->getEngine();

    $metadata_key = self::KEY_RULE_EMBED_FILE;
    $metadata = $engine->getTextMetadata($metadata_key, array());

    if (!$metadata) {
      return;
    }

    $file_phids = array();
    foreach ($metadata as $phid => $bundles) {
      foreach ($bundles as $data) {

        $options = $data['options'];
        $meta    = $data['meta'];

        if (!$meta['viewable'] || $options['layout'] == 'link') {
          $link = id(new PhabricatorFileLinkView())
            ->setFilePHID($meta['phid'])
            ->setFileName($meta['name'])
            ->setFileDownloadURI($meta['dUri'])
            ->setFileViewURI($meta['uri'])
            ->setFileViewable($meta['viewable']);
          $embed = $link->render();
          $engine->overwriteStoredText($data['token'], $embed);
          continue;
        }

        require_celerity_resource('lightbox-attachment-css');
        $img = phutil_tag('img', $data['attrs']);

        $embed = javelin_tag(
          'a',
          array(
            'href'        => $meta['uri'],
            'class'       => $options['image_class'],
            'sigil'       => 'lightboxable',
            'mustcapture' => true,
            'meta'        => $meta,
          ),
          $img);

        $layout_class = null;
        switch ($options['layout']) {
          case 'right':
          case 'center':
          case 'inline':
          case 'left':
            $layout_class = 'phabricator-remarkup-embed-layout-'.
              $options['layout'];
            break;
          default:
            $layout_class = 'phabricator-remarkup-embed-layout-left';
            break;
        }

        if ($options['float']) {
          switch ($options['layout']) {
            case 'center':
            case 'inline':
              break;
            case 'right':
              $layout_class .= ' phabricator-remarkup-embed-float-right';
              break;
            case 'left':
            default:
              $layout_class .= ' phabricator-remarkup-embed-float-left';
              break;
          }
        }

        if ($layout_class) {
          $embed = phutil_tag(
            'div',
            array(
              'class' => $layout_class,
            ),
            $embed);
        }

        $engine->overwriteStoredText($data['token'], $embed);
      }
      $file_phids[] = $phid;
    }
    $engine->setTextMetadata(self::KEY_EMBED_FILE_PHIDS, $file_phids);
    $engine->setTextMetadata($metadata_key, array());
  }

}
