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

    $pager = new AphrontCursorPagerView();
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
        $paste_list = $query->executeWithCursorPager($pager);
        break;
      case 'all':
        $paste_list = $query->executeWithCursorPager($pager);
        break;
    }

    $side_nav = $this->buildSideNavView();
    $side_nav->selectFilter($this->getFilter());

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
        $header = "Recent Pastes";
        break;
      case 'my':
        $header = 'Your Pastes';
        break;
      case 'all':
        $header = 'All Pastes';
        break;
    }

    $this->loadHandles(mpull($paste_list, 'getAuthorPHID'));

    $list = $this->buildPasteList($paste_list);
    $list->setHeader($header);
    $list->setPager($pager);

    $side_nav->appendChild($list);

    return $this->buildApplicationPage(
      $side_nav,
      array(
        'title' => 'Paste',
        'device' => true,
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
    $form->setFlexible(true);

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

    return array(
      id(new PhabricatorHeaderView())->setHeader('Create Paste'),
      $form,
    );
  }

  private function buildPasteList(array $pastes) {
    assert_instances_of($pastes, 'PhabricatorPaste');

    $user = $this->getRequest()->getUser();

    $list = new PhabricatorObjectItemListView();
    foreach ($pastes as $paste) {
      $created = phabricator_datetime($paste->getDateCreated(), $user);

      $item = id(new PhabricatorObjectItemView())
        ->setHeader($paste->getFullName())
        ->setHref('/P'.$paste->getID())
        ->addDetail(
          pht('Author'),
          $this->getHandle($paste->getAuthorPHID())->renderLink())
        ->addAttribute(pht('Created %s', $created));

      $list->addItem($item);
    }

    return $list;
  }

}
