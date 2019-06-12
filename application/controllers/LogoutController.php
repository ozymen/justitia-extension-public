<?php

class LogoutController extends Zend_Controller_Action
{

    public function init()
    {
        /* Initialize action controller here */
    }

    public function indexAction()
    {
        // action body
        
         $session = new Zend_Session_Namespace('session');
        
		$session->unsetAll();
		
        return $this->_redirect('/');
    }


}

