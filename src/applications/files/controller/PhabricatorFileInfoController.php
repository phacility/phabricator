<?php

final class PhabricatorFileInfoController extends PhabricatorFileController {

  private $phid;

  public function willProcessRequest(array $data) {
    $this->phid = $data['phid'];
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $file = id(new PhabricatorFile())->loadOneWhere(
      'phid = %s',
      $this->phid);
    if (!$file) {
      return new Aphront404Response();
    }

    $author_child = null;
    if ($file->getAuthorPHID()) {
      $author = id(new PhabricatorUser())->loadOneWhere(
        'phid = %s',
        $file->getAuthorPHID());

      if ($author) {
        $author_child = id(new AphrontFormStaticControl())
          ->setLabel('Author')
          ->setName('author')
          ->setValue($author->getUserName());
      }
    }

    $form = new AphrontFormView();

    $submit = new AphrontFormSubmitControl();

    $form->setAction($file->getViewURI());
    if ($file->isViewableInBrowser()) {
      $submit->setValue('View File');
    } else {
      $submit->setValue('Download File');
    }

    if (($user->getPHID() == $file->getAuthorPHID()) ||
        ($user->getIsAdmin())) {
      $submit->addCancelButton(
        '/file/delete/'.$file->getID().'/',
        'Delete File');
    }

    $file_id = 'F'.$file->getID();

    $form->setUser($user);
    $form
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('Name')
          ->setName('name')
          ->setValue($file->getName()))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('ID')
          ->setName('id')
          ->setValue($file_id)
          ->setCaption(
            'Download this file with: <tt>arc download '.
            phutil_escape_html($file_id).'</tt>'))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('PHID')
          ->setName('phid')
          ->setValue($file->getPHID()))
      ->appendChild($author_child)
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('Created')
          ->setName('created')
          ->setValue(phabricator_datetime($file->getDateCreated(), $user)))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('Mime Type')
          ->setName('mime')
          ->setValue($file->getMimeType()))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('Size')
          ->setName('size')
          ->setValue($file->getByteSize().' bytes'))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('Engine')
          ->setName('storageEngine')
          ->setValue($file->getStorageEngine()))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('Format')
          ->setName('storageFormat')
          ->setValue($file->getStorageFormat()))
      ->appendChild(
        id(new AphrontFormStaticControl())
          ->setLabel('Handle')
          ->setName('storageHandle')
          ->setValue($file->getStorageHandle()))
      ->appendChild(
        id($submit));

    $panel = new AphrontPanelView();
    $panel->setHeader('File Info - '.$file->getName());

    $panel->appendChild($form);
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);

    $xform_panel = null;

    $transformations = id(new PhabricatorTransformedFile())->loadAllWhere(
      'originalPHID = %s',
      $file->getPHID());
    if ($transformations) {
      $transformed_phids = mpull($transformations, 'getTransformedPHID');
      $transformed_files = id(new PhabricatorFile())->loadAllWhere(
        'phid in (%Ls)',
        $transformed_phids);
      $transformed_map = mpull($transformed_files, null, 'getPHID');

      $rows = array();
      foreach ($transformations as $transformed) {
        $phid = $transformed->getTransformedPHID();
        $rows[] = array(
          phutil_escape_html($transformed->getTransform()),
          phutil_render_tag(
            'a',
            array(
              'href' => $transformed_map[$phid]->getBestURI(),
            ),
            $phid));
      }

      $table = new AphrontTableView($rows);
      $table->setHeaders(
        array(
          'Transform',
          'File',
        ));

      $xform_panel = new AphrontPanelView();
      $xform_panel->appendChild($table);
      $xform_panel->setWidth(AphrontPanelView::WIDTH_FORM);
      $xform_panel->setHeader('Transformations');
    }

    return $this->buildStandardPageResponse(
      array($panel, $xform_panel),
      array(
        'title' => 'File Info - '.$file->getName(),
      ));
  }
}
