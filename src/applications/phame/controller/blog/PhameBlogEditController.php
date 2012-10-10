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

/**
 * @group phame
 */
final class PhameBlogEditController
  extends PhameController {

  private $phid;
  private $isBlogEdit;

  private function setBlogPHID($phid) {
    $this->phid = $phid;
    return $this;
  }
  private function getBlogPHID() {
    return $this->phid;
  }
  private function setIsBlogEdit($is_blog_edit) {
    $this->isBlogEdit = $is_blog_edit;
    return $this;
  }
  private function isBlogEdit() {
    return $this->isBlogEdit;
  }

  protected function getSideNavFilter() {
    if ($this->isBlogEdit()) {
      $filter = 'blog/edit/'.$this->getBlogPHID();
    } else {
      $filter = 'blog/new';
    }
    return $filter;
  }

  protected function getSideNavBlogFilters() {
    $filters = parent::getSideNavBlogFilters();

    if ($this->isBlogEdit()) {
      $filter =
        array('key'  => 'blog/edit/'.$this->getBlogPHID(),
              'name' => 'Edit Blog');
      $filters[] = $filter;
    } else {
      $filter =
        array('key'  => 'blog/new',
              'name' => 'New Blog');
      array_unshift($filters, $filter);
    }

    return $filters;
  }

  public function willProcessRequest(array $data) {
    $phid = idx($data, 'phid');
    $this->setBlogPHID($phid);
    $this->setIsBlogEdit((bool)$phid);
  }

  public function processRequest() {
    $request         = $this->getRequest();
    $user            = $request->getUser();
    $e_name          = null;
    $e_bloggers      = null;
    $e_custom_domain = null;
    $errors          = array();

    if ($this->isBlogEdit()) {
      $blogs = id(new PhameBlogQuery())
        ->withPHIDs(array($this->getBlogPHID()))
        ->execute();
      $blog = reset($blogs);
      if (empty($blog)) {
        return new Aphront404Response();
      }

      $bloggers = $blog->loadBloggers()->getBloggers();

      // TODO -- make this check use a policy
      if (!isset($bloggers[$user->getPHID()]) &&
          !$user->isAdmin()) {
        return new Aphront403Response();
      }
      $blogger_tokens = mpull($bloggers, 'getFullName', 'getPHID');
      $submit_button  = 'Save Changes';
      $delete_button  = javelin_render_tag(
        'a',
        array(
          'href'  => $blog->getDeleteURI(),
          'class' => 'grey button',
          'sigil' => 'workflow',
        ),
        'Delete Blog');
      $page_title     = 'Edit Blog';
    } else {
      $blog = id(new PhameBlog())
        ->setCreatorPHID($user->getPHID());
      $blogger_tokens = array($user->getPHID() => $user->getFullName());
      $submit_button  = 'Create Blog';
      $delete_button  = null;
      $page_title     = 'Create Blog';
    }

    if ($request->isFormPost()) {
      $saved         = true;
      $name          = $request->getStr('name');
      $description   = $request->getStr('description');
      $blogger_arr   = $request->getArr('bloggers');
      $custom_domain = $request->getStr('custom_domain');

      if (empty($blogger_arr)) {
        $error = 'Bloggers must be nonempty.';
        if ($this->isBlogEdit()) {
          $error .= ' To delete the blog, use the delete button.';
        } else {
          $error .= ' A blog cannot exist without bloggers.';
        }
        $e_bloggers = 'Required';
        $errors[] = $error;
      }
      $new_bloggers = array_values($blogger_arr);
      if ($this->isBlogEdit()) {
        $old_bloggers = array_keys($blogger_tokens);
      } else {
        $old_bloggers = array();
      }

      if (empty($name)) {
        $errors[] = 'Name must be nonempty.';
        $e_name   = 'Required';
      }
      $blog->setName($name);
      $blog->setDescription($description);
      if (!empty($custom_domain)) {
        $error = $blog->validateCustomDomain($custom_domain);
        if ($error) {
          $errors[] = $error;
          $e_custom_domain = 'Invalid';
        }
        $blog->setDomain($custom_domain);
      }

      if (empty($errors)) {
        $blog->save();

        $add_phids = $new_bloggers;
        $rem_phids = array_diff($old_bloggers, $new_bloggers);
        $editor    = new PhabricatorEdgeEditor();
        $edge_type = PhabricatorEdgeConfig::TYPE_BLOG_HAS_BLOGGER;
        $editor->setActor($user);
        foreach ($add_phids as $phid) {
          $editor->addEdge($blog->getPHID(), $edge_type, $phid);
        }
        foreach ($rem_phids as $phid) {
          $editor->removeEdge($blog->getPHID(), $edge_type, $phid);
        }
        $editor->save();

      } else {
        $saved = false;
      }

      if ($saved) {
        $uri = new PhutilURI($blog->getViewURI());
        if ($this->isBlogEdit()) {
          $uri->setQueryParam('edit', true);
        } else {
          $uri->setQueryParam('new', true);
        }
        return id(new AphrontRedirectResponse())
          ->setURI($uri);
      }
    }

    $panel = new AphrontPanelView();
    $panel->setHeader($page_title);
    $panel->setWidth(AphrontPanelView::WIDTH_FULL);
    if ($delete_button) {
      $panel->addButton($delete_button);
    }

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setLabel('Name')
        ->setName('name')
        ->setValue($blog->getName())
        ->setID('blog-name')
        ->setError($e_name)
      )
      ->appendChild(
        id(new PhabricatorRemarkupControl())
        ->setLabel('Description')
        ->setName('description')
        ->setValue($blog->getDescription())
        ->setHeight(AphrontFormTextAreaControl::HEIGHT_VERY_TALL)
        ->setID('blog-description')
      )
      ->appendChild(
        id(new AphrontFormTokenizerControl())
        ->setLabel('Bloggers')
        ->setName('bloggers')
        ->setValue($blogger_tokens)
        ->setUser($user)
        ->setDatasource('/typeahead/common/users/')
        ->setError($e_bloggers)
      )
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setLabel('Custom Domain')
        ->setName('custom_domain')
        ->setValue($blog->getDomain())
        ->setCaption('Must include at least one dot (.), e.g. '.
        'blog.example.com')
        ->setError($e_custom_domain)
      )
      ->appendChild(
        id(new AphrontFormSubmitControl())
        ->addCancelButton('/phame/blog/')
        ->setValue($submit_button)
      );

    $panel->appendChild($form);

    if ($errors) {
      $error_view = id(new AphrontErrorView())
        ->setTitle('Errors saving blog.')
        ->setErrors($errors);
    } else {
      $error_view = null;
    }

    $this->setShowSideNav(true);
    return $this->buildStandardPageResponse(
      array(
        $error_view,
        $panel,
      ),
      array(
        'title'   => $page_title,
      ));
  }
}
