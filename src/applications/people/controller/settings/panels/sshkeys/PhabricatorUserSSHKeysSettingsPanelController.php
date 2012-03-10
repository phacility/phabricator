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

final class PhabricatorUserSSHKeysSettingsPanelController
  extends PhabricatorUserSettingsPanelController {

  const PANEL_BASE_URI = '/settings/page/sshkeys/';

  public static function isEnabled() {
    return PhabricatorEnv::getEnvConfig('auth.sshkeys.enabled');
  }

  public function processRequest() {

    $request = $this->getRequest();
    $user = $request->getUser();

    $edit = $request->getStr('edit');
    $delete = $request->getStr('delete');
    if (!$edit && !$delete) {
      return $this->renderKeyListView();
    }

    $id = nonempty($edit, $delete);

    if ($id && is_numeric($id)) {
      // NOTE: Prevent editing/deleting of keys you don't own.
      $key = id(new PhabricatorUserSSHKey())->loadOneWhere(
        'userPHID = %s AND id = %d',
        $user->getPHID(),
        $id);
      if (!$key) {
        return new Aphront404Response();
      }
    } else {
      $key = new PhabricatorUserSSHKey();
      $key->setUserPHID($user->getPHID());
    }

    if ($delete) {
      return $this->processDelete($key);
    }

    $e_name = true;
    $e_key = true;
    $errors = array();
    $entire_key = $key->getEntireKey();
    if ($request->isFormPost()) {
      $key->setName($request->getStr('name'));
      $entire_key = $request->getStr('key');

      if (!strlen($entire_key)) {
        $errors[] = 'You must provide an SSH Public Key.';
        $e_key = 'Required';
      } else {
        $parts = str_replace("\n", '', trim($entire_key));
        $parts = preg_split('/\s+/', $parts);
        if (count($parts) == 2) {
          $parts[] = ''; // Add an empty comment part.
        } else if (count($parts) == 3) {
          // This is the expected case.
        } else {
          if (preg_match('/private\s*key/i', $entire_key)) {
            // Try to give the user a better error message if it looks like
            // they uploaded a private key.
            $e_key = 'Invalid';
            $errors[] = 'Provide your public key, not your private key!';
          } else {
            $e_key = 'Invalid';
            $errors[] = 'Provided public key is not properly formatted.';
          }
        }

        if (!$errors) {
          list($type, $body, $comment) = $parts;
          if (!preg_match('/^ssh-dsa|ssh-rsa$/', $type)) {
            $e_key = 'Invalid';
            $errors[] = 'Public key should be "ssh-dsa" or "ssh-rsa".';
          } else {
            $key->setKeyType($type);
            $key->setKeyBody($body);
            $key->setKeyHash(md5($body));
            $key->setKeyComment($comment);

            $e_key = null;
          }
        }
      }

      if (!strlen($key->getName())) {
        $errors[] = 'You must name this public key.';
        $e_name = 'Required';
      } else {
        $e_name = null;
      }

      if (!$errors) {
        try {
          $key->save();
          return id(new AphrontRedirectResponse())
            ->setURI(self::PANEL_BASE_URI);
        } catch (AphrontQueryDuplicateKeyException $ex) {
          $e_key = 'Duplicate';
          $errors[] = 'This public key is already associated with a user '.
                      'account.';
        }
      }
    }

    $error_view = null;
    if ($errors) {
      $error_view = new AphrontErrorView();
      $error_view->setTitle('Form Errors');
      $error_view->setErrors($errors);
    }

    $is_new = !$key->getID();

    if ($is_new) {
      $header = 'Add New SSH Public Key';
      $save = 'Add Key';
    } else {
      $header = 'Edit SSH Public Key';
      $save   = 'Save Changes';
    }

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->addHiddenInput('edit', $is_new ? 'true' : $key->getID())
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Name')
          ->setName('name')
          ->setValue($key->getName())
          ->setError($e_name))
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel('Public Key')
          ->setName('key')
          ->setValue($entire_key)
          ->setError($e_key))
      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton(self::PANEL_BASE_URI)
          ->setValue($save));

    $panel = new AphrontPanelView();
    $panel->setHeader($header);
    $panel->setWidth(AphrontPanelView::WIDTH_FORM);
    $panel->appendChild($form);

    return id(new AphrontNullView())
      ->appendChild(
        array(
          $error_view,
          $panel,
        ));
  }

  private function renderKeyListView() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $keys = id(new PhabricatorUserSSHKey())->loadAllWhere(
      'userPHID = %s',
      $user->getPHID());

    $rows = array();
    foreach ($keys as $key) {
      $rows[] = array(
        phutil_render_tag(
          'a',
          array(
            'href' => '/settings/page/sshkeys/?edit='.$key->getID(),
          ),
          phutil_escape_html($key->getName())),
        phutil_escape_html($key->getKeyComment()),
        phutil_escape_html($key->getKeyType()),
        phabricator_date($key->getDateCreated(), $user),
        phabricator_time($key->getDateCreated(), $user),
        javelin_render_tag(
          'a',
          array(
            'href' => '/settings/page/sshkeys/?delete='.$key->getID(),
            'class' => 'small grey button',
            'sigil' => 'workflow',
          ),
          'Delete'),
      );
    }

    $table = new AphrontTableView($rows);
    $table->setNoDataString("You haven't added any SSH Public Keys.");
    $table->setHeaders(
      array(
        'Name',
        'Comment',
        'Type',
        'Created',
        'Time',
        '',
      ));
    $table->setColumnClasses(
      array(
        'wide pri',
        '',
        '',
        '',
        'right',
        'action',
      ));

    $panel = new AphrontPanelView();
    $panel->addButton(
      phutil_render_tag(
        'a',
        array(
          'href' => '/settings/page/sshkeys/?edit=true',
          'class' => 'green button',
        ),
        'Add New Public Key'));
    $panel->setHeader('SSH Public Keys');
    $panel->appendChild($table);

    return $panel;
  }

  private function processDelete(PhabricatorUserSSHKey $key) {
    $request = $this->getRequest();
    $user = $request->getUser();

    $name = phutil_escape_html($key->getName());

    if ($request->isDialogFormPost()) {
      $key->delete();
      return id(new AphrontReloadResponse())
        ->setURI(self::PANEL_BASE_URI);
    }

    $dialog = id(new AphrontDialogView())
      ->setUser($user)
      ->addHiddenInput('delete', $key->getID())
      ->setTitle('Really delete SSH Public Key?')
      ->appendChild(
        '<p>The key "<strong>'.$name.'</strong>" will be permanently deleted, '.
        'and you will not longer be able to use the corresponding private key '.
        'to authenticate.</p>')
      ->addSubmitButton('Delete Public Key')
      ->addCancelButton(self::PANEL_BASE_URI);

    return id(new AphrontDialogResponse())
      ->setDialog($dialog);
  }

}
