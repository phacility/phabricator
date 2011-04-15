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

final class DifferentialInlineCommentView extends AphrontView {

  private $inlineComment;
  private $onRight;
  private $buildScaffolding;
  private $handles;
  private $markupEngine;
  private $editable;

  public function setInlineComment(DifferentialInlineComment $comment) {
    $this->inlineComment = $comment;
    return $this;
  }

  public function setOnRight($on_right) {
    $this->onRight = $on_right;
    return $this;
  }

  public function setBuildScaffolding($scaffold) {
    $this->buildScaffolding = $scaffold;
    return $this;
  }

  public function setHandles(array $handles) {
    $this->handles = $handles;
    return $this;
  }

  public function setMarkupEngine(PhutilMarkupEngine $engine) {
    $this->markupEngine = $engine;
    return $this;
  }

  public function setEditable($editable) {
    $this->editable = $editable;
    return $this;
  }

  public function render() {

    $inline = $this->inlineComment;

    $start = $inline->getLineNumber();
    $length = $inline->getLineLength();
    if ($length) {
      $end = $start + $length;
      $line = 'Lines '.number_format($start).'-'.number_format($end);
    } else {
      $line = 'Line '.number_format($start);
    }

    $metadata = array(
      'id' => $inline->getID(),
      'number' => $inline->getLineNumber(),
      'length' => $inline->getLineLength(),
      'on_right' => $this->onRight,
    );

    $sigil = 'differential-inline-comment';

    $content = $inline->getContent();
    $handles = $this->handles;

    $links = array();

    $links[] = javelin_render_tag(
      'a',
      array(
        'href'        => '#',
        'mustcapture' => true,
        'sigil'       => 'differential-inline-reply',
        'meta'        => array(
          'is_new' => true,
          'changeset' => $inline->getChangesetID(),
          'number' => $inline->getLineNumber(),
          'length' => $inline->getLineLength(),
          'on_right' => $this->onRight,
        )
      ),
      'Reply');

    if ($this->editable) {
      $links[] = javelin_render_tag(
        'a',
        array(
          'href'        => '#',
          'mustcapture' => true,
          'sigil'       => 'differential-inline-edit',
        ),
        'Edit');
      $links[] = javelin_render_tag(
        'a',
        array(
          'href'        => '#',
          'mustcapture' => true,
          'sigil'       => 'differential-inline-delete',
        ),
        'Delete');
    }

    if ($links) {
      $links =
        '<span class="differential-inline-comment-links">'.
          implode(' &middot; ', $links).
        '</span>';
    } else {
      $links = null;
    }

    $cache = $inline->getCache();
    if (strlen($cache)) {
      $content = $cache;
    } else {
      $content = $this->markupEngine->markupText($content);
      if ($inline->getID()) {
        $inline->setCache($content);
        $inline->save();
      }
    }

    $anchor = phutil_render_tag(
      'a',
      array(
        'name' => 'inline-'.$inline->getID(),
      ),
      '');

    $markup = javelin_render_tag(
      'div',
      array(
        'class' => 'differential-inline-comment',
        'sigil' => $sigil,
        'meta'  => $metadata,
      ),
      '<div class="differential-inline-comment-head">'.
        $anchor.
        $links.
        '<span class="differential-inline-comment-line">'.$line.'</span>'.
        phutil_escape_html($handles[$inline->getAuthorPHID()]->getName()).
      '</div>'.
      '<div class="phabricator-remarkup">'.
        $content.
      '</div>');

    return $this->scaffoldMarkup($markup);
  }

  private function scaffoldMarkup($markup) {
    if (!$this->buildScaffolding) {
      return $markup;
    }

    $left_markup = !$this->onRight ? $markup : '';
    $right_markup = $this->onRight ? $markup : '';

    return
      '<table>'.
        '<tr class="inline">'.
          '<th></th>'.
          '<td>'.$left_markup.'</td>'.
          '<th></th>'.
          '<td>'.$right_markup.'</td>'.
        '</tr>'.
      '</table>';
  }

}
