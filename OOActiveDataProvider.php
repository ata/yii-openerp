<?php


class OOActiveDataProvider extends CDataProvider
{
	public $modelClass;
	public $model;
	public $keyAttribute;

	private $_criteria;
	private $_sort;

	/**
	 * Constructor.
	 * @param mixed $modelClass the model class (e.g. 'Post') or the model finder instance
	 * (e.g. <code>Post::model()</code>, <code>Post::model()->published()</code>).
	 * @param array $config configuration (name=>value) to be applied as the initial property values of this class.
	 */
	public function __construct($modelClass,$config=array())
	{
		if(is_string($modelClass))
		{
			$this->modelClass=$modelClass;
			$this->model=OOActiveRecord::model($this->modelClass);
		}
		else if($modelClass instanceof OOActiveRecord)
		{
			$this->modelClass=get_class($modelClass);
			$this->model=$modelClass;
		}
		$this->setId($this->modelClass);

		foreach ($config as $key => $value) {
			$this->$key=$value;
		}
	}

	/**
	 * Returns the query criteria.
	 * @return CDbCriteria the query criteria
	 */
	public function getCriteria()
	{
		if($this->_criteria===null)
			$this->_criteria=new OOCriteria;
		return $this->_criteria;
	}

	/**
	 * Sets the query criteria.
	 * @param mixed $value the query criteria. This can be either a CDbCriteria object or an array
	 * representing the query criteria.
	 */
	public function setCriteria($value)
	{
		$this->_criteria = $value instanceof OOCriteria ? $value : new OOCriteria($value);
	}

	/**
	 * Returns the sorting object.
	 * @return CSort the sorting object. If this is false, it means the sorting is disabled.
	 */
	public function getSort()
	{
		/*
		if($this->_sort===null)
		{
			$this->_sort=new CSort;
			if(($id=$this->getId())!='') {
				$this->_sort->sortVar=$id.'_sort';
			}
		}
		return $this->_sort;

		if(($sort=parent::getSort())!==false)
			$sort->modelClass=$this->modelClass;
		return $sort;
		*/
		return false;
	}

	/**
	 * Fetches the data from the persistent data storage.
	 * @return array list of data items
	 */
	protected function fetchData()
	{
		$criteria=clone $this->getCriteria();

		if(($pagination=$this->getPagination())!==false)
		{
			$pagination->setItemCount($this->getTotalItemCount());
			$pagination->applyLimit($criteria);
		}

		$baseCriteria=$this->model->getOOCriteria(false);

		if (($sort = $this->getSort()) !== false) {
			// set model criteria so that CSort can use its table alias setting
			if($baseCriteria !== null){
				$c = clone $baseCriteria;
				$c->mergeWith($criteria);
				$this->model->setOOCriteria($c);
			} else{
				$this->model->setOOCriteria($criteria);
			}
			$sort->applyOrder($criteria);
		}

		$this->model->setOOCriteria($baseCriteria!==null ? clone $baseCriteria : null);
		$data=$this->model->findAll($criteria);
		$this->model->setOOCriteria($baseCriteria);  // restore original criteria
		return $data;
	}

	/**
	 * Fetches the data item keys from the persistent data storage.
	 * @return array list of data item keys.
	 */
	protected function fetchKeys()
	{
		$keys=array();
		foreach($this->getData() as $i=>$data)
		{
			$key=$this->keyAttribute===null ? $data->getPrimaryKey() : $data->{$this->keyAttribute};
			$keys[$i]=is_array($key) ? implode(',',$key) : $key;
		}
		return $keys;
	}

	/**
	 * Calculates the total number of data items.
	 * @return integer the total number of data items.
	 */
	protected function calculateTotalItemCount()
	{
		$baseCriteria=$this->model->getOOCriteria(false);
		if($baseCriteria!==null)
			$baseCriteria=clone $baseCriteria;
		$count=$this->model->count($this->getCriteria());
		$this->model->setOOCriteria($baseCriteria);
		return $count;
	}
}
