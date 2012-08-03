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

final class PhabricatorPasteListController extends PhabricatorPasteController {
  private $filter;

  private $errorView;
  private $errorText;
  private $paste;
  private $pasteText;

  private function setFilter($filter) {
    $this->filter = $filter;
    return $this;
  }
  private function getFilter() {
    return $this->filter;
  }

  private function setErrorView($error_view) {
    $this->errorView = $error_view;
    return $this;
  }

  private function getErrorView() {
    return $this->errorView;
  }

  private function setErrorText($error_text) {
    $this->errorText = $error_text;
    return $this;
  }
  private function getErrorText() {
    return $this->errorText;
  }

  private function setPaste(PhabricatorPaste $paste) {
    $this->paste = $paste;
    return $this;
  }
  private function getPaste() {
    return $this->paste;
  }

  private function setPasteText($paste_text) {
    $this->pasteText = $paste_text;
    return $this;
  }
  private function getPasteText() {
    return $this->pasteText;
  }

  public function willProcessRequest(array $data) {
    $this->setFilter(idx($data, 'filter', 'create'));
  }

  public function processRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $pager = new AphrontIDPagerView();
    $pager->readFromRequest($request);

    $query = new PhabricatorPasteQuery();
    $query->setViewer($user);

    switch ($this->getFilter()) {
      case 'create':
      default:
        // if we successfully create a paste, we redirect to view it
        $created_paste_redirect = $this->processCreateRequest();
        if ($created_paste_redirect) {
          return $created_paste_redirect;
        }

        $query->setLimit(10);
        $paste_list = $query->execute();

        $pager = null;
        break;
      case 'my':
        $query->withAuthorPHIDs(array($user->getPHID()));
        $paste_list = $query->executeWithPager($pager);
        break;
      case 'all':
        $paste_list = $query->executeWithPager($pager);
        break;
    }

    $filters = array(
      'create' => array(
        'name' => 'Create Paste',
      ),
      'my' => array(
        'name' => 'My Pastes',
      ),
      'all' => array(
        'name' => 'All Pastes',
      ),
    );

    $side_nav = new AphrontSideNavView();
    foreach ($filters as $filter_key => $filter) {
      $selected = $filter_key == $this->getFilter();
      $side_nav->addNavItem(
        phutil_render_tag(
          'a',
          array(
            'href' => '/paste/filter/'.$filter_key.'/',
            'class' => $selected ? 'aphront-side-nav-selected': null,
          ),
          $filter['name'])
      );
    }

    if ($this->getErrorView()) {
      $side_nav->appendChild($this->getErrorView());
    }

    switch ($this->getFilter()) {
      case 'create':
      default:
        $side_nav->appendChild($this->renderCreatePaste());
        $see_all = phutil_render_tag(
          'a',
          array(
            'href' => '/paste/filter/all',
          ),
          'See all Pastes');
        $header = "Recent Pastes &middot; {$see_all}";
        break;
      case 'my':
        $header = 'Your Pastes';
        break;
      case 'all':
        $header = 'All Pastes';
        break;
    }

    $side_nav->appendChild(
      $this->renderPasteList($paste_list, $header, $pager));


