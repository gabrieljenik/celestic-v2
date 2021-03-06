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
	 * Especify access control rights
	 * @return array access rules
	 */
	public function accessRules()
	{
		return array(
			array('allow', 
				'actions'=>array(
					'rearrange',
					'view',
					'create',
					'update',
					'index'
				),
				'users'=>array('@'),
				'expression'=>'!$user->isGuest'
			),
			array('deny',
				'users'=>array('*')
			),
		);
	}

	/**
	 * [actionIndex description]
	 * @return [type] [description]
	 */
	public function actionIndex()
	{
		// check if user has permission to indexMilestones
		if (Yii::app()->user->checkAccess('indexMilestones'))
		{
			$criteria = new CDbCriteria();
			$criteria->compare('project_id', (int)Yii::app()->user->getState('project_selected'));
			$milestones = Milestones::model()->findAll($criteria);
			
			// set model attributes from milestones form
			if (Yii::app()->request->isPostRequest)
			{
				$milestone = array();
				foreach($milestones as $item)
				{
					array_push($milestone, array(
						'id'=>$item->milestone_id,
						'title'=>CHtml::encode($item->milestone_title),
						'description'=>nl2br(CHtml::decode($item->milestone_description)),
						'url'=>$this->createUrl('index', array('#'=>'/view/'.$item->milestone_id)),
						'countComments'=>Logs::getCountComments($this->module->id, $item->milestone_id),
						'userOwner'=>ucfirst(CHtml::encode($item->Users->completeName)),
						'userOwnerUrl'=>$this->createUrl('users/view', array('id'=>$item->Users->user_id)),
						'due_date_day'=>CHtml::encode(Yii::app()->dateFormatter->format('dd', $item->milestone_duedate)),
						'due_date_month'=>CHtml::encode(Yii::app()->dateFormatter->format('MMM', $item->milestone_duedate)),
						'due_date_dayWeek'=>CHtml::encode(Yii::app()->dateFormatter->format('EEE', $item->milestone_duedate)),
						'due_dateFormatted'=>CHtml::encode(Yii::app()->dateFormatter->format('dd.MM.yyyy', $item->milestone_duedate)),
						'completed'=>round($item->percent, 2)
					));
				}

				header('Content-type: application/json');
				echo CJSON::encode(array(
					'milestones'=>$milestone
				));
				Yii::app()->end();
			}
				
			$this->render('index', array(
				'model' => new Milestones,
				'users' => Projects::model()->findManagersByProject(Yii::app()->user->getState('project_selected')),
				'status'=>Status::model()->findAll(),
			));
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
		// check if user has permissions to createMilestones
		if (Yii::app()->user->checkAccess('createMilestones'))
		{
			// create Milestones object model
			$model = new Milestones;
			
			// find all project managers
			$Users = Projects::model()->findManagersByProject(Yii::app()->user->getState('project_selected'));

			// if Milestones form exist
			if (isset($_POST['Milestones']))
			{
				// set form elements to Milestones model attributes
				$model->attributes = $_POST['Milestones'];
				$model->project_id = Yii::app()->user->getState('project_selected');
				
				// validate milestone model
				if ($model->validate())
				{
					// find milestones dates
					$milestone_startdate = date("Ymd", strtotime($model->milestone_startdate));
					$milestone_duedate = date("Ymd", strtotime($model->milestone_duedate));
					
					// get project data
					$project = Projects::model()->findByPk($model->project_id);
					
					// find project dates
					$project_startDate = date("Ymd", strtotime($project->project_startDate));
					$project_endDate = date("Ymd", strtotime($project->project_endDate));

					// If milestone dates are not within project dates ERROR!!
					if (($milestone_startdate >= $project_startDate) && ($milestone_startdate <= $project_endDate))
					{
						if (($milestone_duedate <= $project_endDate) && ($milestone_duedate >= $project_startDate))
						{
							// validate and save
							if($model->save())
							{
								// save log
								$attributes = array(
									'log_date' => date("Y-m-d G:i:s"),
									'log_activity' => 'MilestoneCreated',
									'log_resourceid' => $model->primaryKey,
									'log_type' => Logs::LOG_CREATED,
									'user_id' => Yii::app()->user->id,
									'module_id' => $this->module->getName(),
									'project_id' => $model->project_id,
								);
								Logs::model()->saveLog($attributes);
								
								// notify to user that has a milestone to attend
								Yii::import('application.extensions.phpMailer.yiiPhpMailer');
								$mailer = new yiiPhpMailer;
								$subject = Yii::t('email','MilestoneAssigned')." :: ".$model->milestone_title;
								
								// user you will be notified
								$User = Users::model()->findByPk($model->user_id);
								$recipientsList = array(
									'name'=>$User->CompleteName,
									'email'=>$User->user_email,
								);
								
								// render template
								$str = $this->renderPartial('//templates/milestones/assigned',array(
									'milestone' => $model,
									'urlToMilestone' => Yii::app()->createAbsoluteUrl('milestones/view',array('id'=>$model->milestone_id)),
									'applicationName' => Yii::app()->name,
									'applicationUrl' => "http://".$_SERVER['SERVER_NAME'].Yii::app()->request->baseUrl,
								),true);
								//$mailer->pushMail($subject, $str, $recipientsList, Emails::PRIORITY_NORMAL);
								
								header('Content-type: application/json');
								echo CJSON::encode(array(
									'success'=>true,
									'milestones'=>array(
										'id'=>$model->milestone_id,
										'title'=>CHtml::encode($model->milestone_title),
										'description'=>nl2br(CHtml::decode($model->milestone_description)),
										'url'=>$this->createUrl('index', array('#'=>'/view/'.$model->milestone_id)),
										'countComments'=>Logs::getCountComments($this->module->id, $model->milestone_id),
										'userOwner'=>ucfirst(CHtml::encode($model->Users->completeName)),
										'userOwnerUrl'=>$this->createUrl('users/view', array('id'=>$model->Users->user_id)),
										'due_date'=>CHtml::encode($model->milestone_duedate),
										'due_dateFormatted'=>CHtml::encode(Yii::app()->dateFormatter->format('dd.MM.yyyy', $model->milestone_duedate)),
										'completed'=>round($model->percent, 2)
									)
								));
								Yii::app()->end();
							}
						}
						// error on milestone_duedate
						else
						{
							$model->addError('milestone_duedate', Yii::t('milestones','DueDateError'));
						}
					}
					// error on milestone_startdate
					else
					{
						$model->addError('milestone_startdate', Yii::t('milestones','StartDateError'));
					}
				}
				
				header('Content-type: application/json');
				echo CJSON::encode(array(
					'error'=>$model->getErrors()
				));
				Yii::app()->end();
			}

			$this->layout = false;
			$this->render('create',array(
				'model'=>$model,
				'users'=>$Users,
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
		// check if user has permission to viewMilestones
		if (Yii::app()->user->checkAccess('viewMilestones'))
		{
			$model = new Milestones();

			if (($_POST) && (Yii::app()->request->isPostRequest))
			{
				if (isset($_GET['id']))
				{
					$model = Milestones::model()->findByPk((int)Yii::app()->request->getParam('id', 0));
				}

				if($model === null)
				{
					throw new CHttpException(404, Yii::t('site', '404_Error'));
				}
				else 
				{
					// Tasks dataprovider
					$dataProviderTasks = Tasks::model()->with('UserReported','Status')->together()->findAll(array(
						'condition'=>'t.milestone_id = :milestone_id',
						'params'=>array(
							':milestone_id'=>$model->milestone_id,
						),
						'order'=>'t.status_id ASC, t.task_priority DESC'
					));

					$tasks = array();
					foreach ($dataProviderTasks as $data)
					{
						$class = '';
						switch ($data->task_priority) {
							case Tasks::PRIORITY_LOW:
								$class = 'label-info';
								break;
							case Tasks::PRIORITY_MEDIUM:
								$class = 'label-warning';
								break;
							case Tasks::PRIORITY_HIGH:
								$class = 'label-important';
								break;
							default:
								$class = 'label-info';
								break;
						}

						$tasks[] = array(
							'status'=>$data->Status->status_name,
							'class_status'=>'label-'.strtolower(str_replace(" ", "", $data->Status->status_name)),
							'taskTypes_id'=>$data->taskTypes_id,
							'task_startDate'=>Yii::app()->dateFormatter->format('MMMM d, yyy', strtotime($data->task_startDate)),
							'task_endDate'=>$data->task_endDate,
							'task_id'=>$data->task_id,
							'task_url'=>$this->createUrl('/tasks/view', array('id'=>$data->task_id)),
							'task_name'=>$data->task_name,
							'task_priority'=>Tasks::getNameOfTaskPriority($data->task_priority),
							'task_priority_class'=>$class,
							'user'=>$data->UserReported->CompleteName
						);
					}

					// finding by status
					$criteria = new CDbCriteria;
					$criteria->select = "count(t.status_id) as total";
					$criteria->condition = "t.milestone_id = :milestone_id";
					$criteria->params = array(
						':milestone_id' => (int)Yii::app()->request->getParam('id', 0),
					);			
					$criteria->group = "t.status_id";
					$foundTasksStatus = Tasks::model()->with('Status')->together()->findAll($criteria);

					$TasksStatus = array();
					foreach ($foundTasksStatus as $task)
					{
						$TasksStatus[] = array(
							'name' => $task->Status->status_name,
							'data' => intval($task->total),
						);
					}

					// finding by priority
					$criteria = new CDbCriteria;
					$criteria->select = "t.task_priority, count(t.task_priority) as total";
					$criteria->condition = "t.milestone_id = :milestone_id";
					$criteria->params = array(
						':milestone_id' => (int)Yii::app()->request->getParam('id', 0),
					);			
					$criteria->group = "t.task_priority";
					$foundTasksPriority = Tasks::model()->findAll($criteria);
					$TasksPriority = array();
					foreach ($foundTasksPriority as $task)
					{
						$TasksPriority[] = array(Tasks::getNameOfTaskPriority($task->task_priority), intval($task->total));
					}

					header('Content-type: application/json');
					echo CJSON::encode(array(
						'milestone'=>array(
							'title'=>$model->milestone_title,
							'url'=>$this->createUrl('update', array('id'=>$model->milestone_id)),
							'description'=>nl2br(CHtml::encode($model->milestone_description)),
							'duedate'=>Yii::app()->dateFormatter->formatDateTime($model->milestone_duedate, 'medium', false),
							'start_date'=>$model->milestone_startdate,
							'due_date'=>$model->milestone_duedate,
							'owner'=>$model->Users->completeName,
							'ownerId'=>$model->user_id,
							'ownerUrl'=>$this->createUrl("users/view",array("id"=>$model->user_id)),
							'completed'=>round(Milestones::model()->getMilestonePercent($model->milestone_id),2),
							'isManager'=>Yii::app()->user->IsManager || Yii::app()->user->isOwner,
							'dataProviderTasks'=>$tasks,
							'TasksStatus'=>$TasksStatus,
							'TasksPriority'=>$TasksPriority
						)
					));
					Yii::app()->end();
				}
			}

			$this->layout = false;
			$this->render('view', array(
				'status'=>Status::model()->findAll(),
				'model'=>$model,
				'users'=>Projects::model()->findManagersByProject(Yii::app()->user->getState('project_selected'))
			));
		}
		else
		{
			throw new CHttpException(403, Yii::t('site', '403_Error'));
		}
	}

	/**
	 * Updates a particular model.
	 * @return update view
	 */
	public function actionUpdate()
	{
		// check if user has permissions to updateMilestones
		if (Yii::app()->user->checkAccess('updateMilestones'))
		{
			// get Milestones object from $_GET['id'] parameter
			$model = Milestones::model()->findByPk((int)Yii::app()->request->getParam('id', 0));

			// only managers can update budgets
			if (Yii::app()->user->IsManager)
			{
				// find all projects managers
				$Users = Projects::model()->findManagersByProject($model->project_id);

				if (isset($_POST['Milestones']))
				{
					// if Milestones form exist
					$model->attributes=$_POST['Milestones'];

					// validate and save
					if($model->save())
					{
						// save log
						$attributes = array(
							'log_date' => date("Y-m-d G:i:s"),
							'log_activity' => 'MilestoneUpdated',
							'log_resourceid' => $model->milestone_id,
							'log_type' => 'updated',
							'user_id' => Yii::app()->user->id,
							'module_id' => $this->module->getName(),
							'project_id' => $model->project_id,
						);
						Logs::model()->saveLog($attributes);

						header('Content-type: application/json');
						echo CJSON::encode(array(
							'success'=>true,
							'milestone'=>array(
								'id'=>$model->milestone_id,
								'title'=>$model->milestone_title,
								'description'=>$model->milestone_description,
								'start_date'=>$model->milestone_startdate,
								'due_date'=>$model->milestone_duedate,
								'duedate'=>Yii::app()->dateFormatter->formatDateTime($model->milestone_duedate, 'medium', false),
								'owner'=>$model->Users->completeName,
								'ownerId'=>$model->user_id,
								'isManager'=>Yii::app()->user->IsManager || Yii::app()->user->isOwner
							)
						));
						Yii::app()->end();
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
				throw new CHttpException(403, Yii::t('site', '403_Error'));
			}
		}
		else
		{
			throw new CHttpException(403, Yii::t('site', '403_Error'));
		}
	}
}