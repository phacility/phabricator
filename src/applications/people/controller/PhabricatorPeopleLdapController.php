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

final class PhabricatorPeopleLdapController
  extends PhabricatorPeopleController {

  public function shouldRequireAdmin() {
    return true;
  }

  private $view;

  public function willProcessRequest(array $data) {
    $this->view = idx($data, 'view');
  }

  public function processRequest() {

    $request = $this->getRequest();
    $admin = $request->getUser();

    $base_uri = '/people/edit/';

    $content = array();


    $response = $this->processBasicRequest();

    if ($response instanceof AphrontResponse) {
      return $response;
    }

    $content[] = $response;


    return $this->buildStandardPageResponse(
      $content,
      array(
        'title' => 'Import Ldap Users',
      ));
  }

  /**
   * Displays a ldap login form, as we need to auth before we can search
   */
  private function processBasicRequest() {
    $panels = array();

    $request = $this->getRequest();

    $admin = $request->getUser();

    $form = id(new AphrontFormView())
      ->setUser($admin)
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setLabel('LDAP username')
        ->setName('username'))
      ->appendChild(
        id(new AphrontFormPasswordControl())
        ->setLabel('Password')
        ->setName('password')) 
      ->appendChild(
        id(new AphrontFormTextControl())
        ->setLabel('LDAP query')
        ->setName('query'))
      ->setAction($request->getRequestURI()->alter('search', 'true')->alter('import', null))
      ->appendChild(
        id(new AphrontFormSubmitControl())
        ->setValue('Search'));

    $panel = new AphrontPanelView();
    $panel->setHeader('Import Ldap Users');
    $panel->appendChild($form);

    
    if($request->getStr('import')) {
      $panels[] = $this->processImportRequest($request);
    }
    
    $panels[] = $panel;

    if($request->getStr('search')) {
      $panels[] = $this->processSearchRequest($request);
    }

    return $panels;

  }

  private function processImportRequest($request) {
    $admin = $request->getUser();
    $usernames = $request->getArr('usernames'); 
    $emails = $request->getArr('email'); 
    $names = $request->getArr('name'); 
    
    $panel = new AphrontErrorView();
    $panel->setSeverity(AphrontErrorView::SEVERITY_NOTICE);
    $panel->setTitle("Import Successful");
    $errors = array("Successfully imported users from ldap"); 


    foreach($usernames as $username) {
      $user = new PhabricatorUser();
      $user->setUsername($username);
      $user->setRealname($names[$username]);
      
      $email_obj = id(new PhabricatorUserEmail())
        ->setAddress($emails[$username])
        ->setIsVerified(1);
      try {
        id(new PhabricatorUserEditor())
          ->setActor($admin)
          ->createNewUser($user, $email_obj);
	
        $ldap_info = new PhabricatorUserLDAPInfo();
        $ldap_info->setLDAPUsername($username);
        $ldap_info->setUserID($user->getID());
        $ldap_info->save();
        $errors[] = 'Succesfully added ' . $username;
      } catch (Exception $ex) {
        $errors[] = 'Failed to add ' . $username . ' ' . $ex->getMessage();
      }
    }    

    $panel->setErrors($errors);
    return $panel; 

  }

  private function processSearchRequest($request) {
    $panel = new AphrontPanelView();

    $admin = $request->getUser();

    $username = $request->getStr('username');
    $password = $request->getStr('password');
    $search   = $request->getStr('query');

    try {
      $ldapProvider = new PhabricatorLDAPProvider();
      $ldapProvider->auth($username, $password);
      $results = $ldapProvider->search($search);
      foreach($results as $key => $result) {
        $results[$key][] = $this->renderUserInputs($result);
      }

      $form = id(new AphrontFormView())
        ->setUser($admin);

      $table = new AphrontTableView($results);
      $table->setHeaders(
        array(
          'Username',
          'Email',
          'RealName',
          '',
        ));
      $form->appendChild($table);
      $form->setAction($request->getRequestURI()->alter('import', 'true')->alter('search', null))
        ->appendChild(
          id(new AphrontFormSubmitControl())
          ->setValue('Import'));


      $panel->appendChild($form);
    } catch (Exception $ex) {
      $error_view = new AphrontErrorView();
      $error_view->setTitle('LDAP Search Failed');
      $error_view->setErrors(array($ex->getMessage()));
      return $error_view;
    }
    return $panel;

  }
   
  private function renderUserInputs($user) {
        $username = $user[0];
	$inputs =  phutil_render_tag(
          'input',
          array(
            'type' => 'checkbox',
            'name' => 'usernames[]',
            'value' =>$username, 
          ),
          '');

	$inputs .=  phutil_render_tag(
          'input',
          array(
            'type' => 'hidden',
            'name' => "email[$username]",
            'value' =>$user[1], 
          ),
          '');
 
	$inputs .=  phutil_render_tag(
          'input',
          array(
            'type' => 'hidden',
            'name' => "name[$username]",
            'value' =>$user[2], 
          ),
          '');

        return $inputs;

  }
}
