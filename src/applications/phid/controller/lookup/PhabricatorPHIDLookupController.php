<?php

/*
 * Copyright 2012 Facebook, Inc.
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

final class PhabricatorPHIDLookupController
  extends PhabricatorPHIDController {

  public function processRequest() {

    $request = $this->getRequest();
    if ($request->isFormPost()) {
      $phids = $request->getStrList('phids');
      if ($phids) {
        $handles = id(new PhabricatorObjectHandleData($phids))
          ->loadHandles();

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
            phutil_escape_html($handle->getEmail()),
            $link,
          );
        }

        $table = new AphrontTableView($rows);
        $table->setHeaders(
          array(
            'PHID',
            'Type',
            'Name',
            'Email',
            'URI',
          ));
        $table->setColumnClasses(
          array(
            null,
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
    }

    $lookup_form = new AphrontFormView();
    $lookup_form->setUser($request->getUser());
    $lookup_form
      ->setAction('/phid/')
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setName('phids')
//          ->setHeight(AphrontFormTextAreaControl::HEIGHT_VERY_SHORT) TODO
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
