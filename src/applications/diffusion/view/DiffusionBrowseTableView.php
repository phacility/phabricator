<?php

final class DiffusionBrowseTableView extends DiffusionView {

  private $paths;
  private $handles = array();
  private $user;

  public function setPaths(array $paths) {
    assert_instances_of($paths, 'DiffusionRepositoryPath');
    $this->paths = $paths;
    return $this;
  }

  public function setHandles(array $handles) {
    assert_instances_of($handles, 'PhabricatorObjectHandle');
    $this->handles = $handles;
    return $this;
  }

  public function setUser(PhabricatorUser $user) {
    $this->user = $user;
    return $this;
  }

  public static function renderLastModifiedColumns(
    PhabricatorRepository $repository,
    array $handles,
    PhabricatorRepositoryCommit $commit = null,
    PhabricatorRepositoryCommitData $data = null) {
    assert_instances_of($handles, 'PhabricatorObjectHandle');

    if ($commit) {
      $epoch = $commit->getEpoch();
      $modified = DiffusionView::linkCommit(
        $repository,
        $commit->getCommitIdentifier());
      $date = date('M j, Y', $epoch);
      $time = date('g:i A', $epoch);
    } else {
      $modified = '';
      $date = '';
      $time = '';
    }

    if ($data) {
      $author_phid = $data->getCommitDetail('authorPHID');
      if ($author_phid && isset($handles[$author_phid])) {
        $author = $handles[$author_phid]->renderLink();
      } else {
        $author = self::renderName($data->getAuthorName());
      }

      $committer = $data->getCommitDetail('committer');
      if ($committer) {
        $committer_phid = $data->getCommitDetail('committerPHID');
        if ($committer_phid && isset($handles[$committer_phid])) {
          $committer = $handles[$committer_phid]->renderLink();
        } else {
          $committer = self::renderName($committer);
        }
        if ($author != $committer) {
          $author .= '/'.$committer;
        }
      }

      $details = AphrontTableView::renderSingleDisplayLine(
        phutil_escape_html($data->getSummary()));
    } else {
      $author = '';
      $details = '';
    }

    return array(
      'commit'    => $modified,
      'date'      => $date,
      'time'      => $time,
      'author'    => $author,
      'details'   => $details,
    );
  }

  public function render() {
    $request = $this->getDiffusionRequest();
    $repository = $request->getRepository();

    $base_path = trim($request->getPath(), '/');
    if ($base_path) {
      $base_path = $base_path.'/';
    }

    $need_pull = array();
    $rows = array();
    $show_edit = false;
    foreach ($this->paths as $path) {

      $dir_slash = null;
      $file_type = $path->getFileType();
      if ($file_type == DifferentialChangeType::FILE_DIRECTORY) {
        $browse_text = $path->getPath().'/';
        $dir_slash = '/';

        $browse_link = '<strong>'.$this->linkBrowse(
          $base_path.$path->getPath().$dir_slash,
          array(
            'html' => $this->renderPathIcon(
              'dir',
              $browse_text),
          )).'</strong>';
      } else if ($file_type == DifferentialChangeType::FILE_SUBMODULE) {
        $browse_text = $path->getPath().'/';
        $browse_link =
          '<strong>'.
            $this->linkExternal(
              $path->getHash(),
              $path->getExternalURI(),
              $this->renderPathIcon(
                'ext',
                $browse_text)).
          '</strong>';
      } else {
        if ($file_type == DifferentialChangeType::FILE_SYMLINK) {
          $type = 'link';
        } else {
          $type = 'file';
        }
        $browse_text = $path->getPath();
        $browse_link = $this->linkBrowse(
          $base_path.$path->getPath(),
          array(
            'html' => $this->renderPathIcon($type, $browse_text),
          ));
      }

      $commit = $path->getLastModifiedCommit();
      if ($commit) {
        $dict = self::renderLastModifiedColumns(
          $repository,
          $this->handles,
          $commit,
          $path->getLastCommitData());
      } else {
        $dict = array(
          'commit'    => celerity_generate_unique_node_id(),
          'date'      => celerity_generate_unique_node_id(),
          'time'      => celerity_generate_unique_node_id(),
          'author'    => celerity_generate_unique_node_id(),
          'details'   => celerity_generate_unique_node_id(),
        );

        $uri = (string)$request->generateURI(
          array(
            'action' => 'lastmodified',
            'path'   => $base_path.$path->getPath(),
          ));

        $need_pull[$uri] = $dict;
        foreach ($dict as $k => $uniq) {
          $dict[$k] = '<span id="'.$uniq.'"></span>';
        }
      }

      $editor_button = '';
      if ($this->user) {
        $editor_link = $this->user->loadEditorLink(
          $base_path.$path->getPath(),
          1,
          $request->getRepository()->getCallsign());
        if ($editor_link) {
          $show_edit = true;
          $editor_button = phutil_render_tag(
            'a',
            array(
              'href' => $editor_link,
            ),
            'Edit');
        }
      }

      $rows[] = array(
        $this->linkHistory($base_path.$path->getPath().$dir_slash),
        $editor_button,
        $browse_link,
        $dict['commit'],
        $dict['date'],
        $dict['time'],
        $dict['author'],
        $dict['details'],
      );
    }

    if ($need_pull) {
      Javelin::initBehavior('diffusion-pull-lastmodified', $need_pull);
    }

    $view = new AphrontTableView($rows);
    $view->setHeaders(
      array(
        'History',
        'Edit',
        'Path',
        'Modified',
        'Date',
        'Time',
        'Author/Committer',
        'Details',
      ));
    $view->setColumnClasses(
      array(
        '',
        '',
        '',
        '',
        '',
        'right',
        '',
        'wide',
      ));
    $view->setColumnVisibility(
      array(
        true,
        $show_edit,
        true,
        true,
        true,
        true,
        true,
        true,
      ));
    return $view->render();
  }

  private function renderPathIcon($type, $text) {

    require_celerity_resource('diffusion-icons-css');

    return phutil_render_tag(
      'span',
      array(
        'class' => 'diffusion-path-icon diffusion-path-icon-'.$type,
      ),
      phutil_escape_html($text));
  }

}
