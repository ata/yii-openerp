<?php

/**
 * UserIdentity represents the data needed to identity a user.
 * It contains the authentication method that checks if the provided
 * data can identity the user.
 */
class OOUserIdentity extends CUserIdentity
{
	/**
	 * Authenticates a user.
	 * The example implementation makes sure if the username and password
	 * are both 'demo'.
	 * In practical applications, this should be changed to authenticate
	 * against some persistent user identity storage (e.g. database).
	 * @return boolean whether authentication succeeds.
	 */
	public function authenticate()
	{
		$uid = Yii::app()->openerp->getClientCommon()->login(Yii::app()->openerp->database,
					$this->username, $this->password);

		if ($uid !== false) {
			Yii::app()->session['openerp_user_id'] = $uid;
			Yii::app()->session['openerp_user'] = $this->username;
			Yii::app()->session['openerp_password'] = $this->password;
			$this->errorCode=self::ERROR_NONE;
			return true;
		}
		return false;
	}
}
