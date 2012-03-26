<?php

abstract class OOActiveRecord extends CModel
{

	private static $_models = array(); // class name => model
	private static $_mappings = array();

	private $_md;								// meta data
	private $_new=false;						// whether this instance is new or not
	private $_attributes=array();				// attribute name => attribute value
	private $_related=array();					// attribute name => related objects
	private $_c;								// query criteria (used by finder only)
	private $_pk;								// old primary key value
	private $_alias='t';						// the table alias being used for query

	public $isComplete = true;
	public $relations;
	public $objectToStringField = 'name';

	public function __construct($scenario='insert')
	{
		if ($scenario == null) {// internally used by populateRecord() and model()
			return;
		}
		$this->setScenario($scenario);
		$this->setIsNewRecord(true);
		$this->init();
		$this->attachBehaviors($this->behaviors());
		$this->afterConstruct();
	}

	public function __get($name)
	{

		if(!$this->isComplete && $name !== $this->objectToStringField) {
			$this->makeComplete();
		}
		if (isset($this->_attributes[$name])) {
			return $this->_attributes[$name];
		} else if (isset($this->getMetaData()->fields[$name])){
			return null;
		} else if (isset($this->_related[$name])){
			return $this->_related[$name];
		} else if (isset($this->getMetaData()->relations[$name])) {
			return $this->getRelated($name);
		} else {
			return parent::__get($name);
		}
	}

	public function __set($name, $value)
	{
		if($this->setAttribute($name, $value) === false){
			if(isset($this->getMetaData()->relations[$name])) {
				$this->relations[$name] = $value;
			} else {
				parent::__set($name,$value);
			}
		}
	}

	public function makeComplete()
	{
		$data = Yii::app()->openerp->read($this->modelName(), array($this->_attributes['id']));
		$md = $this->getMetaData();
		foreach ($data[0] as $name => $value) {
			if(property_exists($this, $name)) {
				$this->$name = $value;
			} else if(isset($md->fields[$name])) {
				$this->_attributes[$name] = $value;
			} else if(isset($md->relations[$name])) {
				$this->relations[$name] = $value;
			}
		}
		$this->isComplete = true;
	}

	public function __toString()
	{
		return "{$this->{$this->objectToStringField}}";
	}

	public function init()
	{

	}

	private static function updateMapping($modelsPath)
	{
		$files = scandir($modelsPath);
		foreach ($files as $file) {
			if ((substr($file, -4) === '.php')) {
				$class = str_replace('.php','',$file);
				$reflection = new ReflectionClass($class);
				$parent = $reflection->getParentClass()->name;
				$parent2 = $reflection->getParentClass()->getParentClass()->name;
				if(in_array('OOActiveRecord',compact('parent','parent2'))) {
					self::$_mappings[self::model($class)->modelName()] = $class;
				}
			}
		}
	}

	/**
	 * return class from model
	 */
	public static function getClassName($model)
	{
		if (empty(self::$_mappings)) {
			$fileMapping = Yii::app()->runtimePath . '/openerp/mapping.php';
			$modelsPath = Yii::app()->getComponent('openerp')->getModelsFullPath();
			if (!file_exists($fileMapping)) {
				mkdir(Yii::app()->runtimePath . '/openerp',0700);
				self::updateMapping($modelsPath);
				file_put_contents($fileMapping, serialize(self::$_mappings));
			} else {
				$fileMappingDate = date("F d Y H:i:s.", filemtime($fileMapping));
				$modelsPathDate = date("F d Y H:i:s.", filemtime($modelsPath));
				if ($modelsPathDate > $fileMappingDate) {
					self::updateMapping($modelsPath);
					file_put_contents($fileMapping, serialize(self::$_mappings));
				} else {
					self::$_mappings = unserialize(file_get_contents($fileMapping));
				}

			}
		}

		return self::$_mappings[$model];
	}


	public function getRelated($name, $complete=false)
	{
		if (!isset($this->_related[$name])) {
			$md = $this->getMetaData();
			if ($md->relations[$name]['type'] === 'many2one') {
				if ($complete) {
					$listData = $this->getOpenerp()->read(
							$md->relations[$name]['relationModel'],
							array($this->relations[$name][0]));
					$this->_related[$name] = self::model(self::getClassName(
							$md->relations[$name]['relationModel']))
								->populateRecord($listData[0]);
				} else {
					$this->_related[$name] = self::model(self::getClassName(
							$md->relations[$name]['relationModel']))
								->populateRecord(array(
									'id' => $this->relations[$name][0],
									$this->objectToStringField => $this->relations[$name][1],
								));
					$this->_related[$name]->isComplete = false;
				}
			} else {
				$listData = $this->getOpenerp()->read(
						$md->relations[$name]['relationModel'],
						$this->relations[$name]);
				$this->_related[$name] = self::model(self::getClassName($md->relations[$name]['relationModel']))->populateRecords($listData);
			}
		}
		return $this->_related[$name];
	}

	public function getIsNewRecord()
	{
		return $this->_new;
	}

	public function setIsNewRecord($value)
	{
		$this->_new=$value;
	}

