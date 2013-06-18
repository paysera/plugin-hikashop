<?php
	defined('_JEXEC') or die('Restricted access');
?>
<tr>
	<td class="key">
		<label for="data[payment][payment_params][order_status]">
			<?php echo JText::_( 'ORDER_STATUS' ); ?>
		</label>
	</td>
	<td>
		<?php echo $this->data['category']->display("data[payment][payment_params][order_status]",@$this->element->payment_params->order_status); ?>
	</td>
</tr>
<tr>
	<td class="key">
		<label for="data[payment][payment_params][project_id]">
			<?php echo JText::_( 'Project ID' ); ?>
		</label>
	</td>
	<td>
		<input type="text" name="data[payment][payment_params][project_id]" value="<?php echo @$this->element->payment_params->project_id; ?>" />
	</td>
</tr>
<tr>
	<td class="key">
		<label for="data[payment][payment_params][project_pass]">
			<?php echo JText::_( 'Project password' ); ?>
		</label>
	</td>
	<td>
		<input type="text" name="data[payment][payment_params][project_pass]" value="<?php echo @$this->element->payment_params->project_pass; ?>" />
	</td>
</tr>
<tr>
	<td class="key">
		<label for="data[payment][payment_params][test_mode]">
			<?php echo JText::_( 'Enable test mode?' ); ?>
		</label>
	</td>
	<td>
		<?php echo JHTML::_('select.booleanlist', "data[payment][payment_params][test_mode]" , '',@$this->element->payment_params->test_mode	); ?>
	</td>
</tr>