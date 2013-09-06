<div class="form">

<?php $form=$this->beginWidget('CActiveForm', array(
	'id'=>'documents-form',
	'action'=>$this->createUrl('create'),
	'htmlOptions'=>array(
		'name'=>'documents-form',
		'enctype'=>'multipart/form-data',
		'class'=>'vertical-horizontal',
		'ng-submit'=>'submitForm()',
		'onsubmit'=>'return false'
	),
	'enableAjaxValidation'=>false
)); ?>

	<div class="alert alert-info">
  		<h4>Atenci&oacute;n!</h4>
  		<?php echo Yii::t('documents','FieldsRequired'); ?>
	</div>

	<?php echo $form->errorSummary($model, null, null, array('class'=>'errorSummary stick'))."<br />"; ?>
	
	<div class="row-fluid">
		<div class="span6">
			<div class="control-group">
    			<?php echo $form->labelEx($model,'document_name'); ?>
    			<div class="controls">
      				<?php echo $form->textField($model, 'document_name', array('class'=>'betterform', 'style'=>'width:95%', 'maxlength'=>45, 'tabindex'=>1)); ?>
      				<span class="help-inline">
      					<?php echo CHtml::label(Yii::t('documents','FormDocumentName'), CHtml::activeId($model, 'document_name'), array('class'=>'labelhelper')); ?>
					</span>
    			</div>
  			</div>
		</div>
		<div class="span6">
			<div class="control-group">
				<?php echo $form->labelEx($model,'image'); ?>
				<div class="controls">
					<?php echo CHtml::activeFileField($model, 'image', array('tabindex'=>2)); ?>
					<div class="help-inline">
						<?php echo CHtml::label(Yii::t('documents','FormDocumentImage'), CHtml::activeId($model, 'image'), array('class'=>'labelhelper','style'=>'width:95%')); ?>
					</div>
				</div>
			</div>
		</div>
	</div>	
	
	<div class="row-fluid">
		<div class="control-group">
			<?php echo $form->labelEx($model,'document_description'); ?>
			<div class="controls">
				<?php echo $form->textArea($model,'document_description',array('style'=>'width:100%', 'tabindex'=>3)); ?>
				<div class="help-inline">
					<?php echo CHtml::label(Yii::t('documents','FormDocumentDescription'), CHtml::activeId($model, 'document_description'), array('class'=>'labelhelper')); ?>
				</div>
			</div>
		</div>

		<div class="form-actions row">
			<div class="span6">
				<?php echo CHtml::button($model->isNewRecord ? Yii::t('site','create') : Yii::t('site','save'), array('type'=>'submit', 'class'=>'btn btn-primary', 'tabindex'=>4)); ?>
				<?php echo CHtml::button(Yii::t('site','reset'), array('type'=>'reset', 'class'=>'btn', 'tabindex'=>5)); ?>
			</div>
			<div class="span6">
				<?php echo CHtml::link(Yii::t('site','return'), $this->createUrl('index', array('#'=>'/home')), array('class'=>'pull-right button', 'ng-click'=>'showHome()')); ?>
			</div>
		</div>
	</div>
<?php $this->endWidget(); ?>
</div>