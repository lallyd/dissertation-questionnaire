<?php

namespace Questionnaire\Model;

class Question {

	public $id, $title, $position, $section, $type, $allow_multi;

	public function getForDb(){
		return array(
			"id" => $this->id,
			"title" => $this->title,
			"position" => $this->position,
			"section" => $this->section,
			"type" => $this->type,
			"allow_multi" => $this->allow_multi ?: 0
		);
	}


	public function asArray(){
		return (array) $this;
	}
}
