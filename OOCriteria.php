<?php


class OOCriteria extends CComponent
{
	public $fields;
	public $domain = array();
	public $offset = 0;
	public $limit;
	public $order;
	public $context;
	public $index;

	public function __construct($data=array())
	{
		foreach ($data as $name => $value) {
			$this->$name = $value;
		}
	}

	protected function addValidDomain($ditem)
	{
		if ($this->domain == null) {
			$this->domain = array();
		}
		$this->domain[] = $ditem;
	}

	public function addDomain(array $ditem) {
		if ($ditem[2] !== null) {
			$this->addValidDomain($ditem);
		}
	}

	public function mergeWith($criteria) {

		if ($this->fields !== null && $criteria->fields !== null) {
			$this->fields = array_merge($this->fields, $criteria->fields);
		} else if ($this->fields === null) {
			$this->fields = $criteria->fields;
		}

		if ($this->domain !== null && $criteria->domain !== null) {
			$this->domain = array_merge($this->domain, $criteria->domain);
		} else if ($this->domain === null) {
			$this->domain = $criteria->domain;
		}

		if ($this->context !== null && $criteria->context !== null) {
			$this->context = array_merge($this->context, $criteria->context);
		} else if ($this->context === null) {
			$this->context = $criteria->context;
		}
	}

}
