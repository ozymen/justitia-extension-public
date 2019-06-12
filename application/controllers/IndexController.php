<?php

class IndexController extends Zend_Controller_Action
{

    public function init()
    {
        /* Initialize action controller here */
    }

    public function indexAction()
    {
        // action body
        
         $session = new Zend_Session_Namespace('session');
        
        if ($this->_request->isPost()) {
        	$cookieJar = new Zend_Http_CookieJar();
			
			$client = new Zend_Http_Client('https://themis.housing.rug.nl/', ['maxredirects' => 0]);
			$client->setCookieJar($cookieJar);
			
			$client->setUri('https://themis.housing.rug.nl/');
			$response = $client->request();
			
			
			$client->setUri('https://themis.housing.rug.nl/log/in');

            $_POST['username'] = strtolower($_POST['username']);
			
			$client->setMethod(Zend_Http_Client::POST);
			$client->setParameterPost('origin', '');
			$client->setParameterPost('user', $_POST['username']);
			$client->setParameterPost('pass', $_POST['password']);
			//$client->setParameterPost('submit', 'Log in');
			
			$response = $client->request();
			
			if (strpos($response->getBody(), 'Found') === false || !in_array($_POST['username'], ['p0123', 'p456789', '...'])) {
				$this->view->error = 1;
				
				return;
			}
			
			$session = new Zend_Session_Namespace('session');
			
			$session->cookiejar = $cookieJar;
			

			return $this->_redirect('/grade');
        } elseif (isset($session->cookiejar)) {
        	return $this->_redirect('/grade');
        }
    }


}

