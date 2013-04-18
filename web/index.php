<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$app = require __DIR__.'/../app/app.php';

$app->get("/", function() use($app) {
	levent("Load Home");
	return $app['twig']->render("index.html");
});

$app->get("/done", function() use($app) {
	levent("Done");
	return $app['twig']->render("done.html");
});

$app->get("/out/{site}", function($site) use ($app){
	$urls = array(
		"twitter" => "http://twitter.com/lallyd",
		"linkedin" => "http://uk.linkedin.com/pub/lalita-d-cruze/3a/595/936",
		"blog" => "http://oodelallyd.com/"
	);

	levent("Out: ". $site);

	header("location: ". $urls[$site]);
die;
});


foreach (array("about", "results", "privacy", "bibliography") as $p) {
	$app->get("/".$p, function() use($app, $p) {
		levent("Load ". $p);
		return $app['twig']->render($p.".html");
	});
}

$app->get("/questionnaire/{section}", function($section) use($app) {
	return $app['twig']->render("done.html");
})->value('section', 1);


$app->post("/qsuestionnaire", function(Request $request) use ($app){
	$answers = $request->request->all();
	foreach ($answers as $k => $v){
		if ($k == "current_page"){
			$page = $v;
			levent("Save ". $page);
			continue;
		}

		if (!is_array($v)){
			$v = array($v);
		}

		$question = substr(reset(explode("_", $k)), 1);
		foreach ($v as $answer){
			$app['db']->insert("answers", array(
				"question" => $question,
				"answer" => $answer,
				"styled" => ($app['styled'] == $page) ? 1 : 0,
				"user" => $app['user'],
				"ip" => $_SERVER['REMOTE_ADDR']
			));
		}

	}

	if ($page == 3){
		$loc = "/thankyou";
	} else {
		levent("Save page " . $page);
		$loc = "/questionnaire/".(++$page);
	}

	header("location: ". $loc);
die;
});

$app->get("/thankyou", function() use ($app){
	levent("Load Thankyou");
	return $app['twig']->render("thankyou.html");
});


$app->get("/aggregates-lally", function(Request $request) use ($app){

	$aggs = $app['db']->fetchAll("SELECT question, answer FROM answers GROUP BY question, answer, user");

	$ans = array();
	foreach ($aggs as $ag){
		$q = $ag['question'];
		$a = $ag['answer'];
		if (!isset($ans[$q])){
			$ans[$q] = array();
		}

		if (!isset($ans[$q][$a])){
			$ans[$q][$a] = 0;
		}

		$ans[$q][$a]++;
	}
	echo "<pre>";
	print_r($ans);die;

});
$app->get("/answers-lally", function(Request $request) use ($app){
	if ($request->query->get("pass") != "gogogadgetarms"){
		throw new Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
	}

	$filter = $request->query->get("filter");
	$where = '';
	if (count($filter)){
		$allUIDs = array();
		foreach ($filter as $q => $val){
			$uids = array();
			$where = "WHERE question=".$app['db']->quote($q)." AND answer=".$app['db']->quote($val);
			$users = $app['db']->fetchAll("SELECT user FROM answers ". $where);
			foreach ($users as $u){
				$uids[] = $app['db']->quote($u['user']);
			}
			if (!count($allUIDs)){
				$allUIDs = $uids;
			} else {
				$allUIDs = array_intersect($allUIDs, $uids);
			}
		}

		$uids = array_unique($allUIDs);
		if (count($uids)){
			$where = 'WHERE user IN ('.implode($uids,",").')';
		} else {
			$where = 'WHERE 0=1';
		}
	}

	if ($request->query->get("styled")){
		if (strlen($where) == 0){
			$where = 'WHERE ';
		} else {
			$where .= 'AND';
		}
		$where .= ' styled='.$app['db']->quote($request->query->get("styled"));
	}

	$qry = "SELECT a.*, q.title FROM answers a JOIN questions q ON a.question=q.id ".$where." ORDER BY a.user, a.question ASC";
	$answers = $app['db']->fetchAll($qry);

	$data = array();
	foreach ($answers as $a){
		$user = $a['user'];
		$q = $a['question'];

		if (!isset($data[$user])){
			$data[$user] = array(
				"styled" => $a['styled'] == 1 ? "A" : "B",
				"questions" => array()
			);
		}

		if (!isset($data[$user]['questions'][$q])){
			$data[$user]['questions'][$q] = array(
				"question" => $a['title'],
				"answers" => array()
			);
		}

		$data[$user]['questions'][$q]['answers'][] = $a['answer'];
	}

	return $app['twig']->render("answers.twig", array(
		"answers" => $data
	));
});

