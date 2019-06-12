<?php

require_once 'geshi/geshi.php';

class GradeController extends Zend_Controller_Action
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
    }
	
	public function gradeAction()
    {
        // action body
        
        $year = $this->_request->getParam('year');
		$course = $this->_request->getParam('course');
		$week = $this->_request->getParam('week');
		$assignment = $this->_request->getParam('assignment');
		$submissionId = $this->view->submissionId = $this->_request->getParam('submissionId');
		
		if (!file_exists(APPLICATION_PATH . '/data/submissions/' . $year . '_' . $course . '_' . $week . '_' . $assignment . '_grades.json')) {
			file_put_contents(APPLICATION_PATH . '/data/submissions/' . $year . '_' . $course . '_' . $week . '_' . $assignment . '_grades.json', json_encode(array('test' => 'test')));
		}
		
		if (!file_exists(APPLICATION_PATH . '/data/submissions/' . $year . '_' . $course . '_' . $week . '_' . $assignment . '_comments.json')) {
			file_put_contents(APPLICATION_PATH . '/data/submissions/' . $year . '_' . $course . '_' . $week . '_' . $assignment . '_comments.json', json_encode(array('test' => 'test')));
		}
		
		$grades = $this->view->grades = json_decode(file_get_contents(APPLICATION_PATH . '/data/submissions/' . $year . '_' . $course . '_' . $week . '_' . $assignment . '_grades.json'));
		$comments = $this->view->comments = json_decode(file_get_contents(APPLICATION_PATH . '/data/submissions/' . $year . '_' . $course . '_' . $week . '_' . $assignment . '_comments.json'));
		
		$courseIdentifier = $year . '_' . $course . '_' . $week . '_' . $assignment;
		
		$this->view->submissions = $submissions = json_decode(file_get_contents(APPLICATION_PATH . '/data/submissions/' . $year . '_' . $course . '_' . $week . '_' . $assignment . '.json'));

		if ($submissionId != false) {
			$this->view->submission = $submission = $submissions->{'by-submission'}->{$submissionId};
			
			//$search = new Zend_Search_Lucene(APPLICATION_PATH . '/search/' . $year . '_' . $course . '_' . $week . '_' . $assignment . '/');

			$result = json_decode(file_get_contents('http://localhost:8983/solr/collection1/select?q=id:"' . $submissionId . '"&mlt.boost=true&mlt=true&mlt.fl=code&mlt.count=8&wt=json&fl=*,score'));
			
			$fraud = json_decode(file_get_contents('http://localhost:8983/solr/collection1/mlt?q=id:"' . $submissionId . '"&mlt.match.include=false&fq=+course_identifier:"' . 
$courseIdentifier . '"&mlt.boost=true&mlt=true&mlt.fl=code&mlt.count=8&wt=json&fl=*,score'));
			
			$this->result = $result;
			$documents = $result->response->docs;
			
			//$documents = $search->find('submissionId:' . $submissionId);
			
			// first doc
			foreach ($documents as $document) {break;}

            //var_dump($result);exit;
			
			//var_dump($document->students);exit;
			
			$this->view->students = $document->students;
			
			$this->view->document = $document;
			
			require_once 'geshi/geshi.php';
			
			$geshi = new GeSHi();
			$geshi->set_language('matlab');
			$geshi->enable_line_numbers(GESHI_NORMAL_LINE_NUMBERS);
			
			$this->view->submissionId = $submissionId;
			
			$this->view->files = array();

			foreach ($submission->files as $file) {
				$geshi->set_source(trim($document->{$file . '_t'}));
				
				// fraude detection
				//Zend_Search_Lucene::setResultSetLimit(10);
				//$fraude = $search->find($file . ':"' . addslashes(trim($document->{$file})) . '"~0.5 -submissionId:' . $submissionId);
				//$fraude = $search->find('("' . $file . '":"' . Zend_Search_Lucene_Search_QueryParser::parse($document->{$file . '_t'}) . '"~100) -submissionId:' . $submissionId);
				
				#$fraude = $search->find('' . Zend_Search_Lucene_Search_QueryParser::parse($document->{$file}) . '', $file);
				
				
				$this->view->lastDocument = trim($document->{$file . '_t'});
				$this->view->files[$file] = array('code' => $geshi->parse_code());
			}
			
			$documents = $result->moreLikeThis->{$submissionId}->docs;
			$documents = $fraud->response->docs;
			
			$this->view->fraude = array();
			foreach ($documents as $document) {
				$geshi->set_source(trim($document->{$file . '_t'}));
				
				// fraude detection
				//Zend_Search_Lucene::setResultSetLimit(10);
				//$fraude = $search->find($file . ':"' . addslashes(trim($document->{$file})) . '"~0.5 -submissionId:' . $submissionId);
				//$fraude = $search->find('("' . $file . '":"' . Zend_Search_Lucene_Search_QueryParser::parse($document->{$file . '_t'}) . '"~100) -submissionId:' . $submissionId);
				
				#$fraude = $search->find('' . Zend_Search_Lucene_Search_QueryParser::parse($document->{$file}) . '', $file);
				
				
				
				$this->view->fraude[] = $document;
			}
			
			
			// save grades and comments
			if ($this->_request->isPost()) {
				
				$grades->{$submissionId} = $_POST['grade'];
				$comments->{$submissionId} = $_POST['comment'];
				
				file_put_contents(APPLICATION_PATH . '/data/submissions/' . $year . '_' . $course . '_' . $week . '_' . $assignment . '_grades.json', json_encode($grades));
				file_put_contents(APPLICATION_PATH . '/data/submissions/' . $year . '_' . $course . '_' . $week . '_' . $assignment . '_comments.json', json_encode($comments));
				
				$this->view->success = true;
			}
		}

    }

	function ajaxAction()
	{
		
		
		
	}
	
}
