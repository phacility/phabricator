<?php

final class PHUIDiffInlineCommentDetailView
  extends PHUIDiffInlineCommentView {

  private $inlineComment;
  private $handles;
  private $markupEngine;
  private $editable;
  private $preview;
  private $allowReply;
  private $renderer;
  private $canMarkDone;

  public function setInlineComment(PhabricatorInlineCommentInterface $comment) {
    $this->inlineComment = $comment;
    return $this;
  }

  public function setHandles(array $handles) {
    assert_instances_of($handles, 'PhabricatorObjectHandle');
    $this->handles = $handles;
    return $this;
  }

  public function setMarkupEngine(PhabricatorMarkupEngine $engine) {
    $this->markupEngine = $engine;
    return $this;
  }

  public function setEditable($editable) {
    $this->editable = $editable;
    return $this;
  }

  public function setPreview($preview) {
    $this->preview = $preview;
    return $this;
  }

  public function setAllowReply($allow_reply) {
    $this->allowReply = $allow_reply;
    return $this;
  }

  public function setRenderer($renderer) {
    $this->renderer = $renderer;
    return $this;
  }

  public function getRenderer() {
    return $this->renderer;
  }

  public function setCanMarkDone($can_mark_done) {
    $this->canMarkDone = $can_mark_done;
    return $this;
  }

  public function getCanMarkDone() {
    return $this->canMarkDone;
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
      'phid' => $inline->getPHID(),
      'changesetID' => $inline->getChangesetID(),
      'number' => $inline->getLineNumber(),
      'length' => $inline->getLineLength(),
      'isNewFile' => (bool)$inline->getIsNewFile(),
      'on_right' => $this->getIsOnRight(),
      'original' => $inline->getContent(),
      'replyToCommentPHID' => $inline->getReplyToCommentPHID(),
    );

    $sigil = 'differential-inline-comment';
    if ($this->preview) {
      $sigil = $sigil.' differential-inline-comment-preview';
    }

    $classes = array(
      'differential-inline-comment',
    );

    $content = $inline->getContent();
    $handles = $this->handles;

    $links = array();

    $is_synthetic = false;
    if ($inline->getSyntheticAuthor()) {
      $is_synthetic = true;
    }

    $is_draft = false;
    if ($inline->isDraft() && !$is_synthetic) {
      $links[] = pht('Not Submitted Yet');
      $is_draft = true;
    }


    // TODO: This stuff is nonfinal, just making it do something.
    if ($inline->getHasReplies()) {
      $links[] = pht('Has Reply');
      $classes[] = 'inline-has-reply';
    }
    if ($inline->getReplyToCommentPHID()) {
      $links[] = pht('Is Reply');
    }

    if (!$this->preview) {
      $links[] = javelin_tag(
        'a',
        array(
          'href'  => '#',
          'mustcapture' => true,
          'sigil' => 'differential-inline-prev',
        ),
        pht('Previous'));

      $links[] = javelin_tag(
        'a',
        array(
          'href'  => '#',
          'mustcapture' => true,
          'sigil' => 'differential-inline-next',
        ),
        pht('Next'));

      if ($this->allowReply) {

        if (!$is_synthetic) {

          // NOTE: No product reason why you can't reply to these, but the reply
          // mechanism currently sends the inline comment ID to the server, not
          // file/line information, and synthetic comments don't have an inline
          // comment ID.

          $links[] = javelin_tag(
            'a',
            array(
              'href'        => '#',
              'mustcapture' => true,
              'sigil'       => 'differential-inline-reply',
            ),
            pht('Reply'));
        }

      }
    }

    $anchor_name = 'inline-'.$inline->getID();

    if ($this->editable && !$this->preview) {
      $links[] = javelin_tag(
        'a',
        array(
          'href'        => '#',
          'mustcapture' => true,
          'sigil'       => 'differential-inline-edit',
        ),
        pht('Edit'));
      $links[] = javelin_tag(
        'a',
        array(
          'href'        => '#',
          'mustcapture' => true,
          'sigil'       => 'differential-inline-delete',
        ),
        pht('Delete'));
    } else if ($this->preview) {
      $links[] = javelin_tag(
        'a',
        array(
          'meta'        => array(
            'anchor' => $anchor_name,
          ),
          'sigil'       => 'differential-inline-preview-jump',
        ),
        pht('Not Visible'));
      $links[] = javelin_tag(
        'a',
        array(
          'href'        => '#',
          'mustcapture' => true,
          'sigil'       => 'differential-inline-delete',
        ),
        pht('Delete'));
    }

    if (!$is_synthetic) {
      $draft_state = false;
      switch ($inline->getFixedState()) {
        case PhabricatorInlineCommentInterface::STATE_DRAFT:
          $is_done = ($this->getCanMarkDone());
          $draft_state = true;
          break;
        case PhabricatorInlineCommentInterface::STATE_UNDRAFT:
          $is_done = !($this->getCanMarkDone());
          $draft_state = true;
          break;
        case PhabricatorInlineCommentInterface::STATE_DONE:
          $is_done = true;
          break;
        default:
        case PhabricatorInlineCommentInterface::STATE_UNDONE:
          $is_done = false;
          break;
      }

      if ($is_done) {
        $classes[] = 'inline-is-done';
      }

      if ($draft_state) {
        $classes[] = 'inline-state-is-draft';
      }

      $links[] = javelin_tag(
        'input',
        array(
          'type' => 'checkbox',
          'checked' => ($is_done ? 'checked' : null),
          'disabled' => ($this->getCanMarkDone() ? null : 'disabled'),
          'class' => 'differential-inline-done',
          'sigil' => 'differential-inline-done',
        ));
    }

    if ($links) {
      $links = phutil_tag(
        'span',
        array('class' => 'differential-inline-comment-links'),
        phutil_implode_html(" \xC2\xB7 ", $links));
    } else {
      $links = null;
    }

    $content = $this->markupEngine->getOutput(
      $inline,
      PhabricatorInlineCommentInterface::MARKUP_FIELD_BODY);

    if ($this->preview) {
      $anchor = null;
    } else {
      $anchor = phutil_tag(
        'a',
        array(
          'name'    => $anchor_name,
          'id'      => $anchor_name,
          'class'   => 'differential-inline-comment-anchor',
        ),
        '');
    }

    if ($is_draft) {
      $classes[] = 'differential-inline-comment-unsaved-draft';
    }
    if ($is_synthetic) {
      $classes[] = 'differential-inline-comment-synthetic';
    }
    $classes = implode(' ', $classes);

    if ($is_synthetic) {
      $author = $inline->getSyntheticAuthor();
    } else {
      $author = $handles[$inline->getAuthorPHID()]->getName();
    }

    $line = phutil_tag(
      'span',
      array('class' => 'differential-inline-comment-line'),
      $line);

    $markup = javelin_tag(
      'div',
      array(
        'class' => $classes,
        'sigil' => $sigil,
        'meta'  => $metadata,
      ),
      array(
        phutil_tag_div('differential-inline-comment-head', array(
          $anchor,
          $links,
          ' ',
          $line,
          ' ',
          $author,
        )),
        phutil_tag_div(
          'differential-inline-comment-content',
          phutil_tag_div('phabricator-remarkup', $content)),
      ));

    return $markup;
  }

}
