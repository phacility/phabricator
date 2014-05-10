<?php

final class DiffusionBrowseTableView extends DiffusionView {

  private $paths;
  private $handles = array();

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

        $browse_link = phutil_tag('strong', array(), $this->linkBrowse(
          $base_path.$path->getPath().$dir_slash,
          array(
            'text' => $this->renderPathIcon('dir', $browse_text),
          )));
      } else if ($file_type == DifferentialChangeType::FILE_SUBMODULE) {
        $browse_text = $path->getPath().'/';
        $browse_link = phutil_tag('strong', array(), $this->linkExternal(
          $path->getHash(),
          $path->getExternalURI(),
          $this->renderPathIcon('ext', $browse_text)));
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
            'text' => $this->renderPathIcon($type, $browse_text),
          ));
      }

      $dict = array(
        'lint'      => celerity_generate_unique_node_id(),
        'commit'    => celerity_generate_unique_node_id(),
        'date'      => celerity_generate_unique_node_id(),
        'time'      => celerity_generate_unique_node_id(),
        'author'    => celerity_generate_unique_node_id(),
        'details'   => celerity_generate_unique_node_id(),
      );

      $need_pull[$base_path.$path->getPath().$dir_slash] = $dict;
      foreach ($dict as $k => $uniq) {
        $dict[$k] = phutil_tag('span', array('id' => $uniq), '');
      }

      $editor_button = '';
      if ($this->user) {
        $editor_link = $this->user->loadEditorLink(
          $base_path.$path->getPath(),
          1,
          $request->getRepository()->getCallsign());
        if ($editor_link) {
          $show_edit = true;
          $editor_button = phutil_tag(
            'a',
            array(
              'href' => $editor_link,
            ),
            pht('Edit'));
        }
      }

      $rows[] = array(
        $this->linkHistory($base_path.$path->getPath().$dir_slash),
        $editor_button,
        $browse_link,
        idx($dict, 'lint'),
        $dict['commit'],
        $dict['date'],
        $dict['time'],
        $dict['author'],
        $dict['details'],
      );
    }

    if ($need_pull) {
      Javelin::initBehavior(
        'diffusion-pull-lastmodified',
        array(
          'uri'   => (string)$request->generateURI(
            array(
              'action' => 'lastmodified',
            )),
          'map' => $need_pull,
        ));
    }

    $branch = $this->getDiffusionRequest()->loadBranch();
    $show_lint = ($branch && $branch->getLintCommit());
    $lint = $request->getLint();

    $view = new AphrontTableView($rows);
    $view->setHeaders(
      array(
        pht('History'),
        pht('Edit'),
        pht('Path'),
        ($lint ? $lint : pht('Lint')),
        pht('Modified'),
        pht('Date'),
        pht('Time'),
        pht('Author/Committer'),
        pht('Details'),
      ));
    $view->setColumnClasses(
      array(
        '',
        '',
        '',
        'n',
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
        $show_lint,
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

    return phutil_tag(
      'span',
      array(
        'class' => 'diffusion-path-icon diffusion-path-icon-'.$type,
      ),
      $text);
  }

}
