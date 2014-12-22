<?php

class Infortis_Fortis_Helper_Grid extends Mage_Core_Helper_Abstract
{
	/**
	 * Values: number of columns / grid item width
	 *
	 * @var array
	 */
	protected $_itemWidth = array(
		"1" => 98,
		"2" => 48,
		"3" => 31.3333,
		"4" => 23,
		"5" => 18,
		"6" => 14.6666,
		"7" => 12.2857,
		"8" => 10.5,
	);

	/**
	 * Get CSS for grid item based on number of columns
	 *
	 * @param int $columnCount
	 * @return string
	 */
	public function getCssGridItem($columnCount)
	{
		$out = "\n";
		$out .= '.itemgrid.itemgrid-adaptive .item { width:' . $this->_itemWidth[$columnCount] . '%; clear:none !important; }' . "\n";

		if ($columnCount > 1)
		{
			$out .= '.itemgrid.itemgrid-adaptive .item:nth-child(' . $columnCount . 'n+1) { clear:left !important; }' . "\n";
		}

		return $out;
	}

	/**
	 * Get CSS for grid item based on number of columns (style 1, narrower than standard grid item)
	 *
	 * @param int $columnCount
	 * @return string
	 */
	public function getCssGridItemStyle1($columnCount)
	{
		$out = "\n";
		//In grid "style 1" items are 4% narrower than in the standard grid
		$itemWidth = $this->_itemWidth[$columnCount] - 4;
		$out .= '.products-grid-style1.itemgrid-adaptive .item { width:' . $itemWidth . '%; clear:none !important; }' . "\n";

		if ($columnCount > 1)
		{
			$out .= '.products-grid-style1.itemgrid-adaptive .item:nth-child(' . $columnCount . 'n+1) { clear:left !important; }' . "\n";
		}

		return $out;
	}

	/**
	 * Get CSS to disable hover effect
	 *
	 * @return string
	 */
	public function getCssDisableHoverEffect()
	{
		return '
	/* Disable hover effect
	-------------------------------------------------------------- */
		/* Remove item outline */
		/* Cancel "hover effect" styles: apply the same styles which item has without "hover effect" */
		.category-products-grid.hover-effect .item { border-top: none; }
		.category-products-grid.hover-effect .item:hover {
			margin-left:0;
			margin-right:0;
			padding-left:1%;
			padding-right:1%;
			box-shadow: none !important;
		}

		/* Show elements normally displayed only on hover */
		.category-products-grid.hover-effect .item .display-onhover { display:block !important; }
		
		/* Show full name even if enabled: display name in single line */
		.products-grid.single-line-name .item .product-name { overflow: visible; white-space: normal; }



	/* Disable hover effect for grid style 1
	-------------------------------------------------------------- */
		.products-grid-style1.category-products-grid.hover-effect .item:hover {
			border-top:none;
			margin:2% 1% 0%;
			padding-left:2%;
			padding-right:2%;
			padding-top:3%; /* The same value as side padding on hover */
		}

	/* Disable grid style 1
	-------------------------------------------------------------- */
		.products-grid-style1.category-products-grid {
			background-color:transparent;
			padding:0;
		}
		.products-grid-style1.category-products-grid .item {
			box-shadow: none;
		}
		';
	}

	/**
	 * Get CSS to disable hover effect
	 *
	 * @return string
	 */
	public function getCssHideAddtoLinks()
	{
		return'
	/* Products grid
	-------------------------------------------------------------- */
		.products-grid.category-products-grid.hover-effect .item .add-to-links, /* To override "display-onhover" */
		.products-grid .item .add-to-links { display: none !important; }
		';
	}
}