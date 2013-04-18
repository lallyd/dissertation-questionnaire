<?php

namespace Questionnaire\Repository;

class Question {

	private $db;

	public function __construct($db) {
		$this->db = $db;
	}

	public function getById($id) {
		$questions = $this->db->fetchAssoc("SELECT * FROM questions WHERE id=?", array($id));
		return $this->hydrate($questions);
	}

	public function findAll() {
		$questions = $this->db->fetchAll("SELECT * FROM questions ORDER BY position ASC");
		return $questions;
	}

	public function findBySection($section) {
		$questions = $this->db->fetchAll("SELECT * FROM questions WHERE section=? ORDER BY position ASC", array($section));
		foreach ($questions as $k => $v) {
			$questions[$k] = $this->hydrate($v);
		}
		return $questions;
	}

	public function save($question){
		$method = $question->id ? "update" : "insert";
		$data = $question->getForDb();
		$where = array("id" => $data['id']);
		$res =  $this->db->{$method}("questions", $data, $where);

		$this->db->delete("possible_answers", array("question_id" => $data['id']));
		foreach ($question->answers as $a){
			$this->db->insert("possible_answers", array("question_id" => $data['id'], "title" => $a));
		}
	}

	public function hydrate($row){
		$q = new \Questionnaire\Model\Question;
		foreach ($row as $k => $v){
			$q->{$k} = $v;
		}

		// Cheat slightly and add an array of possible answers here
		$q->answers = $this->db->fetchAll("SELECT * FROM possible_answers WHERE question_id=?", array($q->id));
		if (count($q->answers) == 0){ $q->answers = array(null); }

$q->title = auto_link_text($q->title);

		return $q;
	}


}
