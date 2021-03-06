<?php
/**
 * DefaultController class file
 * 
 * @author		Jackfiallos
 * @version		2.0.0
 * @link		http://qbit.com.mx/labs/celestic
 * @copyright 	Copyright (c) 2009-2013 Qbit Mexhico
 * @license		http://qbit.com.mx/labs/celestic/license/
 * @description
 *
 **/
class DefaultController extends Controller
{
	/**
	 * @var CActiveRecord the currently loaded data model instance.
	 */
	private $_model;
	
	/**
	 * @var resourceFolder saved inside all uploaded images
	 */
	const FOLDERIMAGES = 'resources/';
	
	/**
	 * @var string temporal filename
	 */
	private $tmpFileName = '';

	/**
	 * [filters description]
	 * @return [type] [description]
	 */
	public function filters()
	{
		return array(
			'accessControl', // perform access control for CRUD operations
			array(
				'application.filters.YXssFilter',
				'clean'   => '*',
				'tags'    => 'strict',
				'actions' => 'all'
			)
		);
	}

	/**
	 * [accessRules description]
	 * @return [type] [description]
	 */
	public function accessRules()
	{
		return array(
			array('allow', 
				'actions'=>array(
					'index',
					'view',
					'create'
				),
				'users'=>array('@'),
				'expression'=>'!$user->isGuest'
			),
			array('deny',
				'users'=>array('*')
			)
		);
	}

	/**
	 * [actionIndex description]
	 * @return [type] [description]
	 */
	public function actionIndex()
	{
		// verify if user has permissions to indexDownloads
		if (Yii::app()->user->checkAccess('indexDownloads'))
		{			
			if (Yii::app()->request->isPostRequest)
			{
				$criteria = new CDbCriteria();
				$criteria->compare('project_id', (int)Yii::app()->user->getState('project_selected'));
				$documents = Documents::model()->findAll($criteria);
				$document = array();

				foreach ($documents as $item)
				{
					$timestamp = strtotime($item->document_uploadDate);

					array_push($document, array(
						'id'=>$item->document_id,
						'name'=>CHtml::encode($item->document_name),
						'description'=>CHtml::encode($item->document_description),
						'url'=>$this->createUrl('index', array('#'=>'/view/'.$item->document_id)),
						'downloadLink'=>$this->createUrl('download', array('id'=>$item->document_id)),
						'imageType'=>Yii::app()->theme->baseUrl.'/images/icons/'.strtolower(substr(strrchr($item->document_path,'.'),1)).'.png',
						'userName'=>ucfirst(CHtml::encode($item->User->completeName)),
						'userUrl'=>$this->createUrl('users/view', array('id'=>$item->User->user_id)),
						'timestamp'=>Yii::app()->dateFormatter->format('MMMM d, yyy', $timestamp),
						'countComments'=>Logs::getCountComments('documents', $item->document_id)
					));
				}

				$labels = array(
					'downloadLabel'=>Yii::t("documents","downloadFile"),
					'orLabel'=>Yii::t('site','or'),
					'viewDetailsLabel'=>Yii::t('documents','ViewDetails')
				);
				
				header('Content-type: application/json');
				echo CJSON::encode(array(
					'documents'=>$document,
					'labels'=>$labels
				));
				Yii::app()->end();
			}
			
			$this->render('index', array(
				'model'=>new Documents
			));
		}
		else
		{
			throw new CHttpException(403, Yii::t('site', '403_Error'));
		}
	}

	/**
	 * [actionView description]
	 * @return [type] [description]
	 */
	public function actionView()
	{
		// verify if user has permissions to viewDownloads
		if (Yii::app()->user->checkAccess('viewDownloads'))
		{
			if (($_POST) && (Yii::app()->request->isPostRequest))
			{
				$model = null;

				if(isset($_GET['id']))
				{
					$model = Documents::model()->with('Projects', 'User')->together()->findbyPk((int)$_GET['id']);
				}

				if($model === null)
				{
					throw new CHttpException(404, Yii::t('site', '404_Error'));
				}
				else 
				{					
					header('Content-type: application/json');
					echo CJSON::encode(array(
						'name' => $model->document_name,
						'description' => $model->document_description,
						'uploadDate' => Yii::app()->dateFormatter->format('MMMM d, yyy', $model->document_uploadDate),
						'download' => $this->createUrl('download', array('id'=>$model->document_id)),
						'userName'=>ucfirst(CHtml::encode($model->User->completeName)),
						'userUrl'=>$this->createUrl('users/view', array('id'=>$model->User->user_id)),
						'countComments'=>Logs::getCountComments('documents', $model->document_id),
						'imageType'=>Yii::app()->theme->baseUrl.'/images/icons/'.strtolower(substr(strrchr($model->document_path,'.'),1)).'.png',
					));
					Yii::app()->end();
				}
			}

			$this->layout = false;
			$this->render('view');
		}
		else
		{
			throw new CHttpException(403, Yii::t('site', '403_Error'));
		}
	}

