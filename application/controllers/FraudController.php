<?php

require_once('Apache/Solr/Service.php');
require_once 'geshi/geshi.php';


class FraudController extends Zend_Controller_Action
{

    public function init()
    {
        /* Initialize action controller here */
        $session = new Zend_Session_Namespace('session');
		
		if (!isset($session->cookiejar)) {
			return $this->_redirect('/');
		}
		
		$this->_cookiejar = $session->cookiejar;
		
		$this->_courses = new Zend_Config_Ini(APPLICATION_PATH . '/configs/courses.ini', 'production');
		
    }
	

    public function indexAction()
    {
        // action body
        $this->view->courses = $this->_courses;
        
        $solr = new Apache_Solr_Service('localhost', 8983, '/solr/collection1');
		
		
		if ($this->_request->isPost()) {
			$query = 'course_identifier:"' . $_POST['course_identifier'] . '"';
			
			if (isset($_POST['submission_ids']) && $_POST['submission_ids'] != '') {
				$query .= ' AND (';
				
				$ids = explode(',', $_POST['submission_ids']);
				$out = '';
				foreach ($ids as $id) {
					$out[] = 'id:"' . $id . '"';
				}

				$query .= implode(' OR ', $out);				
				$query .= ')';
			}
			
			if (isset($_POST['student_ids']) && $_POST['student_ids'] != '') {
				$query .= ' AND (';
				
				$ids = explode(',', $_POST['student_ids']);
				$out = '';
				foreach ($ids as $id) {
					$out[] = 'students:"' . $id . '"';
				}

				$query .= implode(' OR ', $out);				
				$query .= ')';
			}
			
			
			$this->view->result = json_decode(file_get_contents('http://localhost:8983/solr/collection1/select?q=' . urlencode($query) . '&wt=json&fl=*,score'));
			
		}
    }


}

