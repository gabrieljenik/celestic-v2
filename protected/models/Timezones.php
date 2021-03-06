<?php

/**
 * Timezones Model
 * 
 * @author		Jackfiallos
 * @version		2.0.0
 * @link		http://qbit.com.mx/labs/celestic
 * @copyright 	Copyright (c) 2009-2013 Qbit Mexhico
 * @license		http://qbit.com.mx/labs/celestic/license/
 * @description
 * 
 * This is the model class for table "tb_timezones".
 *
 * The followings are the available columns in table 'tb_timezones':
 * @property integer $timezone_id
 * @property string $timezone_name
 */
class Timezones extends CActiveRecord
{
	/**
	 * [model description]
	 * @param  [type] $className [description]
	 * @return [type]            [description]
	 */
	public static function model($className=__CLASS__)
	{
		return parent::model($className);
	}

	/**
	 * [tableName description]
	 * @return [type] [description]
	 */
	public function tableName()
	{
		return 'tb_timezones';
	}

	/**
	 * [rules description]
	 * @return [type] [description]
	 */
	public function rules()
	{
		return array(
			array('timezone_name', 'length', 'max'=>45)
		);
	}

	/**
	 * [relations description]
	 * @return [type] [description]
	 */
	public function relations()
	{
		return array(
			'Accounts'=>array(self::HAS_MANY, 'Accounts', 'timezone_id')
		);
	}

	/**
	 * [attributeLabels description]
	 * @return [type] [description]
	 */
	public function attributeLabels()
	{
		return array(
			'timezone_id' => 'Timezone',
			'timezone_name' => 'Timezone Name'
		);
	}
	
	/**
	 * [getTimezoneSelected description]
	 * @param  [type] $account_id [description]
	 * @return [type]             [description]
	 */
	public static function getTimezoneSelected($account_id)
	{
		$criteria = new CDbCriteria;
		$criteria->condition = 'Accounts.account_id = 1';
		$criteria->params = array(
			':account_id'=>$account_id
		);
		
		return Timezones::model()->with('Accounts')->together()->find($criteria);
	}
}