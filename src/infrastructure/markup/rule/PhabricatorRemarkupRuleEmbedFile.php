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

    if (!$file->isViewableImage() || $options['layout'] == 'link') {
      // If a file isn't in image, just render a link to it.
      $link = phutil_render_tag(
        'a',
        array(
          'href'    => $file->getBestURI(),
          'target'  => '_blank',
          'class'   => 'phabricator-remarkup-embed-layout-link',
        ),
        phutil_escape_html($file_name));
      return $this->getEngine()->storeText($link);
    }

    $attrs = array();

    switch ($options['size']) {
      case 'full':
        $attrs['src'] = $file->getBestURI();
        $link = null;
        break;
      case 'thumb':
      default:
        $attrs['src'] = $file->getPreview220URI();
        $link = $file->getBestURI();
        break;
    }

    $embed = phutil_render_tag('img', $attrs);

    if ($link) {
      $embed = phutil_render_tag(
        'a',
        array(
          'href'    => $link,
          'class'   => 'phabricator-remarkup-embed-image',
          'target'  => '_blank',
        ),
        $embed);
    }

    $layout_class = null;
    switch ($options['layout']) {
      case 'right':
      case 'center':
      case 'inline':
      case 'left':
        $layout_class = 'phabricator-remarkup-embed-layout-'.$options['layout'];
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

    return $this->getEngine()->storeText($embed);
  }

}