	public function setAttribute($name, $value)
	{
		if(property_exists($this,$name)) {
			$this->$name=$value;
		} else if(isset($this->getMetaData()->fields[$name])) {
			$this->_attributes[$name]=$value;
		} else {
			return false;
		}
		return true;
	}

	public function getPrimaryKey()
	{
		return $this->id;
	}

	public function getMetaData()
	{
		if ($this->_md !== null) {
			return $this->_md;
		} else {
			return $this->_md=self::model(get_class($this))->_md;
		}
	}

	public function rules()
	{
		return array(
			array(implode(',', $this->getMetaData()->requiredFields),'required'),
			array(implode(',', $this->getMetaData()->numericalFields),'numerical'),
			array(implode(',', $this->attributeNames()), 'safe', 'on' => 'search'),
		);
	}

	public function attributeLabels()
	{
		return $this->getMetaData()->attributeLabels;
	}

	public function attributeNames()
	{
		return array_keys($this->getMetaData()->fields);
	}


	public function getOpenerp()
	{
		return Yii::app()->getComponent('openerp');
	}



	protected function instantiate()
	{
		$class=get_class($this);
		$model=new $class(null);
		return $model;
	}


	public function populateRecord(array $attributes, $callAfterFind=true)
	{
		$record=$this->instantiate($attributes);
		$record->setScenario('update');
		$record->init();
		$md = $record->getMetaData();
		foreach ($attributes as $name => $value) {
			if(property_exists($record, $name)) {
				$record->$name = $value;
			} else if(isset($md->fields[$name])) {
				$record->_attributes[$name] = $value;
			} else if(isset($md->relations[$name])) {
				$record->relations[$name] = $value;
			}
		}
		$record->_pk = $record->getPrimaryKey();
		$record->attachBehaviors($record->behaviors());
		if ($callAfterFind) {
			$record->afterFind();
		}
		return $record;
	}

	public function populateRecords($data, $callAfterFind=true, $index=null)
	{
		$records=array();
		foreach($data as $attributes)
		{
			if(($record=$this->populateRecord($attributes,$callAfterFind))!==null)
			{
				if($index === null) {
					$records[] = $record;
				} else {
					$records[$record->$index]=$record;
				}
			}
		}
		return $records;
	}

	protected function query($criteria, $all=false)
	{
		if (!$all) {
			$criteria->limit = 1;
		}
		$listData = $this->getOpenerp()->query($this->modelName(), $criteria);
		return $all ? $this->populateRecords($listData, true, $criteria->index) : $this->populateRecord($listData[0]);
	}

	public function findAll($value)
	{
		$criteria = is_array($value) ? OOCriteria($value): $value;
		return $this->query($criteria, true);
	}

	public function find($criteria)
	{
		$criteria = is_array($value) ? OOCriteria($value): $value;
		return $this->query($criteria);
	}

	public function count($value)
	{
		$criteria = is_array($value) ? OOCriteria($value): $value;
		return $this->getOpenerp()->query($this->modelName(), $criteria, true);
	}

	public function findByPk($pk)
	{
		$data = $this->getOpenerp()->read($this->modelName(), array($pk));
		return $this->populateRecord($data[0]);
	}

	public static function model($className=__CLASS__)
	{
		if(isset(self::$_models[$className]))
			return self::$_models[$className];
		else
		{
			$model=self::$_models[$className]=new $className(null);
			$model->_md=new OOActiveRecordMetaData($model);
			$model->attachBehaviors($model->behaviors());
			return $model;
		}
	}

	public function defaultScope()
	{
		return array();
	}

	public function getOOCriteria($createIfNull=true)
	{
		if ($this->_c===null) {
			if (($c = $this->defaultScope()) !== array() || $createIfNull) {
				$this->_c = new OOCriteria($c);
			}
		}
		return $this->_c;
	}

	public function setOOCriteria($criteria)
	{
		$this->_c=$criteria;
	}

	public function onAfterFind($event)
	{
		$this->raiseEvent('onAfterFind',$event);
	}

	protected function afterFind()
	{
		if ($this->hasEventHandler('onAfterFind')) {
			$this->onAfterFind(new CEvent($this));
		}
	}

	abstract public function modelName();
}


class OOActiveRecordMetaData
{
	public $fields = array();
	public $attributeLabels = array();
	public $relations = array();
	public $requiredFields = array();
	public $numericalFields = array();

	public function __construct($model)
	{
		$metadatas = $model->getOpenerp()->getMetaData($model->modelName());
		$metadatas['id'] = array(
			'required' => true,
			'type' => 'integer',
			'label' => 'Id',
			'relationModel' => '',
			'foreignKeyField' => '',
		);
		foreach($metadatas as $name => $md) {
			if ($md['required']) {
				$this->requiredFields[] = $name;
			}
			if (in_array($md['type'], array('integer','float','many2one'))) {
				$this->numericalFields[] = $name;
			}
			if (in_array($md['type'], array('many2one','one2many','many2many'))) {
				$this->relations[$name] = $md;
			} else {
				$this->fields[$name] = $md;
			}
			$this->attributeLabels[$name] = $md['label'];
		}

	}

}
