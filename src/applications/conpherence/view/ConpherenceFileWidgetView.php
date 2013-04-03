<?php

/**
 * @group conpherence
 */
final class ConpherenceFileWidgetView extends ConpherenceWidgetView {

  public function render() {
    require_celerity_resource('sprite-docs-css');
    $conpherence = $this->getConpherence();
    $widget_data = $conpherence->getWidgetData();
    $files = $widget_data['files'];
    $files_authors = $widget_data['files_authors'];
    $files_html = array();

    foreach ($files as $file) {
      $icon_class = $file->getDisplayIconForMimeType();
      $icon_view = phutil_tag(
        'div',
        array(
          'class' => 'file-icon sprite-docs '.$icon_class
        ),
        '');
      $file_view = id(new PhabricatorFileLinkView())
        ->setFilePHID($file->getPHID())
        ->setFileName(phutil_utf8_shorten($file->getName(), 28))
        ->setFileViewable($file->isViewableImage())
        ->setFileViewURI($file->getBestURI())
        ->setCustomClass('file-title');

      $who_done_it_text = '';
      // system generated files don't have authors
      if ($file->getAuthorPHID()) {
        $who_done_it_text = pht(
          'by %s ',
          $files_authors[$file->getPHID()]->renderLink());
      }
      $date_text = phabricator_relative_date(
        $file->getDateCreated(),
        $this->getUser());

      $who_done_it = phutil_tag(
        'div',
        array(
          'class' => 'file-uploaded-by'
        ),
        pht('Uploaded %s%s.', $who_done_it_text, $date_text));

      $extra = '';
      if ($file->isViewableImage()) {
        $meta = $file_view->getMetadata();
        $extra = javelin_tag(
          'a',
          array(
            'sigil' => 'lightboxable',
            'meta' => $meta,
            'class' => 'file-extra',
          ),
          phutil_tag(
            'img',
            array(
              'src' => $file->getThumb160x120URI()
            ),
            ''));
      }

      $divider = phutil_tag(
        'div',
        array(
          'class' => 'divider'
        ),
        '');

      $files_html[] = phutil_tag(
        'div',
        array(
          'class' => 'file-entry'
        ),
        array(
          $icon_view,
          $file_view,
          $who_done_it,
          $extra,
          $divider
        ));
    }

    return phutil_tag(
      'div',
      array('class' => 'file-list'),
      $files_html);

  }

}
