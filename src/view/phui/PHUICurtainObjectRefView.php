<?php

final class PHUICurtainObjectRefView
  extends AphrontTagView {

  private $handle;
  private $epoch;
  private $highlighted;
  private $exiled;
  private $exileNote = false;

  public function setHandle(PhabricatorObjectHandle $handle) {
    $this->handle = $handle;
    return $this;
  }

  public function setEpoch($epoch) {
    $this->epoch = $epoch;
    return $this;
  }

  public function setHighlighted($highlighted) {
    $this->highlighted = $highlighted;
    return $this;
  }

  public function setExiled($is_exiled, $note = false) {
    $this->exiled = $is_exiled;
    $this->exileNote = $note;
    return $this;
  }

  protected function getTagAttributes() {
    $classes = array();
    $classes[] = 'phui-curtain-object-ref-view';

    if ($this->highlighted) {
      $classes[] = 'phui-curtain-object-ref-view-highlighted';
    }

    if ($this->exiled) {
      $classes[] = 'phui-curtain-object-ref-view-exiled';
    }

    $classes = implode(' ', $classes);

    return array(
      'class' => $classes,
    );
  }

  protected function getTagContent() {
    require_celerity_resource('phui-curtain-object-ref-view-css');

    $viewer = $this->getViewer();
    $handle = $this->handle;

    $more_rows = array();

    $epoch = $this->epoch;
    if ($epoch !== null) {
      $epoch_view = phabricator_dual_datetime($epoch, $viewer);

      $epoch_cells = array();

      $epoch_cells[] = phutil_tag(
        'td',
        array(
          'class' => 'phui-curtain-object-ref-view-epoch-cell',
        ),
        $epoch_view);

      $more_rows[] = phutil_tag('tr', array(), $epoch_cells);
    }

    if ($this->exiled) {
      if ($this->exileNote !== false) {
        $exile_note = $this->exileNote;
      } else {
        $exile_note = pht('No View Permission');
      }

      $exiled_view = array(
        id(new PHUIIconView())->setIcon('fa-eye-slash red'),
        ' ',
        $exile_note,
      );

      $exiled_cells = array();
      $exiled_cells[] = phutil_tag(
        'td',
        array(
          'class' => 'phui-curtain-object-ref-view-exiled-cell',
        ),
        $exiled_view);

      $more_rows[] = phutil_tag('tr', array(), $exiled_cells);
    }

    $header_cells = array();

    $image_view = $this->newImage();

    if ($more_rows) {
      $row_count = 1 + count($more_rows);
    } else {
      $row_count = null;
    }

    $header_cells[] = phutil_tag(
      'td',
      array(
        'rowspan' => $row_count,
        'class' => 'phui-curtain-object-ref-view-image-cell',
      ),
      $image_view);

    $title_view = $this->newTitle();

    $header_cells[] = phutil_tag(
      'td',
      array(
        'class' => 'phui-curtain-object-ref-view-title-cell',
      ),
      $title_view);

    $rows = array();

    if (!$more_rows) {
      $title_row_class = 'phui-curtain-object-ref-view-without-content';
    } else {
      $title_row_class = 'phui-curtain-object-ref-view-with-content';
    }

    $rows[] = phutil_tag(
      'tr',
      array(
        'class' => $title_row_class,
      ),
      $header_cells);

    $body = phutil_tag(
      'tbody',
      array(),
      array(
        $rows,
        $more_rows,
      ));

    return phutil_tag('table', array(), $body);
  }

  private function newTitle() {
    $title_view = null;
    $handle = $this->handle;

    if ($handle) {
      $title_view = $handle->renderLink();
    }

    return $title_view;
  }

  private function newImage() {
    $image_uri = $this->getImageURI();
    $target_uri = $this->getTargetURI();

    $icon_view = null;
    if ($image_uri == null) {
      $icon_view = $this->newIconView();
    }

    if ($image_uri !== null) {
      $image_view = javelin_tag(
        'a',
        array(
          'style' => sprintf('background-image: url(%s)', $image_uri),
          'href' => $target_uri,
          'aural' => false,
        ));
    } else if ($icon_view !== null) {
      $image_view = javelin_tag(
        'a',
        array(
          'href' => $target_uri,
          'class' => 'phui-curtain-object-ref-view-icon-image',
          'aural' => false,
        ),
        $icon_view);
    } else {
      $image_view = null;
    }

    return $image_view;
  }

  private function getTargetURI() {
    $target_uri = null;
    $handle = $this->handle;

    if ($handle) {
      $target_uri = $handle->getURI();
    }

    return $target_uri;
  }

  private function getImageURI() {
    $image_uri = null;
    $handle = $this->handle;

    if ($handle) {
      $image_uri = $handle->getImageURI();
    }

    return $image_uri;
  }

  private function newIconView() {
    $handle = $this->handle;

    if ($handle) {
      $icon_view = id(new PHUIIconView())
        ->setIcon($handle->getIcon());
    }

    return $icon_view;
  }


}
