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

final class PhabricatorTransactionView extends AphrontView {

  private $user;
  private $imageURI;
  private $actions = array();
  private $epoch;
  private $contentSource;
  private $anchorName;
  private $anchorText;
  private $isPreview;
  private $classes = array();

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function setImageURI($uri) {
    $this->imageURI = $uri;
    return $this;
  }

  public function setActions(array $actions) {
    $this->actions = $actions;
    return $this;
  }

  public function setEpoch($epoch) {
    $this->epoch = $epoch;
    return $this;
  }

  public function setContentSource(PhabricatorContentSource $source) {
    $this->contentSource = $source;
    return $this;
  }

  public function setAnchor($anchor_name, $anchor_text) {
    $this->anchorName = $anchor_name;
    $this->anchorText = $anchor_text;
    return $this;
  }

  public function addClass($class) {
    $this->classes[] = $class;
    return $this;
  }

  public function setIsPreview($preview) {
    $this->isPreview = $preview;
    return $this;
  }

  public function render() {
    if (!$this->user) {
      throw new Exception("Call setUser() before render()!");
    }

    require_celerity_resource('phabricator-transaction-view-css');

    $info = $this->renderTransactionInfo();
    $actions = $this->renderTransactionActions();
    $style = $this->renderTransactionStyle();
    $content = $this->renderTransactionContent();
    $classes = phutil_escape_html(implode(' ', $this->classes));

    $transaction_id = $this->anchorName ? 'anchor-'.$this->anchorName : null;

    return phutil_render_tag(
      'div',
      array(
        'class' => 'phabricator-transaction-view',
        'id'    => $transaction_id,
        'style' => $style,
      ),
      '<div class="phabricator-transaction-detail '.$classes.'">'.
        '<div class="phabricator-transaction-header">'.
          $info.
          $actions.
        '</div>'.
        $content.
      '</div>');

  }

  private function renderTransactionInfo() {
    $info = array();

    if ($this->contentSource) {
      $content_source = new PhabricatorContentSourceView();
      $content_source->setContentSource($this->contentSource);
      $content_source->setUser($this->user);
      $source = $content_source->render();
      if ($source) {
        $info[] = $source;
      }
    }

    if ($this->isPreview) {
      $info[] = 'PREVIEW';
    } else if ($this->epoch) {
      $info[] = phabricator_datetime($this->epoch, $this->user);
    }

    if ($this->anchorName) {
      Javelin::initBehavior('phabricator-watch-anchor');
      $info[] = phutil_render_tag(
        'a',
        array(
          'name'  => $this->anchorName,
          'id'    => $this->anchorName,
          'href'  => '#'.$this->anchorName,
        ),
        phutil_escape_html($this->anchorText));
    }

    $info = implode(' &middot; ', $info);

    return
      '<span class="phabricator-transaction-info">'.
        $info.
      '</span>';
  }

  private function renderTransactionActions() {
    return implode('', $this->actions);
  }

  private function renderTransactionStyle() {
    if ($this->imageURI) {
      return 'background-image: url('.$this->imageURI.');';
    } else {
      return null;
    }
  }

  private function renderTransactionContent() {
    $content = $this->renderChildren();
    if (!$content) {
      return null;
    }
    return
      '<div class="phabricator-transaction-content">'.
        $content.
      '</div>';
  }

}
