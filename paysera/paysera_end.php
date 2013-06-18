<?php
	defined('_JEXEC') or die('Restricted access');
?>
<style type="text/css">

#psbutton {
 width: 167px; 
 height: 25px; 
 background: url('https://www.mokejimai.lt/payment/m/m_images/wfiles/i56je3562.png') no-repeat;
 border: none;
 cursor: pointer;
 margin: 20px 0;
}

</style>

<div class="hikashop_paysera_end" id="hikashop_paysera_end">
	<span id="hikashop_paysera_end_message" class="hikashop_paysera_end_message">
		<?php echo JText::sprintf('PLEASE_WAIT_BEFORE_REDIRECTION_TO_X',$method->payment_name).'<br/>'. JText::_('CLICK_ON_BUTTON_IF_NOT_REDIRECTED');?>
	</span>
	<span id="hikashop_paypal_end_spinner" class="hikashop_paypal_end_spinner">
		<img src="<?php echo HIKASHOP_IMAGES.'spinner.gif';?>" />
	</span>
	<br/>
	<form id="hikashop_paysera_form" name="hikashop_paysera_form" action="https://www.mokejimai.lt/pay/" method="post">
		<input id="psbutton" value="" type="submit" alt="<?php echo JText::_('PAY_NOW');?>" />
		<?php
			foreach( $request as $name => $value ) {
				echo '<input type="hidden" name="'.$name.'" value="'.htmlspecialchars((string)$value).'" />';
			}
			$doc =& JFactory::getDocument();
			$doc->addScriptDeclaration("window.addEvent('domready', function() {document.getElementById('hikashop_paysera_form').submit();});");
			JRequest::setVar('noform',1);
		?>
	</form>
</div>