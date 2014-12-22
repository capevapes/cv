<?php
/**
 * BelVG LLC.
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the EULA
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://store.belvg.com/BelVG-LICENSE-COMMUNITY.txt
 *
 *******************************************************************
 * @category   Belvg
 * @package    Belvg_Verpage
 * @version    1.0.2
 * @copyright  Copyright (c) 2010 - 2013 BelVG LLC. (http://www.belvg.com)
 * @license    http://store.belvg.com/BelVG-LICENSE-COMMUNITY.txt
 */
class Belvg_Verpage_Block_Adminhtml_Config_Title extends Mage_Adminhtml_Block_System_Config_Form_Field
{
    
    public function render(Varien_Data_Form_Element_Abstract $element){
        $id = $element->getHtmlId();
        $html = '<tr id="row_' . $id . '" class="system-fieldset-sub-head">' . '<td colspan="5"><h4>' . $element->getLabel() . '</h4></td></tr>';
        return $html;
    }
    
}
