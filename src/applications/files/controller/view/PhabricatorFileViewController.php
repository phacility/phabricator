<?php

/*
 * Copyright 2011 Facebook, Inc.
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

class PhabricatorFileViewController extends PhabricatorFileController {

  private $phid;
  private $view;

  public function willProcessRequest(array $data) {
    $this->phid = $data['phid'];
    $this->view = $data['view'];
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

    switch ($this->view) {
      case 'download':
      case 'view':
        $data = $file->loadFileData();
        $response = new AphrontFileResponse();
        $response->setContent($data);
        $response->setCacheDurationInSeconds(60 * 60 * 24 * 30);

        if ($this->view == 'view') {
          if (!$file->isViewableInBrowser()) {
            return new Aphront400Response();
          }
          $download = false;
        } else {
          $download = true;
        }

        if ($download) {
          $mime_type = $file->getMimeType();
        } else {
          $mime_type = $file->getViewableMimeType();
        }

        $response->setMimeType($mime_type);

        if ($download) {
          $response->setDownload($file->getName());
        }
        return $response;
      default:
        break;
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

    if ($file->isViewableInBrowser()) {
      $form->setAction('/file/view/'.$file->getPHID().'/');
      $button_name = 'View File';
    } else {
      $form->setAction('/file/download/'.$file->getPHID().'/');
      $button_name = 'Download File';
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
        id(new AphrontFormSubmitControl())
          ->setValue($button_name));

    $panel = new AphrontPanelView();
    $panel->setHeader('File Info - '.$file->getName());

    $panel->appendChild($form);
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);


    $transformations = id(new PhabricatorTransformedFile())->loadAllWhere(
      'originalPHID = %s',
      $file->getPHID());
    $rows = array();
    foreach ($transformations as $transformed) {
      $phid = $transformed->getTransformedPHID();
      $rows[] = array(
        phutil_escape_html($transformed->getTransform()),
        phutil_render_tag(
          'a',
          array(
            'href' => PhabricatorFileURI::getViewURIForPHID($phid),
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

    return $this->buildStandardPageResponse(
      array($panel, $xform_panel),
      array(
        'title' => 'File Info - '.$file->getName(),
      ));
  }
}
