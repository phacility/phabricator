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

final class DifferentialRevisionCommentView extends AphrontView {

  private $comment;
  private $handles;
  private $markupEngine;
  private $preview;
  private $inlines;
  private $changesets;

  public function setComment($comment) {
    $this->comment = $comment;
    return $this;
  }

  public function setHandles(array $handles) {
    $this->handles = $handles;
    return $this;
  }

  public function setMarkupEngine($markup_engine) {
    $this->markupEngine = $markup_engine;
    return $this;
  }

  public function setPreview($preview) {
    $this->preview = $preview;
    return $this;
  }

  public function setInlineComments(array $inline_comments) {
    $this->inlines = $inline_comments;
    return $this;
  }

  public function setChangesets(array $changesets) {
    // Ship these in sorted by getSortKey() and keyed by ID... or else!
    $this->changesets = $changesets;
    return $this;
  }

  public function render() {

    require_celerity_resource('phabricator-remarkup-css');
    require_celerity_resource('differential-revision-comment-css');

    $comment = $this->comment;

    $action = $comment->getAction();

    $action_class = 'differential-comment-action-'.phutil_escape_html($action);

    if ($this->preview) {
      $date = 'COMMENT PREVIEW';
    } else {
      $date = date('F jS, Y g:i:s A', $comment->getDateCreated());
    }

    $author = $this->handles[$comment->getAuthorPHID()];
    $author_link = $author->renderLink();

    $verb = DifferentialAction::getActionPastTenseVerb($comment->getAction());
    $verb = phutil_escape_html($verb);

    $content = $comment->getContent();
    if (strlen(rtrim($content))) {
      $title = "{$author_link} {$verb} this revision:";
      $cache = $comment->getCache();
      if (strlen($cache)) {
        $content = $cache;
      } else {
        $content = $this->markupEngine->markupText($content);
        if ($comment->getID()) {
          $comment->setCache($content);
          $comment->save();
        }
      }
      $content =
        '<div class="phabricator-remarkup">'.
          $content.
        '</div>';
    } else {
      $title = null;
      $content =
        '<div class="differential-comment-nocontent">'.
          "<p>{$author_link} {$verb} this revision.</p>".
        '</div>';
    }

    if ($this->inlines) {
      $inline_render = array();
      $inlines = $this->inlines;
      $changesets = $this->changesets;
      $inlines_by_changeset = mgroup($inlines, 'getChangesetID');
      $inlines_by_changeset = array_select_keys(
        $inlines_by_changeset,
        array_keys($this->changesets));
      $inline_render[] = '<table class="differential-inline-summary">';
      foreach ($inlines_by_changeset as $changeset_id => $inlines) {
        $changeset = $changesets[$changeset_id];
        $inlines = msort($inlines, 'getLineNumber');
        $inline_render[] =
          '<tr>'.
            '<th colspan="2">'.
              phutil_escape_html($changeset->getFileName()).
            '</th>'.
          '</tr>';
        foreach ($inlines as $inline) {
          if (!$inline->getLineLength()) {
            $lines = $inline->getLineNumber();
          } else {
            $lines = $inline->getLineNumber()."\xE2\x80\x93".
                     ($inline->getLineNumber() + $inline->getLineLength());
          }

          $lines = phutil_render_tag(
            'a',
            array(
              'href' => '#inline-'.$inline->getID(),
              'class' => 'num',
            ),
            $lines);

          $inline_content = $inline->getContent();
          if (strlen($inline_content)) {
            $inline_cache = $inline->getCache();
            if ($inline_cache) {
              $inline_content = $inline_cache;
            } else {
              $inline_content = $this->markupEngine->markupText(
                $inline_content);
              if ($inline->getID()) {
                $inline->setCache($inline_content);
                $inline->save();
              }
            }
          }

          $inline_render[] =
            '<tr>'.
              '<td class="inline-line-number">'.$lines.'</td>'.
              '<td>'.
                '<div class="phabricator-remarkup">'.
                  $inline_content.
                '</div>'.
              '</td>'.
            '</tr>';
        }
      }
      $inline_render[] = '</table>';
      $inline_render = implode("\n", $inline_render);
      $inline_render =
        '<div class="differential-inline-summary-section">'.
          'Inline Comments'.
        '</div>'.
        $inline_render;
    } else {
      $inline_render = null;
    }

    $background = null;
    $uri = $author->getImageURI();
    if ($uri) {
      $background = "background-image: url('{$uri}');";
    }

    return
      '<div class="differential-comment '.$action_class.'">'.
        '<div class="differential-comment-head">'.
          '<div class="differential-comment-date">'.$date.'</div>'.
          '<div class="differential-comment-title">'.$title.'</div>'.
        '</div>'.
        '<div class="differential-comment-body" style="'.$background.'">'.
          '<div class="differential-comment-content">'.
            '<div class="differential-comment-core">'.
              $content.
            '</div>'.
            $inline_render.
          '</div>'.
        '</div>'.
      '</div>';
  }

}