    return $this->buildStandardPageResponse(
      $side_nav,
      array(
        'title' => 'Paste',
      )
    );
  }

  private function processCreateRequest() {
    $request = $this->getRequest();
    $user = $request->getUser();
    $fork = $request->getInt('fork');

    $error_view = null;
    $e_text = true;
    $new_paste = new PhabricatorPaste();
    $new_paste_text = null;
    $new_paste_language = PhabricatorEnv::getEnvConfig(
      'pygments.dropdown-default');

    if ($request->isFormPost()) {
      $errors = array();

      $text = $request->getStr('text');
      if (!strlen($text)) {
        $e_text = 'Required';
        $errors[] = 'The paste may not be blank.';
      } else {
        $e_text = null;
      }

      $parent_phid = $request->getStr('parent');
      if ($parent_phid) {
        $parent = id(new PhabricatorPaste())->loadOneWhere('phid = %s',
                                                           $parent_phid);
        if ($parent) {
          $new_paste->setParentPHID($parent->getPHID());
        }
      }

      $title = $request->getStr('title');
      $new_paste->setTitle($title);

      $new_paste_language = $request->getStr('language');

      if (!$errors) {
        if ($new_paste_language == 'infer') {
          // If it's infer, store an empty string. Otherwise, store the
          // language name. We do this so we can refer to 'infer' elsewhere
          // in the code (such as default value) while retaining backwards
          // compatibility with old posts with no language stored.
          $new_paste_language = '';
        }
        $new_paste->setLanguage($new_paste_language);

        $new_paste_file = PhabricatorFile::newFromFileData(
          $text,
          array(
            'name' => $title,
            'mime-type' => 'text/plain; charset=utf-8',
            'authorPHID' => $user->getPHID(),
          ));
        $new_paste->setFilePHID($new_paste_file->getPHID());
        $new_paste->setAuthorPHID($user->getPHID());
        $new_paste->save();

        return id(new AphrontRedirectResponse())
          ->setURI('/P'.$new_paste->getID());
      } else {
        $error_view = new AphrontErrorView();
        $error_view->setErrors($errors);
        $error_view->setTitle('A problem has occurred!');
      }
    } else if ($fork) {
      $fork_paste = id(new PhabricatorPaste())->load($fork);
      if ($fork_paste) {
        $new_paste->setTitle('Fork of '.$fork_paste->getID().': '.
                             $fork_paste->getTitle());
        $fork_file = id(new PhabricatorFile())->loadOneWhere(
          'phid = %s',
          $fork_paste->getFilePHID());
        $new_paste_text = $fork_file->loadFileData();
        $new_paste_language = nonempty($fork_paste->getLanguage(), 'infer');
        $new_paste->setParentPHID($fork_paste->getPHID());
      }
    }
    $this->setErrorView($error_view);
    $this->setErrorText($e_text);
    $this->setPasteText($new_paste_text);
    $new_paste->setLanguage($new_paste_language);
    $this->setPaste($new_paste);
  }

  private function renderCreatePaste() {
    $request = $this->getRequest();
    $user = $request->getUser();

    $new_paste = $this->getPaste();

    $form = new AphrontFormView();

    $available_languages = PhabricatorEnv::getEnvConfig(
      'pygments.dropdown-choices');
    asort($available_languages);
    $language_select = id(new AphrontFormSelectControl())
      ->setLabel('Language')
      ->setName('language')
      ->setValue($new_paste->getLanguage())
      ->setOptions($available_languages);

    $form
      ->setUser($user)
      ->setAction($request->getRequestURI()->getPath())
      ->addHiddenInput('parent', $new_paste->getParentPHID())
      ->appendChild(
        id(new AphrontFormTextControl())
          ->setLabel('Title')
          ->setValue($new_paste->getTitle())
          ->setName('title'))
      ->appendChild($language_select)
      ->appendChild(
        id(new AphrontFormTextAreaControl())
          ->setLabel('Text')
          ->setError($this->getErrorText())
          ->setValue($this->getPasteText())
          ->setHeight(AphrontFormTextAreaControl::HEIGHT_VERY_TALL)
          ->setName('text'))

    /* TODO: Doesn't have any useful options yet.
      ->appendChild(
        id(new AphrontFormPolicyControl())
          ->setLabel('Visible To')
          ->setUser($user)
          ->setValue(
            $new_paste->getPolicy(PhabricatorPolicyCapability::CAN_VIEW))
          ->setName('policy'))
    */

      ->appendChild(
        id(new AphrontFormSubmitControl())
          ->addCancelButton('/paste/')
          ->setValue('Create Paste'));

    $create_panel = new AphrontPanelView();
    $create_panel->setWidth(AphrontPanelView::WIDTH_FULL);
    $create_panel->setHeader('Create a Paste');
    $create_panel->appendChild($form);

    return $create_panel;
  }

  private function renderPasteList(array $pastes, $header, $pager) {
    assert_instances_of($pastes, 'PhabricatorPaste');

    $phids = mpull($pastes, 'getAuthorPHID');
    $handles = array();
    if ($phids) {
      $handles = id(new PhabricatorObjectHandleData($phids))->loadHandles();
    }

    $phids = mpull($pastes, 'getFilePHID');
    $file_uris = array();
    if ($phids) {
      $files = id(new PhabricatorFile())->loadAllWhere(
        'phid in (%Ls)',
        $phids);
      if ($files) {
        $file_uris = mpull($files, 'getBestURI', 'getPHID');
      }
    }

    $paste_list_rows = array();
    foreach ($pastes as $paste) {

      $handle = $handles[$paste->getAuthorPHID()];
      $file_uri = $file_uris[$paste->getFilePHID()];

      $paste_list_rows[] = array(
        phutil_escape_html('P'.$paste->getID()),

        // TODO: Make this filter by user instead of going to their profile.
        phutil_render_tag(
          'a',
          array(
            'href' => '/p/'.$handle->getName().'/',
          ),
          phutil_escape_html($handle->getName())),

        phutil_escape_html($paste->getLanguage()),

        phutil_render_tag(
          'a',
          array(
            'href' => '/P'.$paste->getID(),
          ),
          phutil_escape_html(
            nonempty(
              $paste->getTitle(),
              'Untitled Masterwork P'.$paste->getID()))),

        phutil_render_tag(
          'a',
          array(
            'href' => $file_uri,
          ),
          phutil_escape_html($paste->getFilePHID())),

        phabricator_datetime(
          $paste->getDateCreated(),
          $this->getRequest()->getUser()),
      );
    }


    $table = new AphrontTableView($paste_list_rows);
    $table->setHeaders(
      array(
        'Paste ID',
        'Author',
        'Language',
        'Title',
        'File',
        'Created',
      ));

    $table->setColumnClasses(
      array(
        null,
        null,
        null,
        'wide pri',
        null,
        'right',
      ));

    $panel = new AphrontPanelView();
    $panel->setWidth(AphrontPanelView::WIDTH_FULL);
    $panel->setHeader($header);
    $panel->appendChild($table);
    if ($pager) {
      $panel->appendChild($pager);
    }

    return $panel;
  }
}
