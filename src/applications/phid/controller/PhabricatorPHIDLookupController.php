<?php

final class PhabricatorPHIDLookupController
  extends PhabricatorPHIDController {

  public function processRequest() {

    $request = $this->getRequest();
    $phids = $request->getStrList('phids');
    if ($phids) {
      $handles = $this->loadViewerHandles($phids);

      $rows = array();
      foreach ($handles as $handle) {
        if ($handle->getURI()) {
          $link = phutil_render_tag(
            'a',
            array(
              'href' => $handle->getURI(),
            ),
            phutil_escape_html($handle->getURI()));
        } else {
          $link = null;
        }

        $rows[] = array(
          phutil_escape_html($handle->getPHID()),
          phutil_escape_html($handle->getType()),
          phutil_escape_html($handle->getName()),
          $link,
        );
      }

      $table = new AphrontTableView($rows);
      $table->setHeaders(
        array(
          'PHID',
          'Type',
          'Name',
          'URI',
        ));
      $table->setColumnClasses(
        array(
          null,
          null,
          null,
          'wide',
        ));

      $panel = new AphrontPanelView();
      $panel->setHeader('PHID Handles');
      $panel->appendChild($table);

      return $this->buildStandardPageResponse(
        $panel,
        array(
          'title' => 'PHID Lookup Results',
        ));
    }

    $lookup_form = new AphrontFormView();
    $lookup_form->setUser($request->getUser());
    $lookup_form
      ->setAction('/phid/')
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setName('phids')
          ->setCaption('Enter PHIDs separated by spaces or commas.'))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->setValue('Lookup PHIDs'));

    $lookup_panel = new AphrontPanelView();
    $lookup_panel->setHeader('Lookup PHIDs');
    $lookup_panel->appendChild($lookup_form);
    $lookup_panel->setWidth(AphrontPanelView::WIDTH_WIDE);

    return $this->buildStandardPageResponse(
      array(
        $lookup_panel,
      ),
      array(
        'title' => 'PHID Lookup',
      ));
  }

}
