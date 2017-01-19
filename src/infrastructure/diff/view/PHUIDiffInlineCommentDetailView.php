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
  private $objectOwnerPHID;

  public function setInlineComment(PhabricatorInlineCommentInterface $comment) {
    $this->inlineComment = $comment;
    return $this;
  }

  public function isHidden() {
    return $this->inlineComment->isHidden();
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

  public function setObjectOwnerPHID($phid) {
    $this->objectOwnerPHID = $phid;
    return $this;
  }

  public function getObjectOwnerPHID() {
    return $this->objectOwnerPHID;
  }

  public function getAnchorName() {
    $inline = $this->inlineComment;
    if ($inline->getID()) {
      return 'inline-'.$inline->getID();
    }
    return null;
  }

  public function getScaffoldCellID() {
    $anchor = $this->getAnchorName();
    if ($anchor) {
      return 'anchor-'.$anchor;
    }
    return null;
  }

  public function render() {
    require_celerity_resource('phui-inline-comment-view-css');
    $inline = $this->inlineComment;

    $classes = array(
      'differential-inline-comment',
    );

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

    $draft_text = null;
    if (!$is_synthetic) {
      // This display is controlled by CSS
      $draft_text = id(new PHUITagView())
        ->setType(PHUITagView::TYPE_SHADE)
        ->setName(pht('Unsubmitted'))
        ->setSlimShady(true)
        ->setShade(PHUITagView::COLOR_RED)
        ->addClass('mml inline-draft-text');
    }

    $ghost_tag = null;
    $ghost = $inline->getIsGhost();
    $ghost_id = null;
    if ($ghost) {
      if ($ghost['new']) {
        $ghosticon = 'fa-fast-forward';
        $reason = pht('View on forward revision');
      } else {
        $ghosticon = 'fa-fast-backward';
        $reason = pht('View on previous revision');
      }

      $ghost_icon = id(new PHUIIconView())
        ->setIcon($ghosticon)
        ->addSigil('has-tooltip')
        ->setMetadata(
          array(
            'tip' => $reason,
            'size' => 300,
          ));
      $ghost_tag = phutil_tag(
        'a',
        array(
          'class' => 'ghost-icon',
          'href' => $ghost['href'],
          'target' => '_blank',
        ),
        $ghost_icon);
      $classes[] = 'inline-comment-ghost';
    }

    // I think this is unused
    if ($inline->getHasReplies()) {
      $classes[] = 'inline-comment-has-reply';
    }

    if ($inline->getReplyToCommentPHID()) {
      $classes[] = 'inline-comment-is-reply';
    }

    $viewer_phid = $this->getUser()->getPHID();
    $owner_phid = $this->getObjectOwnerPHID();

    if ($viewer_phid) {
      if ($viewer_phid == $owner_phid) {
        $classes[] = 'viewer-is-object-owner';
      }
    }

    $nextprev = null;
    if (!$this->preview) {
      $nextprev = new PHUIButtonBarView();
      $nextprev->setBorderless(true);
      $nextprev->addClass('inline-button-divider');


      $up = id(new PHUIButtonView())
        ->setTag('a')
        ->setTooltip(pht('Previous'))
        ->setIcon('fa-chevron-up')
        ->addSigil('differential-inline-prev')
        ->setMustCapture(true);

      $down = id(new PHUIButtonView())
        ->setTag('a')
        ->setTooltip(pht('Next'))
        ->setIcon('fa-chevron-down')
        ->addSigil('differential-inline-next')
        ->setMustCapture(true);

      if ($this->canHide()) {
        $hide = id(new PHUIButtonView())
          ->setTag('a')
          ->setTooltip(pht('Hide Comment'))
          ->setIcon('fa-times')
          ->addSigil('hide-inline')
          ->setMustCapture(true);

        $nextprev->addButton($hide);
      }

      $nextprev->addButton($up);
      $nextprev->addButton($down);

      $action_buttons = array();
      if ($this->allowReply) {
        if (!$is_synthetic) {
          // NOTE: No product reason why you can't reply to these, but the reply
          // mechanism currently sends the inline comment ID to the server, not
          // file/line information, and synthetic comments don't have an inline
          // comment ID.

          $action_buttons[] = id(new PHUIButtonView())
            ->setTag('a')
            ->setIcon('fa-reply')
            ->setTooltip(pht('Reply'))
            ->addSigil('differential-inline-reply')
            ->setMustCapture(true);
        }
      }
    }

    $anchor_name = $this->getAnchorName();

    if ($this->editable && !$this->preview) {
      $action_buttons[] = id(new PHUIButtonView())
        ->setTag('a')
        ->setIcon('fa-pencil')
        ->setTooltip(pht('Edit'))
        ->addSigil('differential-inline-edit')
        ->setMustCapture(true);

      $action_buttons[] = id(new PHUIButtonView())
        ->setTag('a')
        ->setIcon('fa-trash-o')
        ->setTooltip(pht('Delete'))
        ->addSigil('differential-inline-delete')
        ->setMustCapture(true);

    } else if ($this->preview) {
      $links[] = javelin_tag(
        'a',
        array(
          'class'  => 'inline-button-divider pml msl',
          'meta'  => array(
            'anchor' => $anchor_name,
          ),
          'sigil'  => 'differential-inline-preview-jump',
        ),
        pht('View'));

      $action_buttons[] = id(new PHUIButtonView())
        ->setTag('a')
        ->setTooltip(pht('Delete'))
        ->setIcon('fa-trash-o')
        ->addSigil('differential-inline-delete')
        ->setMustCapture(true);
    }

    $done_button = null;

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

      // If you don't have permission to mark the comment as "Done", you also
      // can not see the draft state.
      if (!$this->getCanMarkDone()) {
        $draft_state = false;
      }

      if ($is_done) {
        $classes[] = 'inline-is-done';
      }

      if ($draft_state) {
        $classes[] = 'inline-state-is-draft';
      }

      if ($this->getCanMarkDone()) {
        $done_input = javelin_tag(
          'input',
          array(
            'type' => 'checkbox',
            'checked' => ($is_done ? 'checked' : null),
            'disabled' => ($this->getCanMarkDone() ? null : 'disabled'),
            'class' => 'differential-inline-done',
            'sigil' => 'differential-inline-done',
          ));
        $done_button = phutil_tag(
          'label',
          array(
            'class' => 'differential-inline-done-label '.
                        ($this->getCanMarkDone() ? null : 'done-is-disabled'),
          ),
          array(
            $done_input,
            pht('Done'),
          ));
      } else {
        if ($is_done) {
          $icon = id(new PHUIIconView())->setIcon('fa-check sky msr');
          $label = pht('Done');
          $class = 'button-done';
        } else {
          $icon = null;
          $label = pht('Not Done');
          $class = 'button-not-done';
        }
        $done_button = phutil_tag(
          'div',
          array(
            'class' => 'done-label '.$class,
          ),
          array(
            $icon,
            $label,
          ));
      }
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

    if ($inline->isDraft() && !$is_synthetic) {
      $classes[] = 'inline-state-is-draft';
    }
    if ($is_synthetic) {
      $classes[] = 'differential-inline-comment-synthetic';
    }
    $classes = implode(' ', $classes);

    $author_owner = null;
    if ($is_synthetic) {
      $author = $inline->getSyntheticAuthor();
    } else {
      $author = $handles[$inline->getAuthorPHID()]->getName();
      if ($inline->getAuthorPHID() == $this->objectOwnerPHID) {
        $author_owner = id(new PHUITagView())
          ->setType(PHUITagView::TYPE_SHADE)
          ->setName(pht('Author'))
          ->setSlimShady(true)
          ->setShade(PHUITagView::COLOR_YELLOW)
          ->addClass('mml');
      }
    }

    $actions = null;
    if ($action_buttons) {
      $actions = new PHUIButtonBarView();
      $actions->setBorderless(true);
      $actions->addClass('inline-button-divider');
      foreach ($action_buttons as $button) {
        $actions->addButton($button);
      }
    }

    $group_left = phutil_tag(
      'div',
      array(
        'class' => 'inline-head-left',
      ),
      array(
        $author,
        $author_owner,
        $draft_text,
        $ghost_tag,
      ));

    $group_right = phutil_tag(
      'div',
      array(
        'class' => 'inline-head-right',
      ),
      array(
        $anchor,
        $done_button,
        $links,
        $actions,
        $nextprev,
      ));

    $markup = javelin_tag(
      'div',
      array(
        'class' => $classes,
        'sigil' => $sigil,
        'meta'  => $metadata,
      ),
      array(
        phutil_tag_div('differential-inline-comment-head grouped', array(
          $group_left,
          $group_right,
        )),
        phutil_tag_div(
          'differential-inline-comment-content',
          phutil_tag_div('phabricator-remarkup', $content)),
      ));

    return $markup;
  }

  private function canHide() {
    $inline = $this->inlineComment;

    if ($inline->isDraft()) {
      return false;
    }

    if (!$inline->getID()) {
      return false;
    }

    $viewer = $this->getUser();
    if (!$viewer->isLoggedIn()) {
      return false;
    }

    if (!$inline->supportsHiding()) {
      return false;
    }

    return true;
  }

}
