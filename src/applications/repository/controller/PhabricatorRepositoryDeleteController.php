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

final class PhabricatorRepositoryDeleteController
  extends PhabricatorRepositoryController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = $data['id'];
  }

  public function processRequest() {

    $repository = id(new PhabricatorRepository())->load($this->id);
    if (!$repository) {
      return new Aphront404Response();
    }

    $request = $this->getRequest();

    if ($request->isDialogFormPost()) {
      return id(new AphrontRedirectResponse())->setURI('/repository/');
    }

    $dialog = new AphrontDialogView();
    $text_1 = pht('If you really want to delete the repository, you must run:');
    $command = 'bin/repository delete '.
               phutil_escape_html($repository->getCallsign());
    $text_2 = pht('Repositories touch many objects and as such deletes are '.
                  'prohibitively expensive to run from the web UI.');
    $body = phutil_render_tag(
      'div',
      array(
        'class' => 'phabricator-remarkup',
      ),
      '<p>'.$text_1.'</p><p><tt>'.$command.'</tt></p><p>'.$text_2.'</p>');
    $dialog
      ->setUser($request->getUser())
      ->setTitle(pht('Really want to delete the repository?'))
      ->appendChild($body)
      ->setSubmitURI('/repository/delete/'.$this->id.'/')
      ->addSubmitButton(pht('Okay'));

    return id(new AphrontDialogResponse())->setDialog($dialog);
  }
}
