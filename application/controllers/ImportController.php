<?php

if (!isset($_GET['2Cnh^98xmRRsWK9G'])) { die('ONLY FOR COURSE ADMIN, I know... silly'); }


require_once('Apache/Solr/Service.php');

class ImportController extends Zend_Controller_Action
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
	
	public function importAction()
    {
        // action body
        
        $year = $this->_request->getParam('year');
		$course = $this->_request->getParam('course');
		$week = $this->_request->getParam('week');
		$assignment = $this->_request->getParam('assignment');
        
		$solr = new Apache_Solr_Service('localhost', 8983, '/solr/collection1');
		
		/*var_dump($solr->ping());
		exit;
		*/
		
		$courseIdentifier = $year . '_' . $course . '_' . $week . '_' . $assignment;
		
		$solr->deleteByQuery('course_identifier:"' . $courseIdentifier. '"');
		$solr->commit();
		
		
		
		
        // fetch users participating
        // http://www.wing.rug.nl/Justitia/admin_results.php/2012-2013/algoritmiek/week2/Cleopatra/
        $client = new Zend_Http_Client(null, array( 'timeout' => 60));
		
		
		$client->setCookieJar($this->_cookiejar);
		
		$client->setUri('https://themis.housing.rug.nl/results/' . $year . '/' . $course . '/' . $week . '/' . $assignment);
        $response = $client->request();
		
		//$search = new Zend_Search_Lucene(APPLICATION_PATH . '/search/' . $year . '_' . $course . '_' . $week . '_' . $assignment . '/', true);
				
		$matches = array();
		preg_match_all('/(\@submissions\/(s([0-9]+)(\+s[0-9]+)?)\/(s([0-9]+)(\+s[0-9]+)?\-([0-9]+)))\"\>(.*)\ \((.*)\)\<\/a\>/U', str_replace('&#x2F;', '/', $response->getBody()), $matches);

		$submissions = array(
		'by-student'	=> array(),
		'by-submission'	=> array()
		);
		$grades = array();


        // per user submission fetch
        // http://www.wing.rug.nl/Justitia/admin_user_submission.php/2012-2013/algoritmiek/week2/Cleopatra/?userid=425
        $i = -1;
        foreach ($matches[0] as $match) {
        	$i++;
			
			$client->setUri('https://themis.housing.rug.nl/stats/' . $year . '/' . $course . '/' . $week . '/' . $assignment . '/@submissions/' . $matches[2][$i] . '/' . $matches[5][$i]);
			$response = $client->request();

            #var_dump($response->getBody());exit;
			
			// check for no submission by student
			if (strpos($response->getBody(), 'no submissions have been made for this assignment') !== false) {
				$status = 'none';
				
				$json['by-student'][$matches[3][$i]] = $submissionId;
				$json['by-submission'][$submissionId] = array(
				'students'	=> array($matches[3][$i]),
				'files'		=> array(),
				'status'	=> $status,
				'highestFraud'		=> 0
				);
			
				continue;
			}

            $submissionId = $assignment . '.' . str_replace('+', '_', $matches[2][$i]);
			
			$body = substr($response->getBody(), strpos($response->getBody(), '<h3>Best</h3>'));
			#$body = substr($body, strpos($body, 'id="submission-')+15);
			#$submissionId = substr($body, 0, strpos($body, '"'));
			$body = substr($body, 0, strpos($body, '<h3>Latest</h3>'));

			if (strpos($body, '<tr><td>Status</td><td><strong>passed - Passed all test cases</strong></td></tr>') !== false) {
				$status = 'passed';
			} elseif (strpos($body, '<tr><td>Status</td><td><strong>diff - The program produced the wrong output</strong></td></tr>') !== false) {
				$status = 'failed:1';
			} else {
				$status = 'failed';
			}
			

			// <td>Submitted&nbsp;by</td><td>M.W. van Dongen <small>(s2135450)</small>, R.L. Prins <small>(s1991698)</small> <a href="
			// <a href="download_submission.php/34474/code/cleopatra.m" class="file m">cleopatra.m</a>
			
			#$submitted = array();
			#preg_match_all('/\<td\>Submitted\&nbsp\;by\<\/td\><td>(.*\ \<small\>\(([a-z0-9]+)\)\<\/small\>)(\,\ .*\ \<small\>\(([a-z0-9]+)\)\<\/small\>)?\ \<a\ href\=\"/U', $body, $submitted);
			
			$files = array();
			preg_match_all('/\<a\ href\=\"download\_submission\.php\/([0-9]+)\/code\/(.*)\"\ class\=\".*\"\>(.*)\<\/a\>/U', $body, $files);
						
			$document = new Apache_Solr_Document();
						
			$solr->deleteById($submissionId);
			$solr->commit();
						
			//$doc = new Zend_Search_Lucene_Document();
			
			$document->id = $submissionId;
			//$doc->addField(Zend_Search_Lucene_Field::keyword('submissionId', $submissionId));
			//$doc->addField(Zend_Search_Lucene_Field::unIndexed('assignment', $year . '/' . $course . '/' . $week . '/' . $assignment));
			
			$students = array();
            $students = explode('+', str_replace('s', '', $matches[2][$i]));
            //var_dump($students);exit;

			#$students[] = $submitted[2][0];
			
			#if ($submitted[4][0] != '') {
			#	$students[] = $submitted[4][0];
			#}
			
			$document->students = $students;
			$document->course_identifier = $courseIdentifier;
			//$doc->addField(Zend_Search_Lucene_Field::text('students', implode(' ', $students)));
			
			$f = -1;
			$filenames = array();
			$codes = array();
			$hashes = array();
			#foreach ($files[0] as $file) {
			#	$f++;
				
			#	try {
			#	$client->setUri('http://justitia.housing.rug.nl/download_submission.php/' . $files[1][$f] . '/code/' . urlencode($files[2][$f]));
			#	$contentsRaw = $client->request()->getBody();
			#	} catch (Exception $e) {
			
			#		$client->setUri('http://justitia.housing.rug.nl/download_submission.php/' . $files[1][$f] . '/code/' . urlencode($files[2][$f]));
			#		$contentsRaw = $client->request()->getBody();
			#	}

				
			#	$filename = strtolower(str_replace('.', '_', $files[2][$f]));
			#	$filenames[] = $filename;
				
				// filter comments so 
			#	$contents = preg_replace('/\%(.*)(\r?\n)/U', '', $contentsRaw . "\n");
			#	$contents = str_replace(array("\n", "\r"), ' ', $contents);
				
			#	$codes[] = $contents;
				
				
			#	$document->{$filename . '_t'} = utf8_encode($contentsRaw);
			#}

            $random = mt_rand(1000000, 99999999);

            #$zip = copy('https://themis.housing.rug.nl/download/' . $year . '/' . $course . '/' . $week . '/' . $assignment . '/@submissions/' . $matches[2][$i] . '/' . $matches[5][$i] . '/source', '/tmp/' . $random . '.zip');

            #exec('unzip /tmp/' . $random . '.zip');a


            $client->setUri('https://themis.housing.rug.nl/download/' . $year . '/' . $course . '/' . $week . '/' . $assignment . '/@submissions/' . $matches[2][$i] . '/' . $matches[5][$i] . '/source');
			$zipje = $client->request()->getBody();

            exec('rm -R /tmp/zipje/');
            exec('rm /tmp/zipje.zip');


            file_put_contents('/tmp/zipje.zip', $zipje);
            exec('unzip /tmp/zipje.zip -d /tmp/zipje/');

            //var_dump($zipje);exit;

            $files1 = scandir('/tmp/zipje');
            
            foreach ($files1 as $file) {
                if ($file != '.' and $file != '..') {
                    $filenames[] = $file;
    
    
                    $contentsRaw = file_get_contents('/tmp/zipje/' . $file);
                    
                    $contents = preg_replace('/\%(.*)(\r?\n)/U', '', $contentsRaw . "\n");
			        $contents = str_replace(array("\n", "\r"), ' ', $contents);

                    $codes[] = file_get_contents('/tmp/zipje/' . $file);

                    $document->{$file . '_t'} = utf8_encode($contentsRaw);
                }
            }
            
            //var_dump($codes);var_dump($filenames);exit;
			
			$document->code = $codes;
			$document->filenames = $filenames;
			$document->status = $status;
			$solr->addDocument($document);
			

			
			$submissions['by-student'][$matches[3][$i]] = $submissionId;
			$submissions['by-submission'][$submissionId] = array(
			'students'	=> $students,
			'files'		=> $filenames,
			'status'	=> $status,
			'highestFraud'		=> 0
			);
			
			$grades[$matches[3][$i]] = 0;
			
			$solr->commit();
			$solr->optimize();
			
			usleep(mt_rand(400, 1200));
        }

		$solr->commit();
		$solr->optimize();
		
		// check for fraud
		
		
		
        
        //$search->optimize();
				
		file_put_contents(APPLICATION_PATH . '/data/grades/' . $year . '_' . $course . '_' . $week . '_' . $assignment . '.json', Zend_Json_Encoder::encode($grades));
		file_put_contents(APPLICATION_PATH . '/data/submissions/' . $year . '_' . $course . '_' . $week . '_' . $assignment . '.json', Zend_Json_Encoder::encode($submissions));
     }


}

