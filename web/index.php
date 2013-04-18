<?php

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$app = require __DIR__.'/../app/app.php';

$app->get("/", function() use($app) {
	levent("Load Home");
	return $app['twig']->render("index.html");
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
	$styled = ($app['styled'] == $section) ? "styled" : "unstyled";
	levent("Load questionnaire". $section .'('.$styled.')');

	$questions = $app['repos.question']->findBySection($section);
	foreach ($questions as $k => $v){
		$questions[$k] = $v->asArray();
	}
	return $app['twig']->render("questionnaire.html", array(
		"questions" => $questions,
		"styled_page" => ($app['styled'] == $section),
		"back_to_bootstrap" => (3 == $section),
		"current_section" => $section,
		"styled_id" => $app['styled']
	));
})->value('section', 1);


$app->post("/questionnaire", function(Request $request) use ($app){
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
	return;
});

$app->get("/thankyou", function() use ($app){
	levent("Load Thankyou");
	return $app['twig']->render("thankyou.html");
});

$app->get("/cancel", function() use ($app){
	levent("Cancel Questionnaire");
	$app['db']->delete("answers", array(
		"user" => $app['user']
	));

	header("location: /");
});


	$app->get("/admin", function() use ($app) {
		$questions = $app['repos.question']->findAll();

		return $app['twig']->render("admin/list.html", array(
			"questions" => $questions
		));
	});

$app->get("/admin/question/{id}", function($id) use ($app) {
	$question = new Questionnaire\Model\Question;

	if ($id){ 
		$question = $app['repos.question']->getById($id);
	}

	return $app['twig']->render("admin/add_question.html", array(
		"question" => $question
	));

})->value('id', null);

$app->post("/admin/question", function(Request $request) use ($app) {
	$answers = $request->request->all();
	if ($answers['type'] != "predefined"){
		$answers['predefined_answers'] = array();
	}

	// Load up our question
	$question = new Questionnaire\Model\Question;
	if ($answers['id']){ 
		$question = $app['repos.question']->getById($answers['id']);
	}
	$question->id = isset($answers['id']) ? $answers['id'] : null;
	$question->title = $answers['title'];
	$question->section = $answers['section'];
	$question->type = $answers['type'];
	$question->allow_multi = isset($answers['allow_multi']) ? 1 : 0;
	$question->answers = $answers['predefined_answers'];

	$app['repos.question']->save($question);

	return $app->redirect("/admin");
});

$app->post("/admin/order_questions", function(Request $request) use ($app){

	foreach ($request->request->get("questions") as $id => $position){
		$app['db']->update("questions", array("position" => $position), array("id" => $id));
	}
	return true;
});

	$app->run();
