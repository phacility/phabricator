<?php

/**
 * @group phame
 */
final class PhamePostEditController
  extends PhameController {

  private $id;

  public function willProcessRequest(array $data) {
    $this->id = idx($data, 'id');
  }

  public function processRequest() {
    $request       = $this->getRequest();
    $user          = $request->getUser();

    if ($this->id) {
      $post = id(new PhamePostQuery())
        ->setViewer($user)
        ->withIDs(array($this->id))
        ->requireCapabilities(
          array(
            PhabricatorPolicyCapability::CAN_EDIT,
          ))
        ->executeOne();
      if (!$post) {
        return new Aphront404Response();
      }

      $cancel_uri = $this->getApplicationURI('/post/view/'.$this->id.'/');
      $submit_button = pht('Save Changes');
      $page_title = pht('Edit Post');
    } else {
      $blog = id(new PhameBlogQuery())
        ->setViewer($user)
        ->withIDs(array($request->getInt('blog')))
        ->executeOne();
      if (!$blog) {
        return new Aphront404Response();
      }

      $post = id(new PhamePost())
        ->setBloggerPHID($user->getPHID())
        ->setBlogPHID($blog->getPHID())
        ->setBlog($blog)
        ->setDatePublished(0)
        ->setVisibility(PhamePost::VISIBILITY_DRAFT);
      $cancel_uri = $this->getApplicationURI('/blog/view/'.$blog->getID().'/');

      $submit_button = pht('Save Draft');
      $page_title    = pht('Create Post');
    }

    $e_phame_title = null;
    $e_title       = true;
    $errors        = array();

    if ($request->isFormPost()) {
      $comments    = $request->getStr('comments_widget');
      $data        = array('comments_widget' => $comments);
      $phame_title = $request->getStr('phame_title');
      $phame_title = PhabricatorSlug::normalize($phame_title);
      $title       = $request->getStr('title');
      $post->setTitle($title);
      $post->setPhameTitle($phame_title);
      $post->setBody($request->getStr('body'));
      $post->setConfigData($data);

      if ($phame_title == '/') {
        $errors[]      = 'Phame title must be nonempty.';
        $e_phame_title = 'Required';
      }

      if (!strlen($title)) {
        $errors[] = 'Title must be nonempty.';
        $e_title  = 'Required';
      } else {
        $e_title = null;
      }

      if (!$errors) {
        try {
          $post->save();

          $uri = $this->getApplicationURI('/post/view/'.$post->getID().'/');
          return id(new AphrontRedirectResponse())->setURI($uri);
        } catch (AphrontQueryDuplicateKeyException $e) {
          $e_phame_title = 'Not Unique';
          $errors[]      = 'Another post already uses this slug. '.
                           'Each post must have a unique slug.';
        }
      }
    }

    $handle = PhabricatorObjectHandleData::loadOneHandle(
      $post->getBlogPHID(),
      $user);

    $form = id(new AphrontFormView())
      ->setUser($user)
      ->setFlexible(true)
      ->addHiddenInput('blog', $request->getInt('blog'))
      ->appendChild(
        id(new AphrontFormMarkupControl())
          ->setLabel('Blog')
          ->setValue($handle->renderLink()))
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setLabel('Title')
        ->setName('title')
        ->setValue($post->getTitle())
        ->setID('post-title')
        ->setError($e_title))
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setLabel('Phame Title')
        ->setName('phame_title')
        ->setValue(rtrim($post->getPhameTitle(), '/'))
        ->setID('post-phame-title')
        ->setCaption('Up to 64 alphanumeric characters '.
                     'with underscores for spaces. '.
                     'Formatting is enforced.')
        ->setError($e_phame_title))
      ->appendChild(
        id(new PhabricatorRemarkupControl())
        ->setLabel('Body')
        ->setName('body')
        ->setValue($post->getBody())
        ->setHeight(AphrontFormTextAreaControl::HEIGHT_VERY_TALL)
        ->setID('post-body')
        ->setUser($user)
        ->setDisableMacros(true))
      ->appendChild(
        id(new AphrontFormSelectControl())
        ->setLabel('Comments Widget')
        ->setName('comments_widget')
        ->setvalue($post->getCommentsWidget())
        ->setOptions($post->getCommentsWidgetOptionsForSelect()))
      ->appendChild(
        id(new AphrontFormSubmitControl())
        ->addCancelButton($cancel_uri)
        ->setValue($submit_button));

    $preview_panel = hsprintf(
      '<div class="aphront-panel-preview">
         <div class="phame-post-preview-header">
           Post Preview
         </div>
         <div id="post-preview">
           <div class="aphront-panel-preview-loading-text">
             Loading preview...
           </div>
         </div>
       </div>');

    require_celerity_resource('phame-css');
    Javelin::initBehavior(
      'phame-post-preview',
      array(
        'preview'     => 'post-preview',
        'body'        => 'post-body',
        'title'       => 'post-title',
        'phame_title' => 'post-phame-title',
        'uri'         => '/phame/post/preview/',
      ));

    $header = id(new PhabricatorHeaderView())->setHeader($page_title);

    if ($errors) {
      $error_view = id(new AphrontErrorView())
        ->setTitle('Errors saving post.')
        ->setErrors($errors);
    } else {
      $error_view = null;
    }

    $nav = $this->renderSideNavFilterView(null);
    $nav->appendChild(
      array(
        $header,
        $error_view,
        $form,
        $preview_panel,
      ));

    return $this->buildApplicationPage(
      $nav,
      array(
        'title'   => $page_title,
        'device'  => true,
      ));
  }

}
