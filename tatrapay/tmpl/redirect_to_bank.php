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

<center><img src="plugins/vmpayment/tatrapay/tatrapay/assets/images/tatrapay-logo.jpg" border="0" width="395" height="110" alt="TatraPay" title="TatraPay" /></center>
<div style="width: 100%; text-align:center;">
	<h2><?php echo vmText::_ ('VMPAYMENT_TATRAPAY_REDIRECT_TO_BANK_INFO'); ?><h2>
	<?php echo $viewData["confirm_button"]; ?>
</div>
