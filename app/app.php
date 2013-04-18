<?php

// Lally is 511ab69b654f9

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

require_once __DIR__.'/../vendor/autoload.php';

$app = new Silex\Application;

$app->register(new Silex\Provider\TwigServiceProvider(), array(
	'twig.path' => __DIR__.'/../views',
));

$app->register(new Silex\Provider\DoctrineServiceProvider(), array(
	'db.options' => array(
		'driver'    => 'pdo_mysql',
		'host'      => 'localhost',
		'dbname'    => 'questionnaire',
		'user'      => 'root',
		'password'  => '',
		'charset'   => 'utf8',
	),
));

$app->register(new Silex\Provider\SessionServiceProvider());

$app->error(function($e){
//	return 'Error';
	var_dump($e);die;
});


$app['repos.question'] = $app->share(function() use ($app){
	return new Questionnaire\Repository\Question($app['db']);
});

$app->before(function() use($app){
	//If we don't know them, set em an identifier
	if (null === $app['session']->get('ident')) {
		$app['session']->set("ident", uniqid());
		$app['session']->set("styled", mt_rand(1,2));
	}	

	$app['user'] = $app['session']->get("ident");
	$app['styled'] = $app['session']->get("styled");

});


function levent($event){
	global $app;
	if (!isset($app['user'])){
		$app['user'] = 0;
	}

	$app['db']->insert("events", array(
		"user" => $app['user'],
		"styled" => $app['styled'],
		"event" => $event
	));
}


function auto_link_text($text)
{
   $pattern  = '#\b(([\w-]+://?|www[.])[^\s()<>]+(?:\([\w\d]+\)|([^[:punct:]\s]|/)))#';
   $callback = create_function('$matches', '
       $url       = array_shift($matches);
       $url_parts = parse_url($url);

       $text = parse_url($url, PHP_URL_HOST) . parse_url($url, PHP_URL_PATH);

       $last = -(strlen(strrchr($text, "/"))) + 1;
       if ($last < 0) {
           $text = substr($text, 0, $last) . "&hellip;";
       }

       return sprintf(\'<a target="_blank" rel="nofollow" href="%s">%s</a>\', $url, $text);
   ');

   return preg_replace_callback($pattern, $callback, $text);
}

return $app;
