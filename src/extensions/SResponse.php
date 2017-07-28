<?php

namespace App\Extensions;

class SResponse {
	public $hasErrors = false;
	public $statusCode = 200;
	public $data;
	
	private $_errors;

	public function __construct() {
		$this->_errors = [];
	}

	public function addError($error) {
		array_push($this->_errors, $error);
		$this->hasErrors = true;
	}

	public function getErrors() {
		return $this->_errors;
	}

	public function json() {
		return json_encode($this);
	}
}