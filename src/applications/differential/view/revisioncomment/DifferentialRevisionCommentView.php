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

final class DifferentialRevisionCommentView extends AphrontView {

  private $comment;
  private $handles;
  private $markupEngine;
  private $preview;
  private $inlines;
  private $changesets;
  private $target;
  private $commentNumber;
  private $user;
  private $versusDiffID;

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

  public function setTargetDiff($target) {
    $this->target = $target;
    return $this;
  }

  public function setVersusDiffID($diff_vs) {
    $this->versusDiffID = $diff_vs;
    return $this;
  }

  public function setCommentNumber($comment_number) {
    $this->commentNumber = $comment_number;
    return $this;
  }

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public function render() {

    if (!$this->user) {
      throw new Exception("Call setUser() before rendering!");
    }

    require_celerity_resource('phabricator-remarkup-css');
    require_celerity_resource('differential-revision-comment-css');

    $comment = $this->comment;

    $action = $comment->getAction();

    $action_class = 'differential-comment-action-'.$action;

    if ($this->preview) {
      $date = 'COMMENT PREVIEW';
    } else {
      $date = phabricator_datetime($comment->getDateCreated(), $this->user);
    }

    $info = array();

    $content_source = new PhabricatorContentSourceView();
    $content_source->setContentSource($comment->getContentSource());
    $content_source->setUser($this->user);
    $info[] = $content_source->render();

    $info[] = $date;

    $comment_anchor = null;
    $num = $this->commentNumber;
    if ($num && !$this->preview) {
      Javelin::initBehavior('phabricator-watch-anchor');
      $info[] = phutil_render_tag(
        'a',
        array(
          'name' => 'comment-'.$num,
          'href' => '#comment-'.$num,
        ),
        'Comment D'.$comment->getRevisionID().'#'.$num);
      $comment_anchor = 'anchor-comment-'.$num;
    }

    $info = implode(' &middot; ', array_filter($info));

    $author = $this->handles[$comment->getAuthorPHID()];
    $author_link = $author->renderLink();

    $verb = DifferentialAction::getActionPastTenseVerb($comment->getAction());
    $verb = phutil_escape_html($verb);

    $content = $comment->getContent();
    $head_content = null;
    if (strlen(rtrim($content))) {
      $title = "{$author_link} {$verb} this revision:";
      $cache = $comment->getCache();
      if (strlen($cache)) {
        $content = $cache;
      } else {
        $content = $this->markupEngine->markupText($content);
        if ($comment->getID()) {
          $comment->setCache($content);

          $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
          $comment->save();
          unset($unguarded);
        }
      }
      $content =
        '<div class="phabricator-remarkup">'.
          $content.
        '</div>';
    } else {
      $title = null;
      $head_content =
        '<div class="differential-comment-nocontent">'.
          "<p>{$author_link} {$verb} this revision.</p>".
        '</div>';
      $content = null;
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
            '<th colspan="3">'.
              phutil_escape_html($changeset->getFilename()).
            '</th>'.
          '</tr>';
        foreach ($inlines as $inline) {
          if (!$inline->getLineLength()) {
            $lines = $inline->getLineNumber();
          } else {
            $lines = $inline->getLineNumber()."\xE2\x80\x93".
                     ($inline->getLineNumber() + $inline->getLineLength());
          }

          $on_target = ($this->target) &&
                       ($this->target->getID() == $changeset->getDiffID());

          $is_visible = false;
          if ($inline->getIsNewFile()) {
            // This comment is on the right side of the versus diff, and visible
            // on the left side of the page.
            if ($this->versusDiffID) {
              if ($changeset->getDiffID() == $this->versusDiffID) {
                $is_visible = true;
              }
            }

            // This comment is on the right side of the target diff, and visible
            // on the right side of the page.
            if ($on_target) {
              $is_visible = true;
            }
          } else {
            // Ths comment is on the left side of the target diff, and visible
            // on the left side of the page.
            if (!$this->versusDiffID) {
              if ($on_target) {
                $is_visible = true;
              }
            }

            // TODO: We still get one edge case wrong here, when we have a
            // versus diff and the file didn't exist in the old version. The
            // comment is visible because we show the left side of the target
            // diff when there's no corresponding file in the versus diff, but
            // we incorrectly link it off-page.
          }

          $where = null;
          if ($is_visible) {
            $lines = phutil_render_tag(
              'a',
              array(
                'href'    => '#inline-'.$inline->getID(),
                'class'   => 'num',
              ),
              $lines);
          } else {
            $diff_id = $changeset->getDiffID();
            $lines = phutil_render_tag(
              'a',
              array(
                'href'    => '?id='.$diff_id.'#inline-'.$inline->getID(),
                'class'   => 'num',
                'target'  => '_blank',
              ),
              $lines." \xE2\x86\x97");
            $where = '(On Diff #'.$diff_id.')';
          }

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
                $unguarded = AphrontWriteGuard::beginScopedUnguardedWrites();
                $inline->save();
                unset($unguarded);
              }
            }
          }

          $inline_render[] =
            '<tr>'.
              '<td class="inline-line-number">'.$lines.'</td>'.
              '<td class="inline-which-diff">'.$where.'</td>'.
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

    $metadata_blocks = array();
    $metadata = $comment->getMetadata();
    $added_reviewers = idx(
      $metadata,
      DifferentialComment::METADATA_ADDED_REVIEWERS);
    if ($added_reviewers) {
      $reviewers = 'Added reviewers: '.$this->renderHandleList(
        $added_reviewers);
      $metadata_blocks[] = $reviewers;
    }

    $added_ccs = idx(
      $metadata,
      DifferentialComment::METADATA_ADDED_CCS);
    if ($added_ccs) {
      $ccs = 'Added CCs: '.$this->renderHandleList($added_ccs);
      $metadata_blocks[] = $ccs;
    }

    if ($metadata_blocks) {
      $metadata_blocks =
        '<div class="differential-comment-metadata">'.
          implode("\n", $metadata_blocks).
        '</div>';
    } else {
      $metadata_blocks = null;
    }

    return phutil_render_tag(
      'div',
      array(
        'class' => "differential-comment {$action_class}",
        'id'    => $comment_anchor,
      ),
      '<div class="differential-comment-head">'.
        '<span class="differential-comment-info">'.$info.'</span>'.
        '<span class="differential-comment-title">'.$title.'</span>'.
        '<div style="clear: both;"></div>'.
      '</div>'.
      '<div class="differential-comment-body" style="'.$background.'">'.
        '<div class="differential-comment-content">'.
          $head_content.
          $metadata_blocks.
          '<div class="differential-comment-core">'.
            $content.
          '</div>'.
          $inline_render.
        '</div>'.
      '</div>');
  }

  private function renderHandleList(array $phids) {
    $result = array();
    foreach ($phids as $phid) {
      $result[] = $this->handles[$phid]->renderLink();
    }
    return implode(', ', $result);
  }

}
