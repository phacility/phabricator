<?php

final class DifferentialInlineCommentMailView
  extends Phobject {

  private $viewer;
  private $inlines;
  private $changesets;
  private $authors;

  public function setViewer(PhabricatorUser $viewer) {
    $this->viewer = $viewer;
    return $this;
  }

  public function getViewer() {
    return $this->viewer;
  }

  public function setInlines($inlines) {
    $this->inlines = $inlines;
    return $this;
  }

  public function getInlines() {
    return $this->inlines;
  }

  public function buildMailSection() {
    $inlines = $this->getInlines();

    $comments = mpull($inlines, 'getComment');
    $comments = mpull($comments, null, 'getPHID');
    $parents = $this->loadParents($comments);
    $all_comments = $comments + $parents;

    $this->changesets = $this->loadChangesets($all_comments);
    $this->authors = $this->loadAuthors($all_comments);
    $groups = $this->groupInlines($inlines);

    $hunk_parser = new DifferentialHunkParser();

    $spacer_text = null;
    $spacer_html = phutil_tag('br');

    $section = new PhabricatorMetaMTAMailSection();

    $last_group_key = last_key($groups);
    foreach ($groups as $changeset_id => $group) {
      $changeset = $this->getChangeset($changeset_id);
      if (!$changeset) {
        continue;
      }

      $is_last_group = ($changeset_id == $last_group_key);

      $last_inline_key = last_key($group);
      foreach ($group as $inline_key => $inline) {
        $comment = $inline->getComment();
        $parent_phid = $comment->getReplyToCommentPHID();

        $is_last_inline = ($inline_key == $last_inline_key);

        $context_text = null;
        $context_html = null;

        if ($parent_phid) {
          $parent = idx($parents, $parent_phid);
          if ($parent) {
            $context_text = $this->renderInline($parent, false, true);
            $context_html = $this->renderInline($parent, true, true);
          }
        } else {
          $patch = $this->getPatch($hunk_parser, $comment);
          $context_text = $this->renderPatch($comment, $patch, false);
          $context_html = $this->renderPatch($comment, $patch, true);
        }

        $render_text = $this->renderInline($comment, false, false);
        $render_html = $this->renderInline($comment, true, false);

        $section->addPlaintextFragment($context_text);
        $section->addHTMLFragment($context_html);

        $section->addPlaintextFragment($spacer_text);
        $section->addHTMLFragment($spacer_html);

        $section->addPlaintextFragment($render_text);
        $section->addHTMLFragment($render_html);

        if (!$is_last_group || !$is_last_inline) {
          $section->addPlaintextFragment($spacer_text);
          $section->addHTMLFragment($spacer_html);
        }
      }
    }

    return $section;
  }

  private function loadChangesets(array $comments) {
    if (!$comments) {
      return array();
    }

    $ids = array();
    foreach ($comments as $comment) {
      $ids[] = $comment->getChangesetID();
    }

    $changesets = id(new DifferentialChangesetQuery())
      ->setViewer($this->getViewer())
      ->withIDs($ids)
      ->needHunks(true)
      ->execute();

    return mpull($changesets, null, 'getID');
  }

  private function loadParents(array $comments) {
    $viewer = $this->getViewer();

    $phids = array();
    foreach ($comments as $comment) {
      $parent_phid = $comment->getReplyToCommentPHID();
      if (!$parent_phid) {
        continue;
      }
      $phids[] = $parent_phid;
    }

    if (!$phids) {
      return array();
    }

    $parents = id(new DifferentialDiffInlineCommentQuery())
      ->setViewer($viewer)
      ->withPHIDs($phids)
      ->execute();

    return mpull($parents, null, 'getPHID');
  }

  private function loadAuthors(array $comments) {
    $viewer = $this->getViewer();

    $phids = array();
    foreach ($comments as $comment) {
      $author_phid = $comment->getAuthorPHID();
      if (!$author_phid) {
        continue;
      }
      $phids[] = $author_phid;
    }

    if (!$phids) {
      return array();
    }

    return $viewer->loadHandles($phids);
  }

  private function groupInlines(array $inlines) {
    return DifferentialTransactionComment::sortAndGroupInlines(
      $inlines,
      $this->changesets);
  }

  private function renderInline(
    DifferentialTransactionComment $comment,
    $is_html,
    $is_quote) {

    $changeset = $this->getChangeset($comment->getChangesetID());
    if (!$changeset) {
      return null;
    }

    $content = $comment->getContent();
    $content = $this->renderRemarkupContent($content, $is_html);

    if ($is_quote) {
      $header = $this->renderHeader($comment, $is_html, true);
    } else {
      $header = null;
    }

    $parts = array(
      $header,
      "\n",
      $content,
    );

    if (!$is_html) {
      $parts = implode('', $parts);
      $parts = trim($parts);
    }

    if ($is_quote) {
      if ($is_html) {
        $parts = $this->quoteHTML($parts);
      } else {
        $parts = $this->quoteText($parts);
      }
    }

    return $parts;
  }

  private function renderRemarkupContent($content, $is_html) {
    $viewer = $this->getViewer();
    $production_uri = PhabricatorEnv::getProductionURI('/');

    if ($is_html) {
      $mode = PhutilRemarkupEngine::MODE_HTML_MAIL;
    } else {
      $mode = PhutilRemarkupEngine::MODE_TEXT;
    }

    $engine = PhabricatorMarkupEngine::newMarkupEngine(array())
      ->setConfig('viewer', $viewer)
      ->setConfig('uri.base', $production_uri)
      ->setMode($mode);

    try {
      return $engine->markupText($content);
    } catch (Exception $ex) {
      return $content;
    }
  }

  private function getChangeset($id) {
    return idx($this->changesets, $id);
  }

  private function getAuthor($phid) {
    if (isset($this->authors[$phid])) {
      return $this->authors[$phid];
    }
    return null;
  }

  private function quoteText($block) {
    $block = phutil_split_lines($block);
    foreach ($block as $key => $line) {
      $block[$key] = '> '.$line;
    }

    return implode('', $block);
  }

  private function quoteHTML($block) {
    $styles = array(
      'padding: 4px 8px;',
      'background: #F8F9FC;',
      'border-left: 3px solid #a7b5bf;',
    );

    $styles = implode(' ', $styles);

    return phutil_tag(
      'div',
      array(
        'style' => $styles,
      ),
      $block);
  }

  private function getPatch(
    DifferentialHunkParser $parser,
    DifferentialTransactionComment $comment) {

    $changeset = $this->getChangeset($comment->getChangesetID());
    $hunks = $changeset->getHunks();

    $is_new = $comment->getIsNewFile();
    $start = $comment->getLineNumber();
    $length = $comment->getLineLength();

    $diff = $parser->makeContextDiff($hunks, $is_new, $start, $length, 1);

    return $diff;
  }

  private function renderPatch(
    DifferentialTransactionComment $comment,
    $patch,
    $is_html) {

    $patch = phutil_split_lines($patch);

    // Remove the "@@ -x,y +u,v @@" line.
    array_shift($patch);

    $patch = implode('', $patch);

    if ($is_html) {
      $style = array(
        'font: 11px/15px "Menlo", "Consolas", "Monaco", monospace;',
        'padding: 0',
        'margin: 0;',
      );

      $style = implode(' ', $style);
      $patch = phutil_tag(
        'pre',
        array(
          'style' => $style,
        ),
        $patch);
    }

    $header = $this->renderHeader($comment, $is_html, false);

    $patch = array(
      $header,
      "\n",
      $patch,
    );

    if (!$is_html) {
      $patch = implode('', $patch);
      $patch = $this->quoteText($patch);
    } else {
      $patch = $this->quoteHTML($patch);
    }

    return $patch;
  }

  private function renderHeader(
    DifferentialTransactionComment $comment,
    $is_html,
    $with_author) {

    $changeset = $this->getChangeset($comment->getChangesetID());
    $path = $changeset->getFilename();

    $start = $comment->getLineNumber();
    $length = $comment->getLineLength();
    if ($length) {
      $range = pht('%s-%s', $start, $start + $length);
    } else {
      $range = $start;
    }

    $header = "{$path}:{$range}";
    if ($is_html) {
      $header = phutil_tag('strong', array(), $header);
    }

    if ($with_author) {
      $author = $this->getAuthor($comment->getAuthorPHID());
    } else {
      $author = null;
    }

    if ($author) {
      $byline = '@'.$author->getName();

      if ($is_html) {
        $byline = phutil_tag('strong', array(), $byline);
      }

      $header = pht('%s wrote in %s', $byline, $header);
    } else {
      $header = pht('In %s', $header);
    }

    if ($is_html) {
      $header = phutil_tag(
        'div',
        array(
          'style' => 'font-style: italic',
        ),
        $header);
    }

    return $header;
  }

}
