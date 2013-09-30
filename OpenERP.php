<?php

set_include_path(get_include_path() . PATH_SEPARATOR . dirname(__FILE__) . '/lib');

require_once 'Zend/XmlRpc/Client.php';

class OpenERP extends CApplicationComponent
{
	public $server;
	public $database;
	public $modelsPath;
	public $publicUser;
	public $publicPassword;
	public $publicUserId;

	public function init()
	{
		parent::init();
		if (Yii::app()->session['openerp_user_id'] === null) {
			if ($this->publicUser == null || $this->publicPassword == null) {
				throw new CException('Non Login openerp api request, '
									.'please set $publicUser and '
									.'$publicPassword in WebApplication');
			}

			$this->publicUserId = $this->getClientCommon()
										->login($this->database,
												$this->publicUser,
												$this->publicPassword);

			//var_dump($this);
			//die();
		}
	}

	public function getModelsFullPath()
	{
		return Yii::getPathOfAlias($this->modelsPath);
	}

	public function getUserId()
	{
		if (Yii::app()->session['openerp_user_id'] === null) {
			return $this->publicUserId;
		}

		return Yii::app()->session['openerp_user_id'];
	}

	public function getUser()
	{
		if (Yii::app()->session['openerp_user'] === null) {
			return $this->publicUser;
		}

		return Yii::app()->session['openerp_user'];
	}

	public function getPassword()
	{
		if (Yii::app()->session['openerp_password'] === null) {
			return $this->publicPassword;
		}
		return Yii::app()->session['openerp_password'];
	}

	private $_clientCommon;
	public function getClientCommon()
	{
		if ($this->_clientCommon == null){
			$conn = new Zend_XmlRpc_Client($this->server . '/common');
			$this->_clientCommon = $conn->getProxy();
		}
		return $this->_clientCommon;
	}

	private $_client;
	public function getClient()
	{
		if ($this->_client == null){
			$conn = new Zend_XmlRpc_Client($this->server . '/object');
			$this->_client = $conn->getProxy();
		}
		return $this->_client;
	}

	/**
	 * @return new id data
	 */
	public function create($model, array $values, $context=null)
	{
		return $this->getClient()->execute($this->database,
				$this->getUserId(), $this->password, $model,
				'create', $ids, $values, $context);
	}

	/**
	 * @return array id data
	 */
	public function search($model, array $args = array(), $offset=0,
							$limit=null, $order=null,
							array $context=null, $count=false)
	{
		return $this->getClient()->execute($this->database,
				$this->getUserId(), $this->password, $model,
				'search', $args, $offset, $limit, $order,
				$context, $count);
	}
	/**
	 * return array
	 */
	public function read($model, array $ids, array $fields=null, $context=null)
	{
		return $this->getClient()->execute($this->database,
				$this->getUserId(), $this->password, $model,
				'read', $ids, $fields, $context);
	}

	/**
	 * @return ..
	 */
	public function write($model, array $ids, array $values, $context=null)
	{
		return $this->getClient()->execute($this->database,
				$this->getUserId(), $this->password, $model,
				'write', $ids, $values, $context);
	}

	/**
	 * @return ..
	 */
	public function unlink($model, array $ids, $context=null)
	{
		return $this->getClient()->execute($this->database,
				$this->getUserId(), $this->password, $model,
				'unlink', $ids, $context);
	}

	private $_metaData;
	public function getMetaData($model)
	{
		if (!isset($this->_metaData[$model])) {
			$ids =  $this->search('ir.model.fields', array(
				array('model','=',$model),
			));
			$list_field = $this->read('ir.model.fields', $ids,
					array('name', 'ttype','relation','relation_field',
						'field_description','required'));
			$fields = array();
			foreach($list_field as $afield){
				$fields[$afield['name']] = array(
					'required' => $afield['required'],
					'type' => $afield['ttype'],
					'label' => $afield['field_description'],
					'relationModel'=> $afield['relation'],
					'foreignKeyField' => $afield['relation_field'],
				);
			}
			$this->_metaData[$model] = $fields;
		}
		return $this->_metaData[$model];
	}

	public function query($model, $criteria, $count=false)
	{
		$ids = $this->search($model, $criteria->domain,
							$criteria->offset, $criteria->limit,
							$criteria->order, $criteria->context, $count);
		if (!is_array($ids)) {
			return $ids;
		}
		return $this->read($model, $ids, $criteria->fields,
							$criteria->context);
	}

}