	/**
	 * [actionCreate description]
	 * @return [type] [description]
	 */
	public function actionCreate()
	{
		// verify if user has permissions to createDownloads
		if (Yii::app()->user->checkAccess('createDownloads'))
		{
			// create object model Documents
			$model=new Documents;
			
			// verify _POST['Documents'] exist
			if (isset($_POST['Documents']))
			{
				// set form elements to Documents model attributes
				$model->attributes=$_POST['Documents'];
				$model->project_id = Yii::app()->user->getState('project_selected');
				$model->user_id = Yii::app()->user->id;

				$extension = null;
				
				// verify file upload exist
				if ((isset($_FILES['Documents']['name']['image'])) && (!empty($_FILES['Documents']['name']['image'])))
				{
					// create an instance of uploaded file
					$model->image = CUploadedFile::getInstance($model,'image');
					if (!$model->image->getError())
					{
						// name is formed by date(day+month+year+hour+minutes+seconds+dayofyear+microtime())
						$this->tmpFileName = str_replace(" ","",date('dmYHis-z-').microtime());
						// get the file extension
						$extension=$model->image->getExtensionName();
						if ($model->image->saveAs($this::FOLDERIMAGES.$this->tmpFileName.'.'.$extension))
						{
							// set other attributes
							$model->document_name = $model->image->getName();
							$model->document_path = $this::FOLDERIMAGES.$this->tmpFileName.'.'.$extension;
							$model->document_revision = '1';
							$model->document_uploadDate = date("Y-m-d");
							$model->document_type = $model->image->getType();
							$model->document_baseRevision = date('dmYHis');
							$model->user_id = Yii::app()->user->id;
						}
					}
					else
					{
						$model->addError('image',$model->image->getError());
					}
				}

				if (!$model->validate())
				{
					if (file_exists($this::FOLDERIMAGES.$this->tmpFileName.'.'.$extension)) 
					{
						if (!is_dir($this::FOLDERIMAGES.$this->tmpFileName.'.'.$extension)) 
						{
							unlink($this::FOLDERIMAGES.$this->tmpFileName.'.'.$extension);
						}
					}

					header('Content-type: application/json');
					echo CJSON::encode(array(
						'error'=>json_decode(CActiveForm::validate($model))
					));
					Yii::app()->end();
				}
				else 
				{
					// validate and save
					if ($model->save())
					{
						// save log
						$attributes = array(
							'log_date' => date("Y-m-d G:i:s"),
							'log_activity' => 'DocumentCreated',
							'log_resourceid' => $model->primaryKey,
							'log_type' => Logs::LOG_CREATED,
							'user_id' => Yii::app()->user->id,
							'module_id' => $this->module->getName(),
							'project_id' => $model->project_id,
						);
						Logs::model()->saveLog($attributes);
						
						// create email object
						Yii::import('application.extensions.phpMailer.yiiPhpMailer');
						$mailer = new yiiPhpMailer;
						$subject = Yii::t('email','newDocumentUpload')." - ".$model->document_name;

						// find users managers to send email
						$Users = Projects::model()->findManagersByProject($model->project_id);
						
						// create array of users destinations
						$recipientsList = array();
						foreach ($Users as $client)
						{			
							$recipientsList[] = array(
								'name'=>$client->CompleteName,
								'email'=>$client->user_email,
							);				
						}
						
						// set layout then send
						$str = $this->renderPartial('//templates/documents/newUpload', array(
							'document' => $model,
							'urlToDocument' => "http://".$_SERVER['SERVER_NAME'].Yii::app()->createUrl('documents/view',array('id'=>$model->document_id)),
							'applicationName' => Yii::app()->name,
							'applicationUrl' => "http://".$_SERVER['SERVER_NAME'].Yii::app()->request->baseUrl,
						), true);
						
						$mailer->pushMail($subject, $str, $recipientsList, Emails::PRIORITY_NORMAL);

						$document = array(
							'id'=>$model->document_id,
							'name'=>$model->document_name,
							'description'=>$model->document_description,
							'url'=>$this->createUrl('index', array('#'=>'/view/'.$model->document_id)),
							'downloadLink'=>$this->createUrl('download', array('id'=>$model->document_id)),
							'imageType'=>Yii::app()->theme->baseUrl.'/images/icons/'.strtolower(substr(strrchr($model->document_path,'.'),1)).'.png',
							'userName'=>ucfirst(CHtml::encode($model->User->user_name)),
							'userUrl'=>$this->createUrl('users/view', array('id'=>$model->User->user_id)),
							'timestamp'=>Yii::app()->dateFormatter->format('MMMM d, yyy', strtotime($model->document_uploadDate))
						);
						
						header('Content-type: application/json');
						echo CJSON::encode(array(
							'success'=>true,
							'document'=>$document
						));
						Yii::app()->end();
					}
					else
					{
						header('Content-type: application/json');
						echo CJSON::encode(array(
							'error'=>$model->getErrors()
						));
						Yii::app()->end();
					}
				}
			}
			
			$this->layout = false;
			$this->render('create',array(
				'model'=>$model,
			));
		}
		else
		{
			throw new CHttpException(403, Yii::t('site', '403_Error'));
		}
	}
}