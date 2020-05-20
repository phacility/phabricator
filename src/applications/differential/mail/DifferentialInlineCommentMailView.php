<?php

final class DifferentialInlineCommentMailView
  extends DifferentialMailView {

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

        $inline_object = $comment->newInlineCommentObject();
        $document_engine_key = $inline_object->getDocumentEngineKey();

        $is_last_inline = ($inline_key == $last_inline_key);

        $context_text = null;
        $context_html = null;

        if ($parent_phid) {
          $parent = idx($parents, $parent_phid);
          if ($parent) {
            $context_text = $this->renderInline($parent, false, true);
            $context_html = $this->renderInline($parent, true, true);
          }
        } else if ($document_engine_key !== null) {
          // See T13513. If an inline was left on a rendered document, don't
          // include the patch context. Document engines currently can not
          // render to mail targets, and using the line numbers as raw source
          // lines produces misleading context.

          $patch_text = null;
          $context_text = $this->renderPatch($comment, $patch_text, false);

          $patch_html = null;
          $context_html = $this->renderPatch($comment, $patch_html, true);
        } else {
          $patch_text = $this->getPatch($hunk_parser, $comment, false);
          $context_text = $this->renderPatch($comment, $patch_text, false);

          $patch_html = $this->getPatch($hunk_parser, $comment, true);
          $context_html = $this->renderPatch($comment, $patch_html, true);
        }

        $render_text = $this->renderInline($comment, false, false);
        $render_html = $this->renderInline($comment, true, false);

        $section->addPlaintextFragment($context_text);
        $section->addPlaintextFragment($spacer_text);
        $section->addPlaintextFragment($render_text);

        $html_fragment = $this->renderContentBox(
          array(
            $context_html,
            $render_html,
          ));

        $section->addHTMLFragment($html_fragment);

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

    if ($is_html) {
      $style = array(
        'margin: 8px 0;',
        'padding: 0 12px;',
      );

      if ($is_quote) {
        $style[] = 'color: #74777D;';
      }

      $content = phutil_tag(
        'div',
        array(
          'style' => implode(' ', $style),
        ),
        $content);
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

    $attributes = array(
      'style' => 'padding: 0; margin: 8px;',
    );

    $engine = PhabricatorMarkupEngine::newMarkupEngine(array())
      ->setConfig('viewer', $viewer)
      ->setConfig('uri.base', $production_uri)
      ->setConfig('default.p.attributes', $attributes)
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
      'padding: 0;',
      'background: #F7F7F7;',
      'border-color: #e3e4e8;',
      'border-style: solid;',
      'border-width: 0 0 1px 0;',
      'margin: 0;',
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
    DifferentialTransactionComment $comment,
    $is_html) {

    $changeset = $this->getChangeset($comment->getChangesetID());
    $is_new = $comment->getIsNewFile();
    $start = $comment->getLineNumber();
    $length = $comment->getLineLength();

    // By default, show one line of context around the target inline.
    $context = 1;

    // If the inline is at least 3 lines long, don't show any extra context.
    if ($length >= 2) {
      $context = 0;
    }

    // If the inline is more than 7 lines long, only show the first 7 lines.
    if ($length >= 6) {
      $length = 6;
    }

    if (!$is_html) {
      $hunks = $changeset->getHunks();
      $patch = $parser->makeContextDiff(
        $hunks,
        $is_new,
        $start,
        $length,
        $context);
      $patch = phutil_split_lines($patch);

      // Remove the "@@ -x,y +u,v @@" line.
      array_shift($patch);

      return implode('', $patch);
    }

    $viewer = $this->getViewer();
    $engine = new PhabricatorMarkupEngine();

    if ($is_new) {
      $offset_mode = 'new';
    } else {
      $offset_mode = 'old';
    }

    // See PHI894. Use the parse cache since we can end up with a large
    // rendering cost otherwise when users or bots leave hundreds of inline
    // comments on diffs with long recipient lists.
    $cache_key = $changeset->getID();

    $viewstate = new PhabricatorChangesetViewState();

    $parser = id(new DifferentialChangesetParser())
      ->setRenderCacheKey($cache_key)
      ->setViewer($viewer)
      ->setViewstate($viewstate)
      ->setChangeset($changeset)
      ->setOffsetMode($offset_mode)
      ->setMarkupEngine($engine);

    $parser->setRenderer(new DifferentialChangesetOneUpMailRenderer());

    return $parser->render(
      $start - $context,
      $length + (2 * $context),
      array());
  }

  private function renderPatch(
    DifferentialTransactionComment $comment,
    $patch,
    $is_html) {

    if ($is_html) {
      if ($patch !== null) {
        $patch = $this->renderCodeBlock($patch);
      }
    }

    $header = $this->renderHeader($comment, $is_html, false);

    if ($patch === null) {
      $patch = array(
        $header,
      );
    } else {
      $patch = array(
        $header,
        "\n",
        $patch,
      );
    }

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

    // Only show the filename.
    $path = basename($path);

    $start = $comment->getLineNumber();
    $length = $comment->getLineLength();
    if ($length) {
      $range = pht('%s-%s', $start, $start + $length);
    } else {
      $range = $start;
    }

    $header = "{$path}:{$range}";
    if ($is_html) {
      $header = $this->renderHeaderBold($header);
    }

    if ($with_author) {
      $author = $this->getAuthor($comment->getAuthorPHID());
    } else {
      $author = null;
    }

    if ($author) {
      $byline = $author->getName();

      if ($is_html) {
        $byline = $this->renderHeaderBold($byline);
      }

      $header = pht('%s wrote in %s', $byline, $header);
    }

    if ($is_html) {
      $link_href = $this->getInlineURI($comment);
      if ($link_href) {
        $link_style = array(
          'float: right;',
          'text-decoration: none;',
        );

        $link = phutil_tag(
          'a',
          array(
            'style' => implode(' ', $link_style),
            'href' => $link_href,
          ),
          array(
            pht('View Inline'),

            // See PHI920. Add a space after the link so we render this into
            // the document:
            //
            //   View Inline filename.txt
            //
            // Otherwise, we render "Inlinefilename.txt" and double-clicking
            // the file name selects the word "Inline" as well.
            ' ',
          ));
      } else {
        $link = null;
      }

      $header = $this->renderHeaderBlock(array($link, $header));
    }

    return $header;
  }

  private function getInlineURI(DifferentialTransactionComment $comment) {
    $changeset = $this->getChangeset($comment->getChangesetID());
    if (!$changeset) {
      return null;
    }

    $diff = $changeset->getDiff();
    if (!$diff) {
      return null;
    }

    $revision = $diff->getRevision();
    if (!$revision) {
      return null;
    }

    $link_href = '/'.$revision->getMonogram().'#inline-'.$comment->getID();
    $link_href = PhabricatorEnv::getProductionURI($link_href);

    return $link_href;
  }


}
