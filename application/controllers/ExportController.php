<?php

class ExportController extends Zend_Controller_Action
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
	
	
	public function exportAction()
    {
        
		 $year = $this->_request->getParam('year');
		$course = $this->_request->getParam('course');
		$week = $this->_request->getParam('week');
		$assignment = $this->_request->getParam('assignment');
		$submissionId = $this->view->submissionId = $this->_request->getParam('submissionId');
		
		
		$grades = $this->view->grades = json_decode(file_get_contents(APPLICATION_PATH . '/data/submissions/' . $year . '_' . $course . '_' . $week . '_' . $assignment . '_grades.json'));

		$comments = $this->view->grades = json_decode(file_get_contents(APPLICATION_PATH . '/data/submissions/' . $year . '_' . $course . '_' . $week . '_' . $assignment . '_comments.json'));
		
		$submissions = json_decode(file_get_contents(APPLICATION_PATH . '/data/submissions/' . $year . '_' . $course . '_' . $week . '_' . $assignment . '.json'));


		
		if ($this->_request->isPost()) {
			if (($handle = fopen($_FILES['nestor']['tmp_name'], "r")) !== FALSE) {
				$i = 0;
				$output = '';// . json_decode('"\uFEFF"');
			$contents = file_get_contents($_FILES['nestor']['tmp_name']);	
// first line is somehow bugged (special char somewhere), so copy
$output .= explode("\n", $contents)[0] . "\n";

    			while (($data = fgetcsv($handle, 4000, ",")) !== FALSE) {
    				$i++;
					
					if ($i == 1) {
						//$output = str_replace('"ï' , '"', trim('"' . implode('","', $data) . '"')) . "\n";
						continue;
					}
					
					$studentId = str_replace('s', '', $data[2]);
                    $submissionId = false;

                    foreach ($submissions->{'by-student'} as $a => $b) {
                        if (strpos($b, $studentId) !== false) {
                            $submissionId = $b;
                        }
                    }
					
					#var_dump($data[2], $submissions->{'by-student'});
#var_dump($submissions->{'by-student'}->{$studentId});exit;

        
					
					if ($submissionId == false) { // || !isset($submissions->{'by-student'}->{$studentId})) {
						// NIET INGESCHREVEN VOOR HET VAK!!
						//echo $studentId . ' heeft niks ingeleverd!!' . "<br />";
						$output .= '"' . implode('","', $data) . '"' . "\r\n";
						continue;
					}
					
					#$submissionId = $submissions->{'by-student'}->{$studentId};
					
					$data[4+2] = @number_format($grades->{$submissionId}, 2, '.', ','); 
					$data[6+2] = 'SMART_TEXT';
					$data[7+2] = str_replace(array("\n", "\r"), '', str_replace(',', '..', addslashes($comments->{$submissionId})));
					$data[8+2] = 'SMART_TEXT';
					
					$output .= '"' . implode('","', $data) . '"' . "\r\n";
#var_dump($output);   exit;
					
				}

				//$output = str_replace('"Last Name"', 'Last Name', $output);
				
				// Es wird downloaded.pdf benannt
				header('Content-Disposition: attachment; filename="' . $assignment . '.csv"');
				header("Content-Type: text/csv");
				die($output);
			} else {
				throw new Zend_Controller_Action_Exception();
			}
		}
	}


}

