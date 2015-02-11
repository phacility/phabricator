<?php

final class ConpherenceFileWidgetView extends ConpherenceWidgetView {

  public function render() {
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
          'class' => 'file-icon phui-font-fa phui-icon-view '.$icon_class,
        ),
        '');
      $file_view = id(new PhabricatorFileLinkView())
        ->setFilePHID($file->getPHID())
        ->setFileName(id(new PhutilUTF8StringTruncator())
          ->setMaximumGlyphs(28)
          ->truncateString($file->getName()))
        ->setFileViewable($file->isViewableImage())
        ->setFileViewURI($file->getBestURI())
        ->setCustomClass('file-title');

      $who_done_it_text = '';
      // system generated files don't have authors
      if ($file->getAuthorPHID()) {
        $who_done_it_text = pht(
          'By %s ',
          $files_authors[$file->getPHID()]->renderLink());
      }
      $date_text = phabricator_relative_date(
        $file->getDateCreated(),
        $this->getUser());

      $who_done_it = phutil_tag(
        'div',
        array(
          'class' => 'file-uploaded-by',
        ),
        pht('%s%s.', $who_done_it_text, $date_text));

      $files_html[] = phutil_tag(
        'div',
        array(
          'class' => 'file-entry',
        ),
        array(
          $icon_view,
          $file_view,
          $who_done_it,
        ));
    }

    if (empty($files)) {
      $files_html[] = javelin_tag(
        'div',
        array(
          'class' => 'no-files',
          'sigil' => 'no-files',
        ),
        pht('No files.'));
    }

    return phutil_tag(
      'div',
      array('class' => 'file-list'),
      $files_html);

  }

}
