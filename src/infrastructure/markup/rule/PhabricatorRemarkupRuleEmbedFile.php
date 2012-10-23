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

/**
 * @group markup
 */
final class PhabricatorRemarkupRuleEmbedFile
  extends PhutilRemarkupRule {

  const KEY_RULE_EMBED_FILE = 'rule.embed.file';

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
    $phid = $file->getPHID();

    $engine = $this->getEngine();
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
      $options = PhutilSimpleOptions::parse($matches[2]) + $options;
    }
    $file_name = coalesce($options['name'], $file->getName());
    $options['name'] = $file_name;

    $attrs = array();
    switch ($options['size']) {
      case 'full':
        $attrs['src'] = $file->getBestURI();
        $options['image_class'] = null;
        break;
      case 'thumb':
      default:
        $attrs['src'] = $file->getPreview220URI();
        $options['image_class'] = 'phabricator-remarkup-embed-image';
        break;
    }
    $bundle['attrs'] = $attrs;
    $bundle['options'] = $options;

    $bundle['meta'] = array(
      'phid'     => $file->getPHID(),
      'viewable' => $file->isViewableImage(),
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
        $img = phutil_render_tag('img', $data['attrs']);

        $embed = javelin_render_tag(
          'a',
          array(
            'href'        => '#',
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
          $embed = phutil_render_tag(
            'div',
            array(
              'class' => $layout_class,
            ),
            $embed);
        }

        $engine->overwriteStoredText($data['token'], $embed);
      }
    }
    $engine->setTextMetadata($metadata_key, array());
  }

}