$app->get("/events-lally", function(Request $request) use ($app){
	if ($request->query->get("pass") != "gogogadgetarms"){
		throw new Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
	}

	$aggs = $app['db']->fetchAll("SELECT * FROM events ORDER BY created_at ASC");

	$events = array();
	$counts = array();
	$timings = array();
	$visits = array();
	$usedVisits = array();
	$completions = array();
	$usedCompletions = array();


	foreach ($aggs as $a){
		//print_r($aggs);die;
		$a['event'] = substr($a['event'], 0 , strlen("Load questionnaire1"));

		// Visit dates
		if($a['event'] == "Load Home" && !in_array($a['user'], $usedVisits)){
			$d = date('Y-m-d', strtotime($a['created_at']));
			if (!isset($visits[$d])){
				$visits[$d] = 0;
			}

			$visits[$d]++;
			$usedVisits[] = $a['user'];
		}

		// Timings on each questionnaire
		if (!isset($timings[$a['user']])){
			$timings[$a['user']] = array(
				"styled" => $a['styled'],
				"q1" => array(),
				"q2" => array(),
				"q3" => array()
			);
		}

		if (substr($a['event'], 0, -1) == "Load questionnaire"){
			$timings[$a['user']]['q'.substr($a['event'], -1)]['start'] = $a['created_at'];
		}

		if (substr($a['event'], 0, 4) == "Save"){
			$timings[$a['user']]['q'.substr($a['event'], -1)]['end'] = $a['created_at'];

			$d = date('Y-m-d', strtotime($a['created_at']));
			if (!isset($completions[$d])){
				$completions[$d] = array(
					1=> 0,
					2=> 0,
					3=> 0
				);
			}

			$completions[$d][substr($a['event'],-1)]++;
			$usedCompletions[] = $a['user'];
		}

		// What events did that user see?
		if (!isset($events['user'])){
			$events['user'] = array();
		}

		$events['user'][] = $a['event'];

		// How many times was each event triggered?
		if (!isset($counts[$a['event']])){
			$counts[$a['event']] = 0;
		}
		$counts[$a['event']]++;

	}

	// Additional processing for time taken for each questionnaire
	$processedT = array("1" => array(1=>array(),2=>array(),3=>array()), "2" => array(1=>array(),2=>array(),3=>array()));
	foreach ($timings as $user => $t){
		foreach (array("q1","q2","q3") as $q){
			$start = strtotime($t[$q]['start']);
			$end = strtotime($t[$q]['end']);
			if (!$start || !$end){ continue; }
			$q = substr($q,1);
			$diff = $end - $start;
			if ($diff < 0){ continue; }
			$processedT[$t['styled']][$q][] = $diff;
		}
	}

	$humanT = array(
		1 => array(),
		2 => array()
	);

	foreach (range(1,2) as $i){
		foreach (range(1,3) as $j){
			$humanT[$i][$j] = array(
				"mean" => mmmr($processedT[$i][$j], "mean"),
				"median" => mmmr($processedT[$i][$j], "median"),
				"mode" => mmmr($processedT[$i][$j], "mode"),
				"range" => mmmr($processedT[$i][$j], "range")
			);

			$humanT[$i][$j]['min'] = min($processedT[$i][$j]);
			$humanT[$i][$j]['max'] = max($processedT[$i][$j]);
		}

	}

	$nToL = array(
		1 => "A", 2 => "B", 3=>"C"
	);
	foreach ($humanT as $styled => $v){
		echo '<h3>Questionnaire '.$nToL[$styled].' styled</h3>';
		foreach ($v as $k => $vv){
			echo '<h4>Questionnaire '.$nToL[$k].'</h4>';
			foreach (array("mean","median","mode","range","min","max") as $sk){
				echo ucfirst($sk).": ".$vv[$sk].'<br />';
			}
		}
	}


	echo '<pre>';
//	print_r($humanT);die;
//	print_r($processedT);die;
//	print_r($visits);die;
//	print_r($counts);die;
	print_r($completions);die;

});

$app->run();


function mmmr($array, $output = 'mean'){ 
	if(!is_array($array)){ 
		return FALSE; 
	}else{ 
		switch($output){ 
		case 'mean': 
			$count = count($array); 
			$sum = array_sum($array); 
			$total = $sum / $count; 
			break; 
		case 'median': 
			rsort($array); 
			$middle = round(count($array) / 2); 
			$total = $array[$middle-1]; 
			break; 
		case 'mode': 
			$v = array_count_values($array); 
			arsort($v); 
			foreach($v as $k => $v){$total = $k; break;} 
			break; 
		case 'range': 
			sort($array); 
			$sml = $array[0]; 
			rsort($array); 
			$lrg = $array[0]; 
			$total = $lrg - $sml; 
			break; 
		} 
		return round($total,2); 
	} 
} 

