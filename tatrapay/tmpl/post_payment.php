<?php
defined ('_JEXEC') or die();

/**
 * @version: 1.0.0 27.04.2018
 * @package: Tatrapay Virtuemart plugin
 * @copyright: 2018 Richard Forro, All rights reserved.
 * @author: 2018 Richard Forro(riki49@gmail.com)
 * @license: http://www.gnu.org/copyleft/gpl.html GNU/GPL
 * @description: TatraPay (Tatra banka Slovakia) payment plugin for Virtuemart
 * 
 * This plugin is was inpired by stardard payment, skrill, tco, paypal and others Virtuemart payment plugins. 
 */

?>

<div style="height: 30px; margin-bottom: 10px;">
    <img style="margin-right: 10px" src="<?php echo $viewData["confirm_icon"]; ?>">
    <h2 style="display: inline;"><?php echo $viewData["confirm_title"]; ?></h2>
</div>

<div style="width: 100%">
	<span><?php echo vmText::_ ('COM_VIRTUEMART_ORDER_NUMBER'); ?> </span>
	<?php echo  $viewData["order_number"]; ?>
</div>

<div style="width: 100%">
  <span><?php echo $viewData["confirm_detail"]; ?></span>
</div>
<br />

<?php
$tracking = VmConfig::get('ordertracking','guests');
if($tracking !='none' and !($tracking =='registered' and empty($viewData["virtuemart_user_id"]) )){

$orderlink = 'index.php?option=com_virtuemart&view=orders&layout=details&order_number='.$viewData["order_number"];
if( $tracking == 'guestlink' or ( $tracking == 'guests' and empty($viewData["virtuemart_user_id"]))){
	$orderlink .= '&order_pass='.$viewData["order_pass"];
}
?>
<a class="vm-button-correct" href="<?php echo JRoute::_($orderlink, false)?>"><?php echo vmText::_('COM_VIRTUEMART_ORDER_VIEW_ORDER'); ?></a>
<?php
}
?>






